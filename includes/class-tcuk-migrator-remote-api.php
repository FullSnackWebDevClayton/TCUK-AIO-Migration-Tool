<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_Remote_API {
    private $plugin;

    public function __construct( TCUK_Migrator $plugin ) {
        $this->plugin = $plugin;

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'tcuk-migrator/v1',
            '/receive-backup',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'receive_backup' ),
                'permission_callback' => array( $this, 'authorize_request' ),
            )
        );
    }

    public function authorize_request( WP_REST_Request $request ) {
        if ( ! $this->plugin->license->is_premium_active() ) {
            return new WP_Error( 'tcuk_api_premium_required', 'Premium license required for API Push receive endpoint.', array( 'status' => 403 ) );
        }

        $settings = get_option( TCUK_Migrator_Admin::OPTION_KEY, array() );

        $enabled = ! empty( $settings['remote_api_enabled'] );
        if ( ! $enabled ) {
            return new WP_Error( 'tcuk_api_disabled', 'Remote API is disabled on this site.', array( 'status' => 403 ) );
        }

        $expected = (string) ( $settings['remote_api_token'] ?? '' );
        $provided = (string) $request->get_header( 'x-tcuk-api-key' );

        if ( '' === $expected || '' === $provided || ! hash_equals( $expected, $provided ) ) {
            return new WP_Error( 'tcuk_api_unauthorized', 'Invalid API token.', array( 'status' => 401 ) );
        }

        return true;
    }

    public function receive_backup( WP_REST_Request $request ) {
        if ( '1' === (string) $request->get_header( 'x-tcuk-probe' ) ) {
            return rest_ensure_response(
                array(
                    'success'  => true,
                    'probe'    => true,
                    'endpoint' => rest_url( 'tcuk-migrator/v1/receive-backup' ),
                    'site'     => home_url(),
                )
            );
        }

        try {
            @set_time_limit( 0 );
            if ( function_exists( 'wp_raise_memory_limit' ) ) {
                wp_raise_memory_limit( 'admin' );
            }

            $raw_body = $request->get_body();
            if ( '' === $raw_body ) {
                return new WP_Error( 'tcuk_api_empty_body', 'Backup payload is empty.', array( 'status' => 400 ) );
            }

            $filename = sanitize_file_name( (string) $request->get_header( 'x-tcuk-filename' ) );
            if ( '' === $filename ) {
                $filename = 'api-push-' . gmdate( 'Ymd-His' ) . '.zip';
            }

            if ( '.zip' !== strtolower( substr( $filename, -4 ) ) ) {
                $filename .= '.zip';
            }

            $stored_file = $this->plugin->backup_manager->import_raw_backup( $raw_body, $filename );

            $components_header = (string) $request->get_header( 'x-tcuk-restore-components' );
            $components        = array_filter( array_map( 'trim', explode( ',', $components_header ) ) );
            $components        = array_map( 'sanitize_text_field', $components );

            if ( empty( $components ) ) {
                $components = array( 'theme' );
            }

            $components = $this->plugin->license->enforce_theme_only_components( $components );
            if ( empty( $components ) ) {
                return new WP_Error( 'tcuk_api_components_blocked', 'Restore components are not allowed for current plan.', array( 'status' => 403 ) );
            }

            $logs = $this->plugin->backup_manager->restore_backup(
                $stored_file,
                array(
                    'restore_components' => $components,
                )
            );

            return rest_ensure_response(
                array(
                    'success'       => true,
                    'stored'        => $stored_file,
                    'components'    => $components,
                    'logs'          => $logs,
                    'received_from' => (string) $request->get_header( 'x-tcuk-source-site' ),
                )
            );
        } catch ( Throwable $e ) {
            error_log( '[TCUK Migrator] API Push receive failed: ' . $e->getMessage() );

            return new WP_Error(
                'tcuk_api_receive_failed',
                'API Push receive failed on destination: ' . $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }
}

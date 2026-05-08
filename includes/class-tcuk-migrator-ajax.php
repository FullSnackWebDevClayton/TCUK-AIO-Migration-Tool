<?php
/**
 * Handles AJAX requests for the TCUK All-in-One Migrator plugin.
 */
class TCUK_Migrator_Ajax {
    public static function init() {
        add_action('wp_ajax_tcuk_list_directory', [__CLASS__, 'list_directory']);
        // Delegate common admin actions to the admin class so they can be called via admin-ajax
        $delegates = array(
            'tcuk_migrator_save_settings',
            'tcuk_migrator_api_push',
            'tcuk_migrator_ssh_push',
            'tcuk_migrator_ssh_pull',
            'tcuk_migrator_backup_create',
            'tcuk_migrator_backup_upload',
            'tcuk_migrator_backup_restore',
            'tcuk_migrator_backup_delete',
            'tcuk_migrator_github_pull',
            'tcuk_migrator_repair_fse',
            'tcuk_migrator_setup_wizard',
            'tcuk_migrator_ssh_test',
            'tcuk_migrator_github_test',
            'tcuk_migrator_remote_api_test',
        );

        foreach ( $delegates as $action ) {
            add_action( 'wp_ajax_' . $action, function() use ( $action ) {
                // Ensure the request is marked as async so the admin methods return JSON
                if ( empty( $_POST['tcuk_async'] ) ) {
                    $_POST['tcuk_async'] = '1';
                }

                try {
                    $tcuk = TCUK_Migrator::instance();
                    if ( isset( $tcuk->admin ) && is_object( $tcuk->admin ) ) {
                        // Call the corresponding admin handler method if it exists.
                        // Prefer methods named `run_xxx` but fall back to plain `xxx` if present (e.g. save_settings).
                        $base = preg_replace( '/^tcuk_migrator_/', '', $action );
                        $method_run = 'run_' . $base;
                        if ( method_exists( $tcuk->admin, $method_run ) ) {
                            $tcuk->admin->{$method_run}();
                        } elseif ( method_exists( $tcuk->admin, $base ) ) {
                            $tcuk->admin->{$base}();
                        }
                        // admin methods should handle sending JSON and exiting for async requests
                    }
                } catch ( Exception $e ) {
                    wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
                }

                // If we get here, ensure a generic success response
                wp_send_json_success();
            } );
        }
        // Allow client to request refreshed admin markup (rendered server-side) so UI can be swapped in-place
        add_action( 'wp_ajax_tcuk_refresh_admin_markup', [ __CLASS__, 'refresh_admin_markup' ] );
        // Allow toggling the receive endpoint and returning a generated token
        add_action( 'wp_ajax_tcuk_toggle_remote_api', [ __CLASS__, 'toggle_remote_api' ] );
        // Future: add more AJAX actions here (backup, restore, etc)
    }

    /**
     * List contents of a directory via SSH.
     */
    public static function list_directory() {
        check_ajax_referer( 'tcuk_migrator_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        }

        $path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';

        // Load saved settings (single source of truth)
        $settings = get_option( 'tcuk_aio_migrator_settings', array() );

        // If no path provided, try to use configured remote backup dir
        if ( '' === $path ) {
            $path = rtrim( (string) ( $settings['ssh_remote_backup_dir'] ?? '' ), '/' );
            if ( '' === $path ) {
                wp_send_json_error( array( 'message' => 'Remote path not provided.' ), 400 );
            }
        }

        try {
            $tcuk = TCUK_Migrator::instance();
            $items = $tcuk->ssh_sync->list_directory( $path, $settings );

            wp_send_json_success( array( 'path' => $path, 'items' => $items ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
        }
    }

    public static function refresh_admin_markup() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        }

        try {
            ob_start();
            $tcuk = TCUK_Migrator::instance();
            if ( isset( $tcuk->admin ) && is_object( $tcuk->admin ) ) {
                $tcuk->admin->render_page();
            }
            $html = (string) ob_get_clean();

            wp_send_json_success( array( 'html' => $html ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
        }
    }

    public static function toggle_remote_api() {
        check_ajax_referer( 'tcuk_migrator_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        }

        try {
            $enable = isset( $_POST['enable'] ) && '1' === (string) wp_unslash( $_POST['enable'] );

            $option_key = 'tcuk_aio_migrator_settings';
            $settings = get_option( $option_key, array() );
            if ( ! is_array( $settings ) ) {
                $settings = array();
            }
            $before_token = isset( $settings['remote_api_token'] ) ? (string) $settings['remote_api_token'] : '';

            if ( $enable ) {
                $settings['remote_api_enabled'] = 1;
                if ( empty( $settings['remote_api_token'] ) ) {
                    if ( function_exists( 'wp_generate_password' ) ) {
                        $settings['remote_api_token'] = wp_generate_password( 48, false, false );
                    } else {
                        // fallback
                        $settings['remote_api_token'] = bin2hex( openssl_random_pseudo_bytes( 24 ) );
                    }
                }
            } else {
                $settings['remote_api_enabled'] = 0;
            }

            update_option( $option_key, $settings, false );

            // Audit log: record who toggled the receive endpoint and whether a token was generated
            try {
                $user = wp_get_current_user();
                $uid = $user ? (int) $user->ID : 0;
                $ulogin = $user ? (string) $user->user_login : 'unknown';
                $action = $enable ? 'enabled' : 'disabled';
                $generated = ( $enable && '' === $before_token && ! empty( $settings['remote_api_token'] ) );
                error_log( sprintf( '[TCUK Migrator] User %s (ID %d) %s receive endpoint%s', $ulogin, $uid, $action, $generated ? ' and generated receive token' : '' ) );
            } catch ( Exception $e ) {
                // don't let logging errors break the response
            }

            wp_send_json_success( array( 'remote_api_token' => (string) ( $settings['remote_api_token'] ?? '' ), 'remote_api_enabled' => (int) ( $settings['remote_api_enabled'] ?? 0 ) ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
        }
    }
}

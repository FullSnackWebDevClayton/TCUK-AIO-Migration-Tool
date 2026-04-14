<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_Admin {
    const OPTION_KEY      = 'tcuk_aio_migrator_settings';
    const RESULT_TRANSIENT = 'tcuk_aio_migrator_result';
    const WIZARD_TRANSIENT = 'tcuk_aio_migrator_wizard';

    private $plugin;

    public function __construct( TCUK_Migrator $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'admin_post_tcuk_migrator_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_tcuk_migrator_remote_api_test', array( $this, 'run_remote_api_test' ) );
        add_action( 'admin_post_tcuk_migrator_repair_fse', array( $this, 'run_repair_fse' ) );
        add_action( 'admin_post_tcuk_migrator_setup_wizard', array( $this, 'run_setup_wizard' ) );
        add_action( 'admin_post_tcuk_migrator_api_push', array( $this, 'run_api_push' ) );
        add_action( 'admin_post_tcuk_migrator_github_pull', array( $this, 'run_github_pull' ) );
        add_action( 'admin_post_tcuk_migrator_github_test', array( $this, 'run_github_test' ) );
        add_action( 'admin_post_tcuk_migrator_backup_create', array( $this, 'run_backup_create' ) );
        add_action( 'admin_post_tcuk_migrator_backup_upload', array( $this, 'run_backup_upload' ) );
        add_action( 'admin_post_tcuk_migrator_backup_restore', array( $this, 'run_backup_restore' ) );
        add_action( 'admin_post_tcuk_migrator_backup_delete', array( $this, 'run_backup_delete' ) );
        add_action( 'admin_post_tcuk_migrator_backup_download', array( $this, 'run_backup_download' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'TCUK Migrator', 'tcuk-all-in-one-migrator' ),
            __( 'TCUK Migrator', 'tcuk-all-in-one-migrator' ),
            'manage_options',
            'tcuk-migrator',
            array( $this, 'render_page' ),
            'dashicons-migrate',
            58
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_tcuk-migrator' !== $hook ) {
            return;
        }

        $css_file    = TCUK_MIGRATOR_DIR . 'assets/css/admin.css';
        $js_file     = TCUK_MIGRATOR_DIR . 'assets/js/admin.js';
        $css_version = file_exists( $css_file ) ? (string) filemtime( $css_file ) : TCUK_MIGRATOR_VERSION;
        $js_version  = file_exists( $js_file ) ? (string) filemtime( $js_file ) : TCUK_MIGRATOR_VERSION;

        wp_enqueue_style(
            'tcuk-migrator-admin',
            TCUK_MIGRATOR_URL . 'assets/css/admin.css',
            array(),
            $css_version
        );

        wp_enqueue_script(
            'tcuk-migrator-admin',
            TCUK_MIGRATOR_URL . 'assets/js/admin.js',
            array(),
            $js_version,
            true
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();
        $result   = get_transient( self::RESULT_TRANSIENT );
        if ( $result ) {
            delete_transient( self::RESULT_TRANSIENT );
        }

        $local_plugins = array();
        $site_plugins  = $this->plugin->filesystem->list_subdirectories( WP_CONTENT_DIR . '/plugins' );
        $site_themes   = array_keys( wp_get_themes() );
        $backups       = $this->plugin->backup_manager->list_backups();
        $wizard_report = $this->sanitize_wizard_report( get_transient( self::WIZARD_TRANSIENT ) );
        $remote_api_endpoint = rest_url( 'tcuk-migrator/v1/receive-backup' );
        $license_status = $this->plugin->license->get_status();
        $is_premium     = ! empty( $license_status['active'] );
        $license_api_url = $this->plugin->license->get_api_url();

        require TCUK_MIGRATOR_DIR . 'templates/admin-page.php';
    }

    public function save_settings() {
        $this->assert_permissions( 'tcuk_migrator_save_settings' );

        $current_settings = $this->get_settings();
        $settings_scope   = sanitize_key( wp_unslash( $_POST['settings_scope'] ?? 'full' ) );

        if ( 'license' === $settings_scope ) {
            $current_settings['premium_license_key'] = $this->sanitize_secret_field( $_POST['premium_license_key'] ?? '' );
            update_option( self::OPTION_KEY, $current_settings, false );
            delete_transient( self::WIZARD_TRANSIENT );

            $this->set_result( true, array( 'License key saved.' ) );
            $this->redirect_to_page();

            return;
        }

        $settings = array(
            'remote_api_enabled' => ! empty( $_POST['remote_api_enabled'] ) ? 1 : 0,
            'remote_api_token'   => $this->sanitize_secret_field( $_POST['remote_api_token'] ?? '' ),
            'remote_push_site_url' => esc_url_raw( wp_unslash( $_POST['remote_push_site_url'] ?? '' ) ),
            'remote_push_api_key'  => $this->sanitize_secret_field( $_POST['remote_push_api_key'] ?? '' ),
            'remote_push_verify_ssl' => ! empty( $_POST['remote_push_verify_ssl'] ) ? 1 : 0,
            'github_repo'        => sanitize_text_field( wp_unslash( $_POST['github_repo'] ?? '' ) ),
            'github_branch'      => sanitize_text_field( wp_unslash( $_POST['github_branch'] ?? 'main' ) ),
            'github_theme_slug'  => sanitize_text_field( wp_unslash( $_POST['github_theme_slug'] ?? '' ) ),
            'github_token'       => $this->sanitize_secret_field( $_POST['github_token'] ?? '' ),
            'github_repo_subdir' => sanitize_text_field( wp_unslash( $_POST['github_repo_subdir'] ?? '' ) ),
            'premium_license_key' => $current_settings['premium_license_key'],
        );

        if ( array_key_exists( 'premium_license_key', $_POST ) ) {
            $settings['premium_license_key'] = $this->sanitize_secret_field( $_POST['premium_license_key'] ?? '' );
        }

        if ( ! empty( $settings['remote_api_enabled'] ) && '' === $settings['remote_api_token'] ) {
            $settings['remote_api_token'] = wp_generate_password( 48, false, false );
        }

        update_option( self::OPTION_KEY, $settings, false );
        delete_transient( self::WIZARD_TRANSIENT );

        $this->set_result( true, array( 'Settings saved.' ) );
        $this->redirect_to_page();
    }

    public function run_github_pull() {
        $this->assert_permissions( 'tcuk_migrator_github_pull' );
        $this->assert_premium_feature( 'GitHub pull' );

        try {
            @set_time_limit( 0 );
            $logs = $this->plugin->github_sync->pull_theme( $this->get_settings(), wp_unslash( $_POST ) );
            $this->set_result( true, $logs );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_github_test() {
        $this->assert_permissions( 'tcuk_migrator_github_test' );
        $this->assert_premium_feature( 'GitHub connection test' );

        try {
            @set_time_limit( 0 );
            $logs = $this->plugin->github_sync->test_connection( $this->get_settings(), wp_unslash( $_POST ) );
            $this->set_result( true, $logs );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_remote_api_test() {
        $this->assert_permissions( 'tcuk_migrator_remote_api_test' );
        $this->assert_premium_feature( 'API Push connection test' );

        try {
            @set_time_limit( 0 );
            $message = $this->probe_remote_push_endpoint( $this->get_settings() );
            $this->set_result( true, array( 'API Push connection test successful.', $message ) );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_repair_fse() {
        $this->assert_permissions( 'tcuk_migrator_repair_fse' );
        $this->assert_premium_feature( 'FSE repair' );

        try {
            @set_time_limit( 0 );
            $logs = $this->plugin->backup_manager->repair_fse_content();
            $this->set_result( true, $logs );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_setup_wizard() {
        $this->assert_permissions( 'tcuk_migrator_setup_wizard' );
        $this->assert_premium_feature( 'Setup wizard' );

        try {
            @set_time_limit( 0 );
            $report = $this->build_setup_wizard_report( $this->get_settings() );

            set_transient( self::WIZARD_TRANSIENT, $report, 12 * HOUR_IN_SECONDS );

            $summary = sprintf(
                'Setup wizard completed: %d pass, %d warning, %d fail.',
                (int) $report['counts']['pass'],
                (int) $report['counts']['warning'],
                (int) $report['counts']['fail']
            );

            $this->set_result( 0 === (int) $report['counts']['fail'], array( $summary ) );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_api_push() {
        $this->assert_permissions( 'tcuk_migrator_api_push' );
        $this->assert_premium_feature( 'API Push' );

        try {
            @set_time_limit( 0 );

            $settings   = $this->get_settings();
            $endpoints  = $this->build_remote_push_endpoints( $settings['remote_push_site_url'] ?? '' );
            $api_key    = (string) ( $settings['remote_push_api_key'] ?? '' );

            if ( empty( $endpoints ) ) {
                throw new RuntimeException( 'API Push destination URL is required.' );
            }

            if ( '' === $api_key ) {
                throw new RuntimeException( 'API Push key is required.' );
            }

            $request_data = wp_unslash( $_POST );
            $backup_input = $this->build_backup_request_from_sync_request( $request_data );
            $backup_result = $this->plugin->backup_manager->create_backup( $backup_input );

            $backup_file = $backup_result['file_name'] ?? '';
            if ( '' === $backup_file ) {
                throw new RuntimeException( 'Unable to create backup payload for API push.' );
            }

            $backup_path = $this->plugin->backup_manager->get_backup_file_path( $backup_file );
            $payload     = file_get_contents( $backup_path );

            if ( false === $payload || '' === $payload ) {
                throw new RuntimeException( 'Unable to read backup payload file.' );
            }

            $components = array_map( 'sanitize_text_field', (array) ( $request_data['components'] ?? array() ) );
            if ( empty( $components ) ) {
                $components = array( 'theme' );
            }

            $request_result = $this->post_api_push_request(
                $endpoints,
                array(
                    'timeout'   => 300,
                    'sslverify' => ! empty( $settings['remote_push_verify_ssl'] ),
                    'headers'   => array(
                        'Content-Type'              => 'application/octet-stream',
                        'X-TCUK-API-Key'            => $api_key,
                        'X-TCUK-Filename'           => $backup_file,
                        'X-TCUK-Restore-Components' => implode( ',', $components ),
                        'X-TCUK-Source-Site'        => home_url(),
                    ),
                    'body'      => $payload,
                ),
                'API Push'
            );

            $remote_url = $request_result['endpoint'];
            $response   = $request_result['response'];
            $http_code = (int) wp_remote_retrieve_response_code( $response );
            $body      = wp_remote_retrieve_body( $response );

            $messages = array(
                'API Push completed successfully.',
                'API Push endpoint: ' . $remote_url,
                'HTTP ' . $http_code,
            );

            $decoded = json_decode( (string) $body, true );
            if ( is_array( $decoded ) && ! empty( $decoded['logs'] ) && is_array( $decoded['logs'] ) ) {
                foreach ( $decoded['logs'] as $log ) {
                    $messages[] = (string) $log;
                }
            }

            $this->set_result( true, $messages );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_backup_create() {
        $this->assert_permissions( 'tcuk_migrator_backup_create' );

        try {
            @set_time_limit( 0 );
            $request = wp_unslash( $_POST );
            $request = $this->normalize_backup_request_for_plan( $request );
            $result = $this->plugin->backup_manager->create_backup( $request );
            $this->set_result( true, $result['messages'] );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_backup_upload() {
        $this->assert_permissions( 'tcuk_migrator_backup_upload' );

        try {
            @set_time_limit( 0 );

            if ( empty( $_FILES['backup_upload_file'] ) ) {
                throw new RuntimeException( 'Please choose a backup zip file to upload.' );
            }

            $stored_file = $this->plugin->backup_manager->import_uploaded_backup( $_FILES['backup_upload_file'] );

            $restore_request = wp_unslash( $_POST );
            $restore_request = $this->normalize_restore_request_for_plan( $restore_request );
            $restore_components = array_map( 'sanitize_text_field', (array) ( $restore_request['restore_components'] ?? array() ) );

            if ( empty( $restore_components ) ) {
                $restore_components = array( 'theme', 'plugins', 'uploads', 'mu-plugins', 'database' );
            }

            $restore_logs = $this->plugin->backup_manager->restore_backup(
                $stored_file,
                array(
                    'restore_components' => $restore_components,
                )
            );

            $messages = array_merge(
                array( 'Backup file uploaded successfully: ' . $stored_file, 'Uploaded backup restored immediately.' ),
                $restore_logs
            );

            $this->set_result( true, $messages );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_backup_restore() {
        $this->assert_permissions( 'tcuk_migrator_backup_restore' );

        try {
            @set_time_limit( 0 );
            $file = $this->sanitize_backup_file_name( wp_unslash( $_POST['backup_file'] ?? '' ) );
            $request = $this->normalize_restore_request_for_plan( wp_unslash( $_POST ) );
            $logs = $this->plugin->backup_manager->restore_backup( $file, $request );
            $this->set_result( true, $logs );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_backup_delete() {
        $this->assert_permissions( 'tcuk_migrator_backup_delete' );

        try {
            $file = $this->sanitize_backup_file_name( wp_unslash( $_POST['backup_file'] ?? '' ) );
            $this->plugin->backup_manager->delete_backup( $file );
            $this->set_result( true, array( 'Backup deleted: ' . $file ) );
        } catch ( Exception $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
        }

        $this->redirect_to_page();
    }

    public function run_backup_download() {
        $this->assert_permissions( 'tcuk_migrator_backup_download' );

        try {
            $file = $this->sanitize_backup_file_name( wp_unslash( $_GET['backup_file'] ?? '' ) );
            $path = trailingslashit( $this->plugin->backup_manager->get_backup_dir() ) . basename( $file );

            if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
                wp_die( esc_html__( 'Backup file not found.', 'tcuk-all-in-one-migrator' ) );
            }

            if ( false !== stripos( PHP_OS, 'WIN' ) ) {
                $path = str_replace( '\\', '/', $path );
            }

            $size = filesize( $path );
            if ( false === $size ) {
                wp_die( esc_html__( 'Unable to read backup file metadata.', 'tcuk-all-in-one-migrator' ) );
            }

            nocache_headers();
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
            header( 'Content-Length: ' . (string) $size );

            $handle = fopen( $path, 'rb' );
            if ( false === $handle ) {
                wp_die( esc_html__( 'Unable to open backup file.', 'tcuk-all-in-one-migrator' ) );
            }

            while ( ! feof( $handle ) ) {
                echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                flush();
            }

            fclose( $handle );
            exit;
        } catch ( Exception $e ) {
            wp_die( esc_html( $e->getMessage() ) );
        }
    }

    private function sanitize_backup_file_name( $file_name ) {
        $file_name = sanitize_file_name( sanitize_text_field( (string) $file_name ) );

        if ( '' === $file_name || '.zip' !== strtolower( substr( $file_name, -4 ) ) ) {
            throw new RuntimeException( 'Invalid backup file.' );
        }

        return $file_name;
    }

    private function sanitize_secret_field( $value ) {
        return trim( (string) wp_unslash( $value ) );
    }

    private function build_setup_wizard_report( $settings ) {
        $checks = array();

        $this->add_wizard_check(
            $checks,
            'PHP version',
            version_compare( PHP_VERSION, '7.4', '>=' ) ? 'pass' : 'fail',
            'Detected: ' . PHP_VERSION,
            'Requires PHP 7.4 or later.'
        );

        $this->add_wizard_check(
            $checks,
            'mysqli extension',
            extension_loaded( 'mysqli' ) ? 'pass' : 'fail',
            extension_loaded( 'mysqli' ) ? 'Available.' : 'Missing.',
            'Install/enable mysqli extension.'
        );

        $this->add_wizard_check(
            $checks,
            'ZipArchive support',
            class_exists( 'ZipArchive' ) ? 'pass' : 'warning',
            class_exists( 'ZipArchive' ) ? 'Available.' : 'Missing, backup zips may fail.',
            'Install php-zip for reliable backup archive features.'
        );

        $backup_dir = $this->plugin->backup_manager->get_backup_dir();
        $this->add_wizard_check(
            $checks,
            'Backup directory writable',
            is_writable( $backup_dir ) ? 'pass' : 'fail',
            $backup_dir,
            'Fix directory ownership/permissions for uploads backup folder.'
        );

        $this->add_wizard_check(
            $checks,
            'Destination URL',
            '' !== home_url() ? 'pass' : 'warning',
            home_url(),
            'Confirm destination domain is correct before API Push database restores.'
        );

        $github_repo = trim( (string) ( $settings['github_repo'] ?? '' ) );
        if ( '' === $github_repo ) {
            $this->add_wizard_check(
                $checks,
                'GitHub pull configured',
                'warning',
                'Repository not set.',
                'Set GitHub repo if you want one-click theme deploys from GitHub.'
            );
        } else {
            try {
                $github_logs = $this->plugin->github_sync->test_connection( $settings, $settings );
                $this->add_wizard_check(
                    $checks,
                    'GitHub pull configured',
                    'pass',
                    ! empty( $github_logs[1] ) ? (string) $github_logs[1] : 'GitHub connection successful.',
                    ''
                );
            } catch ( Exception $e ) {
                $this->add_wizard_check(
                    $checks,
                    'GitHub pull configured',
                    'fail',
                    $e->getMessage(),
                    'Verify GitHub repository/branch/token in Connection Settings, then test connection again.'
                );
            }
        }

        $remote_api_enabled = ! empty( $settings['remote_api_enabled'] );
        $remote_api_token   = trim( (string) ( $settings['remote_api_token'] ?? '' ) );
        $this->add_wizard_check(
            $checks,
                'API Push receive endpoint',
            ( $remote_api_enabled && '' !== $remote_api_token ) ? 'pass' : 'warning',
                $remote_api_enabled ? 'Enabled at: ' . rest_url( 'tcuk-migrator/v1/receive-backup' ) : 'API Push receive endpoint is disabled. This is expected on source/local sites that only push backups.',
                'Enable API Push receive endpoint + token on destination/live site for one-click pushes from local.'
        );

        $remote_push_url = trim( (string) ( $settings['remote_push_site_url'] ?? '' ) );
        $remote_push_key = trim( (string) ( $settings['remote_push_api_key'] ?? '' ) );
        if ( '' === $remote_push_url || '' === $remote_push_key ) {
            $this->add_wizard_check(
                $checks,
                'API Push settings',
                'warning',
                '' !== $remote_push_url ? 'Configured target: ' . $remote_push_url : 'Remote push URL not set.',
                'Set API Push destination URL + key on source/local site before using API Push.'
            );
        } else {
            try {
                $probe_result = $this->probe_remote_push_endpoint( $settings );
                $this->add_wizard_check(
                    $checks,
                    'API Push settings',
                    'pass',
                    $probe_result,
                    ''
                );
            } catch ( Exception $e ) {
                $this->add_wizard_check(
                    $checks,
                    'API Push settings',
                    'fail',
                    $e->getMessage(),
                    'Verify API Push destination URL/key and ensure destination has API Push receive endpoint enabled.'
                );
            }
        }

        $counts = array(
            'pass'    => 0,
            'warning' => 0,
            'fail'    => 0,
        );

        foreach ( $checks as $check ) {
            if ( isset( $counts[ $check['status'] ] ) ) {
                $counts[ $check['status'] ]++;
            }
        }

        return array(
            'generated_at' => time(),
            'checks'       => $checks,
            'counts'       => $counts,
        );
    }

    private function add_wizard_check( &$checks, $label, $status, $detail, $action ) {
        $allowed = array( 'pass', 'warning', 'fail' );
        if ( ! in_array( $status, $allowed, true ) ) {
            $status = 'warning';
        }

        $checks[] = array(
            'label'  => (string) $label,
            'status' => (string) $status,
            'detail' => (string) $detail,
            'action' => (string) $action,
        );
    }

    private function sanitize_wizard_report( $wizard_report ) {
        if ( empty( $wizard_report ) || ! is_array( $wizard_report ) || empty( $wizard_report['checks'] ) || ! is_array( $wizard_report['checks'] ) ) {
            return $wizard_report;
        }

        $original_count = count( $wizard_report['checks'] );
        $wizard_report['checks'] = array_values(
            array_filter(
                $wizard_report['checks'],
                static function ( $check ) {
                    $label = strtolower( trim( (string) ( $check['label'] ?? '' ) ) );
                    return 'local path configured' !== $label;
                }
            )
        );

        if ( count( $wizard_report['checks'] ) === $original_count ) {
            return $wizard_report;
        }

        $counts = array(
            'pass'    => 0,
            'warning' => 0,
            'fail'    => 0,
        );

        foreach ( $wizard_report['checks'] as $check ) {
            $status = (string) ( $check['status'] ?? '' );
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }
        }

        $wizard_report['counts'] = $counts;
        set_transient( self::WIZARD_TRANSIENT, $wizard_report, 12 * HOUR_IN_SECONDS );

        return $wizard_report;
    }

    private function build_remote_push_endpoints( $site_url ) {
        $site_url = trim( (string) $site_url );
        if ( '' === $site_url ) {
            return array();
        }

        $site_url     = untrailingslashit( $site_url );
        $pretty_route = $site_url . '/wp-json/tcuk-migrator/v1/receive-backup';
        $query_route  = $site_url . '/?rest_route=/tcuk-migrator/v1/receive-backup';

        return array_values( array_unique( array( $pretty_route, $query_route ) ) );
    }

    private function build_backup_request_from_sync_request( $request ) {
        $components = array_map( 'sanitize_text_field', (array) ( $request['components'] ?? array() ) );

        $backup_request = array(
            'backup_components'    => $components,
            'backup_plugin_mode'   => sanitize_text_field( $request['plugin_mode'] ?? 'all' ),
            'backup_plugins'       => array_map( 'sanitize_text_field', (array) ( $request['selected_plugins'] ?? array() ) ),
            'backup_db_mode'       => sanitize_text_field( $request['db_mode'] ?? 'all' ),
            'backup_db_groups'     => array_map( 'sanitize_text_field', (array) ( $request['db_groups'] ?? array() ) ),
            'backup_custom_tables' => sanitize_textarea_field( $request['custom_tables'] ?? '' ),
        );

        $theme_slug = sanitize_text_field( $request['source_theme_slug'] ?? '' );
        if ( '' !== $theme_slug ) {
            $backup_request['backup_themes'] = array( $theme_slug );
        }

        $target_theme_slug = sanitize_text_field( $request['target_theme_slug'] ?? '' );
        if ( '' !== $target_theme_slug ) {
            $backup_request['target_theme_slug'] = $target_theme_slug;
        }

        return $backup_request;
    }

    private function probe_remote_push_endpoint( $settings ) {
        $endpoints = $this->build_remote_push_endpoints( $settings['remote_push_site_url'] ?? '' );
        $api_key    = trim( (string) ( $settings['remote_push_api_key'] ?? '' ) );

        if ( empty( $endpoints ) ) {
            throw new RuntimeException( 'API Push destination URL is not configured.' );
        }

        if ( '' === $api_key ) {
            throw new RuntimeException( 'API Push key is not configured.' );
        }

        $request_result = $this->post_api_push_request(
            $endpoints,
            array(
                'timeout'   => 30,
                'sslverify' => ! empty( $settings['remote_push_verify_ssl'] ),
                'headers'   => array(
                    'X-TCUK-API-Key' => $api_key,
                    'X-TCUK-Probe'   => '1',
                ),
                'body'      => '',
            ),
            'API Push probe'
        );

        $remote_url = $request_result['endpoint'];
        $response   = $request_result['response'];

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) || empty( $decoded['success'] ) ) {
            throw new RuntimeException( 'API Push probe returned an unexpected response.' );
        }

        return 'API Push endpoint reachable and key accepted: ' . $remote_url . ' (HTTP ' . $code . ')';
    }

    private function post_api_push_request( $endpoints, $request_args, $context_label ) {
        $last_error = '';

        foreach ( (array) $endpoints as $endpoint ) {
            $response = wp_remote_post( $endpoint, $request_args );

            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = (string) wp_remote_retrieve_body( $response );

            if ( $code >= 200 && $code < 300 ) {
                return array(
                    'endpoint' => (string) $endpoint,
                    'response' => $response,
                );
            }

            if ( 404 === $code ) {
                $last_error = 'HTTP 404 at ' . $endpoint;
                continue;
            }

            $remote_message = $this->extract_remote_error_message( $body );
            $message = $context_label . ' failed with HTTP ' . $code . ' at ' . $endpoint . ': ' . $remote_message;

            if ( $code >= 500 ) {
                $message .= ' Check destination site logs (`wp-content/debug.log` or server error log) for the exact fatal stack trace.';
            }

            throw new RuntimeException( $message );
        }

        if ( '' !== $last_error ) {
            throw new RuntimeException( $context_label . ' failed. ' . $last_error . '. Check destination URL, endpoint availability, and permalinks/rewrite settings.' );
        }

        throw new RuntimeException( $context_label . ' failed: no reachable endpoint variants were found.' );
    }

    private function extract_remote_error_message( $body ) {
        $body = (string) $body;
        $decoded = json_decode( $body, true );

        if ( is_array( $decoded ) ) {
            if ( ! empty( $decoded['message'] ) ) {
                return (string) $decoded['message'];
            }

            if ( ! empty( $decoded['data']['message'] ) ) {
                return (string) $decoded['data']['message'];
            }
        }

        $clean = trim( wp_strip_all_tags( $body ) );
        if ( '' === $clean ) {
            return 'No response body was returned.';
        }

        return $clean;
    }

    private function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );

        return wp_parse_args(
            $saved,
            array(
                'remote_api_enabled' => 0,
                'remote_api_token'   => '',
                'remote_push_site_url' => '',
                'remote_push_api_key'  => '',
                'remote_push_verify_ssl' => 1,
                'github_repo'        => '',
                'github_branch'      => 'main',
                'github_theme_slug'  => '',
                'github_token'       => '',
                'github_repo_subdir' => '',
                'premium_license_key' => '',
            )
        );
    }

    private function assert_premium_feature( $feature_name ) {
        try {
            $this->plugin->license->assert_premium_or_throw( (string) $feature_name );
        } catch ( RuntimeException $e ) {
            $this->set_result( false, array( $e->getMessage() ) );
            $this->redirect_to_page();
        }
    }

    private function normalize_backup_request_for_plan( $request ) {
        $components = array_map( 'sanitize_text_field', (array) ( $request['backup_components'] ?? array() ) );
        $components = $this->plugin->license->enforce_theme_only_components( $components );

        if ( empty( $components ) ) {
            throw new RuntimeException( 'Free plan allows theme-only file backup. Select Theme in backup components.' );
        }

        $request['backup_components'] = $components;

        if ( ! $this->plugin->license->is_premium_active() ) {
            unset( $request['backup_plugins'], $request['backup_plugin_mode'], $request['backup_db_mode'], $request['backup_db_groups'], $request['backup_custom_tables'] );
        }

        return $request;
    }

    private function normalize_restore_request_for_plan( $request ) {
        $components = array_map( 'sanitize_text_field', (array) ( $request['restore_components'] ?? array() ) );

        if ( empty( $components ) ) {
            $components = array( 'theme' );
        }

        $components = $this->plugin->license->enforce_theme_only_components( $components );

        if ( empty( $components ) ) {
            throw new RuntimeException( 'Free plan allows theme-only file restore. Select Theme in restore components.' );
        }

        $request['restore_components'] = $components;

        return $request;
    }

    private function assert_permissions( $nonce_action ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'tcuk-all-in-one-migrator' ) );
        }

        check_admin_referer( $nonce_action );
    }

    private function set_result( $success, $messages ) {
        set_transient(
            self::RESULT_TRANSIENT,
            array(
                'success'  => (bool) $success,
                'messages' => (array) $messages,
            ),
            60
        );
    }

    private function redirect_to_page() {
        $redirect_url = admin_url( 'admin.php?page=tcuk-migrator' );

        if ( $this->is_async_request() ) {
            wp_send_json_success(
                array(
                    'redirect' => $redirect_url,
                )
            );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    private function is_async_request() {
        if ( isset( $_POST['tcuk_async'] ) && '1' === (string) wp_unslash( $_POST['tcuk_async'] ) ) {
            return true;
        }

        $requested_with = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) ) : '';
        if ( 'xmlhttprequest' === $requested_with ) {
            return true;
        }

        $accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
        return false !== stripos( $accept, 'application/json' );
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_Backup_Manager {
    private $filesystem;
    private $database;
    private $license;

    public function __construct( TCUK_Migrator_Filesystem $filesystem, TCUK_Migrator_Database $database, TCUK_Migrator_License $license ) {
        $this->filesystem = $filesystem;
        $this->database   = $database;
        $this->license    = $license;
    }

    public function get_backup_dir() {
        $upload_dir = wp_get_upload_dir();
        $dir        = trailingslashit( $upload_dir['basedir'] ) . 'tcuk-migrator-backups';
        $this->filesystem->ensure_dir( $dir );

        return $dir;
    }

    public function create_backup( $request ) {
        global $wpdb;

        $self_plugin_slug = $this->get_self_plugin_slug();

        $components = array_map( 'sanitize_text_field', (array) ( $request['backup_components'] ?? array() ) );
        $components = $this->license->enforce_theme_only_components( $components );
        if ( empty( $components ) ) {
            throw new RuntimeException( 'Select at least one backup component.' );
        }

        $temp_dir    = $this->filesystem->create_temp_dir( 'backup-' );
        $working_dir = trailingslashit( $temp_dir ) . 'backup';
        $this->filesystem->ensure_dir( $working_dir );

        try {
            $manifest = array(
                'created_at' => gmdate( 'c' ),
                'site_url'   => home_url(),
                'components' => $components,
            );

            $target_theme_slug = sanitize_key( $request['target_theme_slug'] ?? '' );
            if ( '' !== $target_theme_slug ) {
                $manifest['target_theme_slug'] = $target_theme_slug;
            }

            if ( in_array( 'theme', $components, true ) ) {
                $themes = array_map( 'sanitize_text_field', (array) ( $request['backup_themes'] ?? array() ) );
                if ( empty( $themes ) ) {
                    $themes = array( wp_get_theme()->get_stylesheet() );
                }

                $theme_count = count( $themes );
                $manifest_themes = array();

                foreach ( $themes as $slug ) {
                    $source_slug      = sanitize_key( $slug );
                    $destination_slug = $source_slug;

                    if ( '' !== $target_theme_slug && 1 === $theme_count ) {
                        $destination_slug = $target_theme_slug;
                    }

                    $this->filesystem->copy_recursive(
                        WP_CONTENT_DIR . '/themes/' . $source_slug,
                        $working_dir . '/wp-content/themes/' . $destination_slug,
                        true
                    );

                    $manifest_themes[] = $destination_slug;
                }

                $manifest['themes'] = $manifest_themes;
            }

            if ( in_array( 'plugins', $components, true ) ) {
                $mode = sanitize_text_field( $request['backup_plugin_mode'] ?? 'all' );
                if ( 'selected' === $mode ) {
                    $plugins = array_map( 'sanitize_text_field', (array) ( $request['backup_plugins'] ?? array() ) );
                    foreach ( $plugins as $slug ) {
                        $slug = sanitize_key( $slug );
                        if ( '' === $slug || $slug === $self_plugin_slug ) {
                            continue;
                        }

                        $this->filesystem->copy_recursive(
                            WP_CONTENT_DIR . '/plugins/' . $slug,
                            $working_dir . '/wp-content/plugins/' . $slug,
                            true
                        );
                    }
                    $manifest['plugins'] = $plugins;
                } else {
                    $this->filesystem->copy_recursive(
                        WP_CONTENT_DIR . '/plugins',
                        $working_dir . '/wp-content/plugins',
                        true,
                        array( $self_plugin_slug )
                    );
                    $manifest['plugins'] = 'all';
                }
            }

            if ( in_array( 'uploads', $components, true ) ) {
                $this->filesystem->copy_recursive(
                    WP_CONTENT_DIR . '/uploads',
                    $working_dir . '/wp-content/uploads',
                    true,
                    array(
                        'tcuk-migrator-temp',
                        'tcuk-migrator-backups',
                    )
                );
            }

            if ( in_array( 'mu-plugins', $components, true ) ) {
                $this->filesystem->copy_recursive(
                    WP_CONTENT_DIR . '/mu-plugins',
                    $working_dir . '/wp-content/mu-plugins',
                    true
                );
            }

            if ( in_array( 'database', $components, true ) ) {
                $selection = array(
                    'all_tables'    => 'all' === sanitize_text_field( $request['backup_db_mode'] ?? 'all' ),
                    'groups'        => array_map( 'sanitize_text_field', (array) ( $request['backup_db_groups'] ?? array() ) ),
                    'custom_tables' => sanitize_textarea_field( $request['backup_custom_tables'] ?? '' ),
                );

                $this->database->export_current_db( $selection, $wpdb->prefix, $working_dir . '/database.sql' );
                $manifest['db_selection'] = $selection;
            }

            $manifest_json = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
            if ( false === $manifest_json ) {
                throw new RuntimeException( 'Unable to generate backup manifest file.' );
            }

            if ( false === file_put_contents( $working_dir . '/manifest.json', $manifest_json ) ) {
                throw new RuntimeException( 'Unable to write backup manifest file.' );
            }

            $file_name = 'tcuk-backup-' . gmdate( 'Ymd-His' ) . '.zip';
            $zip_path  = trailingslashit( $this->get_backup_dir() ) . $file_name;
            $this->filesystem->zip_directory( $working_dir, $zip_path );

            if ( ! file_exists( $zip_path ) ) {
                throw new RuntimeException( 'Backup archive was not created successfully.' );
            }

            return array(
                'file_name' => $file_name,
                'messages'  => array(
                    'Backup completed successfully.',
                    'Backup file: ' . $file_name,
                ),
            );
        } finally {
            $this->filesystem->delete_recursive( $temp_dir );
        }
    }

    public function restore_backup( $backup_file, $request ) {
        $restore_components = array_map( 'sanitize_text_field', (array) ( $request['restore_components'] ?? array() ) );
        $restore_components = $this->license->enforce_theme_only_components( $restore_components );
        if ( empty( $restore_components ) ) {
            throw new RuntimeException( 'Select at least one restore component.' );
        }

        $restore_components = array_values( array_unique( $restore_components ) );

        $source_file = trailingslashit( $this->get_backup_dir() ) . basename( $backup_file );
        if ( ! file_exists( $source_file ) ) {
            throw new RuntimeException( 'Backup file not found.' );
        }

        $temp_dir    = $this->filesystem->create_temp_dir( 'restore-' );
        $extract_dir = trailingslashit( $temp_dir ) . 'extract';
        $logs        = array();
        $restored_components = array();
        $self_plugin_slug = $this->get_self_plugin_slug();

        try {
            $this->filesystem->unzip_to( $source_file, $extract_dir );
            $payload_dir = $this->resolve_restore_payload_dir( $extract_dir );

            if ( $payload_dir !== $extract_dir ) {
                $logs[] = 'Detected nested archive root; using payload path: ' . str_replace( trailingslashit( $temp_dir ), '', trailingslashit( $payload_dir ) );
            }

            $source_site_url   = $this->read_manifest_site_url( $payload_dir );
            $manifest_themes   = $this->read_manifest_themes( $payload_dir );
            $target_theme_slug = $this->read_manifest_target_theme_slug( $payload_dir );

            if ( in_array( 'theme', $restore_components, true ) && is_dir( $payload_dir . '/wp-content/themes' ) ) {
                $entries = $this->filesystem->list_subdirectories( $payload_dir . '/wp-content/themes' );
                foreach ( $entries as $entry ) {
                    $this->filesystem->copy_recursive(
                        $payload_dir . '/wp-content/themes/' . $entry,
                        WP_CONTENT_DIR . '/themes/' . $entry,
                        true
                    );
                }
                if ( ! empty( $entries ) ) {
                    $logs[] = 'Themes restored.';
                    $restored_components[] = 'theme';
                }
            }

            if ( in_array( 'plugins', $restore_components, true ) && is_dir( $payload_dir . '/wp-content/plugins' ) ) {
                $entries = $this->filesystem->list_subdirectories( $payload_dir . '/wp-content/plugins' );
                $skipped_self_plugin = false;
                $copied_plugins = 0;
                foreach ( $entries as $entry ) {
                    if ( $entry === $self_plugin_slug ) {
                        $skipped_self_plugin = true;
                        continue;
                    }

                    $this->filesystem->copy_recursive(
                        $payload_dir . '/wp-content/plugins/' . $entry,
                        WP_CONTENT_DIR . '/plugins/' . $entry,
                        true
                    );
                    $copied_plugins++;
                }
                if ( $copied_plugins > 0 ) {
                    $logs[] = 'Plugins restored.';
                    $restored_components[] = 'plugins';
                }
                if ( $skipped_self_plugin ) {
                    $logs[] = 'Skipped restoring active migrator plugin folder to avoid self-overwrite during restore.';
                }
            }

            if ( in_array( 'uploads', $restore_components, true ) && is_dir( $payload_dir . '/wp-content/uploads' ) ) {
                $this->filesystem->copy_recursive(
                    $payload_dir . '/wp-content/uploads',
                    WP_CONTENT_DIR . '/uploads',
                    false
                );
                $logs[] = 'Uploads restored.';
                $restored_components[] = 'uploads';
            }

            if ( in_array( 'mu-plugins', $restore_components, true ) && is_dir( $payload_dir . '/wp-content/mu-plugins' ) ) {
                $this->filesystem->copy_recursive(
                    $payload_dir . '/wp-content/mu-plugins',
                    WP_CONTENT_DIR . '/mu-plugins',
                    false
                );
                $logs[] = 'MU plugins restored.';
                $restored_components[] = 'mu-plugins';
            }

            if ( in_array( 'database', $restore_components, true ) && file_exists( $payload_dir . '/database.sql' ) ) {
                $preserved_api_receive = $this->get_preserved_api_receive_settings();
                $this->database->import_sql_into_current( $payload_dir . '/database.sql' );
                $this->restore_preserved_api_receive_settings( $preserved_api_receive );
                $this->enforce_current_site_urls();
                $placeholder_fixes = $this->database->repair_placeholder_tokens_in_current_db();
                $destination_site_url = untrailingslashit( home_url() );
                $replacements = $this->database->replace_url_references_in_current_db( $source_site_url, $destination_site_url );
                $logs[] = 'Database restored.';
                $logs[] = 'Destination site URLs normalized to current domain.';
                $logs[] = 'Destination API Push receive settings preserved.';
                if ( $placeholder_fixes > 0 ) {
                    $logs[] = sprintf( 'Placeholder token cleanup applied in %d rows.', (int) $placeholder_fixes );
                }
                if ( $replacements > 0 ) {
                    $logs[] = sprintf( 'URL references updated from source to destination domain in %d rows.', (int) $replacements );
                }

                $restored_components[] = 'database';
            }

            if ( empty( $restored_components ) ) {
                throw new RuntimeException( 'Backup restore aborted: no selected components were found in the uploaded zip. Ensure this is a valid TCUK backup archive.' );
            }

            $missing_components = array_values( array_diff( $restore_components, $restored_components ) );
            if ( ! empty( $missing_components ) ) {
                $logs[] = 'Selected components not found in archive: ' . implode( ', ', $missing_components ) . '.';
            }

            $stabilization_logs = $this->stabilize_post_restore_state( $manifest_themes, $target_theme_slug );
            if ( ! empty( $stabilization_logs ) ) {
                $logs = array_merge( $logs, $stabilization_logs );
            }

            $template_repair_logs = $this->repair_block_theme_template_bindings();
            if ( ! empty( $template_repair_logs ) ) {
                $logs = array_merge( $logs, $template_repair_logs );
            }

            $fse_content_logs = $this->repair_fse_post_content_theme_attributes();
            if ( ! empty( $fse_content_logs ) ) {
                $logs = array_merge( $logs, $fse_content_logs );
            }

            $logs[] = 'Backup restore completed successfully.';
            return $logs;
        } finally {
            $this->filesystem->delete_recursive( $temp_dir );
        }
    }

    public function repair_fse_content() {
        $logs = array();

        $this->enforce_current_site_urls();
        $logs[] = 'Destination site URLs normalized to current domain.';

        $placeholder_fixes = $this->database->repair_placeholder_tokens_in_current_db();
        if ( $placeholder_fixes > 0 ) {
            $logs[] = sprintf( 'Placeholder token cleanup applied in %d rows.', (int) $placeholder_fixes );
        }

        $alternate_scheme_url = $this->get_alternate_scheme_home_url();
        $current_url          = untrailingslashit( home_url() );

        if ( '' !== $alternate_scheme_url && $alternate_scheme_url !== $current_url ) {
            $replacements = $this->database->replace_url_references_in_current_db( $alternate_scheme_url, $current_url );
            if ( $replacements > 0 ) {
                $logs[] = sprintf( 'Scheme normalization applied to %d rows.', (int) $replacements );
            }
        }

        $template_repair_logs = $this->repair_block_theme_template_bindings();
        if ( ! empty( $template_repair_logs ) ) {
            $logs = array_merge( $logs, $template_repair_logs );
        }

        $content_repair_logs = $this->repair_fse_post_content_theme_attributes();
        if ( ! empty( $content_repair_logs ) ) {
            $logs = array_merge( $logs, $content_repair_logs );
        }

        $term_repair_logs = $this->repair_fse_theme_term_assignments();
        if ( ! empty( $term_repair_logs ) ) {
            $logs = array_merge( $logs, $term_repair_logs );
        }

        $stabilization_logs = $this->stabilize_post_restore_state( array( sanitize_key( get_stylesheet() ) ) );
        if ( ! empty( $stabilization_logs ) ) {
            $logs = array_merge( $logs, $stabilization_logs );
        }

        $logs[] = 'FSE repair completed.';

        return $logs;
    }

    private function enforce_current_site_urls() {
        global $wpdb;

        $site_url = untrailingslashit( home_url() );
        if ( '' === $site_url ) {
            return;
        }

        $options_table = $wpdb->options;

        $wpdb->update(
            $options_table,
            array( 'option_value' => $site_url ),
            array( 'option_name' => 'siteurl' ),
            array( '%s' ),
            array( '%s' )
        );

        $wpdb->update(
            $options_table,
            array( 'option_value' => $site_url ),
            array( 'option_name' => 'home' ),
            array( '%s' ),
            array( '%s' )
        );
    }

    private function read_manifest_site_url( $extract_dir ) {
        $manifest_file = trailingslashit( $extract_dir ) . 'manifest.json';
        if ( ! file_exists( $manifest_file ) || ! is_readable( $manifest_file ) ) {
            return '';
        }

        $content = file_get_contents( $manifest_file );
        if ( false === $content || '' === $content ) {
            return '';
        }

        $decoded = json_decode( $content, true );
        if ( ! is_array( $decoded ) || empty( $decoded['site_url'] ) ) {
            return '';
        }

        return untrailingslashit( (string) $decoded['site_url'] );
    }

    private function read_manifest_themes( $extract_dir ) {
        $manifest_file = trailingslashit( $extract_dir ) . 'manifest.json';
        if ( ! file_exists( $manifest_file ) || ! is_readable( $manifest_file ) ) {
            return array();
        }

        $content = file_get_contents( $manifest_file );
        if ( false === $content || '' === $content ) {
            return array();
        }

        $decoded = json_decode( $content, true );
        if ( ! is_array( $decoded ) || empty( $decoded['themes'] ) || ! is_array( $decoded['themes'] ) ) {
            return array();
        }

        return array_values( array_filter( array_map( 'sanitize_key', $decoded['themes'] ) ) );
    }

    private function read_manifest_target_theme_slug( $extract_dir ) {
        $manifest_file = trailingslashit( $extract_dir ) . 'manifest.json';
        if ( ! file_exists( $manifest_file ) || ! is_readable( $manifest_file ) ) {
            return '';
        }

        $content = file_get_contents( $manifest_file );
        if ( false === $content || '' === $content ) {
            return '';
        }

        $decoded = json_decode( $content, true );
        if ( ! is_array( $decoded ) ) {
            return '';
        }

        return sanitize_key( $decoded['target_theme_slug'] ?? '' );
    }

    private function stabilize_post_restore_state( $manifest_themes, $target_theme_slug = '' ) {
        $logs = array();

        $preferred_theme = sanitize_key( $target_theme_slug );
        if ( '' === $preferred_theme && ! empty( $manifest_themes ) ) {
            $preferred_theme = (string) $manifest_themes[0];
        }

        if ( '' !== $preferred_theme ) {
            $theme_obj = wp_get_theme( $preferred_theme );
            if ( $theme_obj->exists() && get_stylesheet() !== $preferred_theme ) {
                switch_theme( $preferred_theme );
                $logs[] = 'Active theme synchronized to restored theme: ' . $preferred_theme;
            }
        }

        if ( function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules( false );
            $logs[] = 'Permalink rewrite rules flushed.';
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
            $logs[] = 'Object cache flushed.';
        }

        return $logs;
    }

    private function repair_block_theme_template_bindings() {
        global $wpdb;

        $active_theme = sanitize_key( get_stylesheet() );
        if ( '' === $active_theme ) {
            return array();
        }

        if ( ! $this->table_exists_for_repair( $wpdb->posts ) ) {
            return array();
        }

        $rows = $wpdb->get_results(
            "SELECT ID, post_name, post_type FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part') AND post_name LIKE '%//%'",
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $renamed = 0;
        foreach ( (array) $rows as $row ) {
            $post_name = (string) ( $row['post_name'] ?? '' );
            $parts = explode( '//', $post_name, 2 );
            if ( 2 !== count( $parts ) ) {
                continue;
            }

            $current_theme_prefix = sanitize_key( $parts[0] );
            $template_slug        = sanitize_title( $parts[1] );

            if ( '' === $template_slug || $current_theme_prefix === $active_theme ) {
                continue;
            }

            $new_post_name = $active_theme . '//' . $template_slug;

            $wpdb->update(
                $wpdb->posts,
                array( 'post_name' => $new_post_name ),
                array( 'ID' => (int) $row['ID'] ),
                array( '%s' ),
                array( '%d' )
            );

            $renamed++;
        }

        $logs = array();
        if ( $renamed > 0 ) {
            $logs[] = sprintf( 'Block template bindings repaired for active theme `%s` (%d records).', $active_theme, (int) $renamed );
        }

        return $logs;
    }

    private function repair_fse_post_content_theme_attributes() {
        global $wpdb;

        $active_theme = sanitize_key( get_stylesheet() );
        if ( '' === $active_theme || ! $this->table_exists_for_repair( $wpdb->posts ) ) {
            return array();
        }

        $rows = $wpdb->get_results(
            "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part') AND post_content LIKE '%theme%';",
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $updated = 0;
        foreach ( (array) $rows as $row ) {
            $old_content = (string) ( $row['post_content'] ?? '' );
            $new_content = preg_replace( '/("theme"\s*:\s*")[^"]+(")/', '$1' . $active_theme . '$2', $old_content );
            $new_content = preg_replace( '/(\\"theme\\"\s*:\s*\\")[^\\"]+(\\")/', '$1' . $active_theme . '$2', (string) $new_content );

            if ( $new_content === $old_content ) {
                continue;
            }

            $wpdb->update(
                $wpdb->posts,
                array( 'post_content' => $new_content ),
                array( 'ID' => (int) $row['ID'] ),
                array( '%s' ),
                array( '%d' )
            );
            $updated++;
        }

        if ( $updated > 0 ) {
            return array( sprintf( 'Template-part theme attributes aligned to active theme `%s` (%d records).', $active_theme, (int) $updated ) );
        }

        return array();
    }

    private function repair_fse_theme_term_assignments() {
        global $wpdb;

        $active_theme = sanitize_key( get_stylesheet() );
        if ( '' === $active_theme || ! taxonomy_exists( 'wp_theme' ) || ! $this->table_exists_for_repair( $wpdb->posts ) ) {
            return array();
        }

        $term = term_exists( $active_theme, 'wp_theme' );
        if ( ! $term ) {
            $created = wp_insert_term( $active_theme, 'wp_theme', array( 'slug' => $active_theme ) );
            if ( is_wp_error( $created ) ) {
                return array( 'Unable to ensure FSE theme term: ' . $created->get_error_message() );
            }
        }

        $rows = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part','wp_global_styles') AND post_status NOT IN ('trash','auto-draft')",
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $assigned = 0;
        foreach ( (array) $rows as $row ) {
            $post_id = (int) ( $row['ID'] ?? 0 );
            if ( $post_id <= 0 ) {
                continue;
            }

            wp_set_object_terms( $post_id, array( $active_theme ), 'wp_theme', false );
            $assigned++;
        }

        if ( $assigned > 0 ) {
            return array( sprintf( 'FSE theme term assignments synchronized to `%s` (%d records).', $active_theme, (int) $assigned ) );
        }

        return array();
    }

    private function table_exists_for_repair( $table_name ) {
        global $wpdb;

        $result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        return (string) $result === (string) $table_name;
    }

    private function get_alternate_scheme_home_url() {
        $home_url = untrailingslashit( home_url() );

        if ( 0 === strpos( $home_url, 'https://' ) ) {
            return 'http://' . substr( $home_url, 8 );
        }

        if ( 0 === strpos( $home_url, 'http://' ) ) {
            return 'https://' . substr( $home_url, 7 );
        }

        return '';
    }

    private function get_preserved_api_receive_settings() {
        $settings = get_option( TCUK_Migrator_Admin::OPTION_KEY, array() );

        return array(
            'remote_api_enabled' => ! empty( $settings['remote_api_enabled'] ) ? 1 : 0,
            'remote_api_token'   => isset( $settings['remote_api_token'] ) ? (string) $settings['remote_api_token'] : '',
        );
    }

    private function restore_preserved_api_receive_settings( $preserved ) {
        $settings = get_option( TCUK_Migrator_Admin::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings['remote_api_enabled'] = ! empty( $preserved['remote_api_enabled'] ) ? 1 : 0;
        $settings['remote_api_token']   = isset( $preserved['remote_api_token'] ) ? (string) $preserved['remote_api_token'] : '';

        update_option( TCUK_Migrator_Admin::OPTION_KEY, $settings, false );
    }

    public function list_backups() {
        $dir = $this->get_backup_dir();

        $items = glob( trailingslashit( $dir ) . '*.zip' );
        if ( empty( $items ) ) {
            return array();
        }

        rsort( $items );

        return array_map(
            static function ( $path ) {
                $ts = filemtime( $path );
                $display = '';
                $iso = '';
                if ( $ts ) {
                    $display = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
                    $iso = date_i18n( 'c', $ts );
                }

                return array(
                    'name'             => basename( $path ),
                    'size'             => size_format( filesize( $path ) ),
                    'timestamp'        => $ts,
                    'display_timestamp'=> $display,
                    'iso_timestamp'    => $iso,
                );
            },
            $items
        );
    }

    public function delete_backup( $file_name ) {
        $file = trailingslashit( $this->get_backup_dir() ) . basename( $file_name );
        if ( file_exists( $file ) && ! @unlink( $file ) ) {
            throw new RuntimeException( 'Unable to delete backup file.' );
        }
    }

    public function import_uploaded_backup( $file ) {
        if ( empty( $file ) || ! is_array( $file ) ) {
            throw new RuntimeException( 'No upload file was received.' );
        }

        if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
            throw new RuntimeException( 'Backup upload failed. Please retry with a valid zip file.' );
        }

        $tmp_name = $file['tmp_name'] ?? '';
        if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
            throw new RuntimeException( 'Invalid uploaded file payload.' );
        }

        $original_name = sanitize_file_name( (string) ( $file['name'] ?? 'backup.zip' ) );
        if ( '.zip' !== strtolower( substr( $original_name, -4 ) ) ) {
            throw new RuntimeException( 'Only .zip backup files are supported.' );
        }

        $target_name = $this->build_import_target_name( $original_name );
        $target_path = $this->get_backup_file_path( $target_name );

        if ( ! move_uploaded_file( $tmp_name, $target_path ) ) {
            throw new RuntimeException( 'Unable to move uploaded backup file into storage.' );
        }

        if ( ! file_exists( $target_path ) || ! is_readable( $target_path ) ) {
            throw new RuntimeException( 'Uploaded backup file is not readable after save.' );
        }

        return $target_name;
    }

    public function import_raw_backup( $raw_contents, $original_name = 'remote-push.zip' ) {
        $raw_contents = (string) $raw_contents;
        if ( '' === $raw_contents ) {
            throw new RuntimeException( 'Incoming backup payload is empty.' );
        }

        $target_name = $this->build_import_target_name( sanitize_file_name( (string) $original_name ) );
        $target_path = $this->get_backup_file_path( $target_name );

        if ( false === file_put_contents( $target_path, $raw_contents ) ) {
            throw new RuntimeException( 'Unable to store incoming backup payload.' );
        }

        return $target_name;
    }

    public function get_backup_file_path( $file_name ) {
        return trailingslashit( $this->get_backup_dir() ) . basename( (string) $file_name );
    }

    private function build_import_target_name( $original_name ) {
        if ( '.zip' !== strtolower( substr( $original_name, -4 ) ) ) {
            $original_name .= '.zip';
        }

        $base_name = preg_replace( '/\.zip$/i', '', $original_name );

        return sanitize_file_name( 'tcuk-import-' . gmdate( 'Ymd-His' ) . '-' . $base_name . '.zip' );
    }

    private function resolve_restore_payload_dir( $extract_dir ) {
        $extract_dir = rtrim( (string) $extract_dir, '/\\' );

        if ( file_exists( $extract_dir . '/manifest.json' ) || is_dir( $extract_dir . '/wp-content' ) || file_exists( $extract_dir . '/database.sql' ) ) {
            return $extract_dir;
        }

        $children = $this->filesystem->list_subdirectories( $extract_dir );
        foreach ( $children as $child ) {
            $candidate = $extract_dir . '/' . $child;
            if ( file_exists( $candidate . '/manifest.json' ) || is_dir( $candidate . '/wp-content' ) || file_exists( $candidate . '/database.sql' ) ) {
                return $candidate;
            }
        }

        return $extract_dir;
    }

    private function get_self_plugin_slug() {
        return sanitize_key( basename( dirname( TCUK_MIGRATOR_FILE ) ) );
    }
}

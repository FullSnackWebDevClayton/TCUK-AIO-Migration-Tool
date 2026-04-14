<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_Updater {
    const RELEASE_CACHE_TRANSIENT = 'tcuk_migrator_github_release';

    private $plugin_file;
    private $plugin_basename;
    private $plugin_slug;
    private $repo;

    public function __construct( $plugin_file ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->plugin_slug     = dirname( $this->plugin_basename );
        $this->repo            = 'FullSnackWebDevClayton/TCUK-AIO-Migration-Tool';

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
        add_filter( 'plugins_api', array( $this, 'inject_plugin_info' ), 20, 3 );
        add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
        add_filter( 'http_request_args', array( $this, 'filter_http_request_args' ), 10, 2 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_github_package_source' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( $this, 'clear_release_cache' ), 10, 2 );
    }

    public function add_plugin_row_meta( $links, $file ) {
        if ( $this->plugin_basename !== $file ) {
            return $links;
        }

        $details_url = add_query_arg(
            array(
                'tab'       => 'plugin-information',
                'plugin'    => $this->plugin_slug,
                'section'   => 'description',
                'TB_iframe' => 'true',
                'width'     => 772,
                'height'    => 788,
            ),
            network_admin_url( 'plugin-install.php' )
        );

        $links[] = sprintf(
            '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
            esc_url( $details_url ),
            esc_attr__( 'View plugin details', 'tcuk-all-in-one-migrator' ),
            esc_html__( 'View details', 'tcuk-all-in-one-migrator' )
        );

        return $links;
    }

    public function filter_http_request_args( $args, $url ) {
        $token = $this->get_github_token();
        if ( '' === $token || ! $this->is_repo_related_github_url( (string) $url ) ) {
            return $args;
        }

        if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
            $args['headers'] = array();
        }

        $args['headers']['Authorization'] = 'Bearer ' . $token;
        $args['headers']['User-Agent']    = 'TCUK-Migrator-Updater/' . TCUK_MIGRATOR_VERSION;

        return $args;
    }

    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
            return $transient;
        }

        $installed_version = '';
        if ( isset( $transient->checked[ $this->plugin_basename ] ) ) {
            $installed_version = (string) $transient->checked[ $this->plugin_basename ];
        }

        if ( '' === $installed_version ) {
            $installed_version = TCUK_MIGRATOR_VERSION;
        }

        $release = $this->get_latest_release();
        if ( empty( $release ) || empty( $release['tag_name'] ) ) {
            return $transient;
        }

        $latest_version = $this->normalize_version( (string) $release['tag_name'] );

        if ( '' !== $latest_version && version_compare( $latest_version, $installed_version, '<' ) ) {
            $release        = $this->get_latest_release( true );
            $latest_version = ! empty( $release['tag_name'] ) ? $this->normalize_version( (string) $release['tag_name'] ) : '';
        }

        if ( '' === $latest_version || version_compare( $latest_version, $installed_version, '<=' ) ) {
            return $transient;
        }

        $package = $this->resolve_package_url( $release );
        if ( '' === $package ) {
            return $transient;
        }

        $logo_url = plugins_url( 'assets/images/logo.png', $this->plugin_file );

        $transient->response[ $this->plugin_basename ] = (object) array(
            'slug'        => $this->plugin_slug,
            'plugin'      => $this->plugin_basename,
            'new_version' => $latest_version,
            'url'         => (string) ( $release['html_url'] ?? ( 'https://github.com/' . $this->repo ) ),
            'package'     => $package,
            'tested'      => (string) ( $release['target_commitish'] ?? '' ),
            'requires_php'=> '7.4',
            'icons'       => array(
                'default' => $logo_url,
                '1x'      => $logo_url,
                '2x'      => $logo_url,
            ),
        );

        return $transient;
    }

    public function inject_plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
            return $result;
        }

        $release = $this->get_latest_release( true );
        $download_link = ! empty( $release ) ? $this->resolve_package_url( $release ) : '';
        $release_version = ! empty( $release ) ? $this->normalize_version( (string) ( $release['tag_name'] ?? TCUK_MIGRATOR_VERSION ) ) : '';
        $installed_version = TCUK_MIGRATOR_VERSION;
        $version = $installed_version;

        if ( '' !== $release_version && version_compare( $release_version, $installed_version, '>' ) ) {
            $version = $release_version;
        }

        $changelog     = ! empty( $release ) ? (string) ( $release['body'] ?? '' ) : '';
        $logo_url      = plugins_url( 'assets/images/logo.png', $this->plugin_file );
        $banner_url    = plugins_url( 'assets/images/banner.png', $this->plugin_file );

        return (object) array(
            'name'          => 'TCUK All In One Migrator',
            'slug'          => $this->plugin_slug,
            'version'       => $version,
            'author'        => '<a href="https://techcentreuk.co.uk">Tech Centre UK</a>',
            'homepage'      => (string) ( $release['html_url'] ?? ( 'https://github.com/' . $this->repo ) ),
            'download_link' => $download_link,
            'last_updated'  => (string) ( $release['published_at'] ?? gmdate( 'c' ) ),
            'icons'         => array(
                'default' => $logo_url,
                '1x'      => $logo_url,
                '2x'      => $logo_url,
            ),
            'banners'       => array(
                'low'  => $banner_url,
                'high' => $banner_url,
            ),
            'sections'      => array(
                'description' => '<p>TCUK All In One Migrator is a deployment and migration toolkit for WordPress, combining API Push, GitHub theme pull, and backup/restore workflows in one admin dashboard.</p><p>Move themes, plugins, uploads, and database content with selective component control, environment-safe URL normalization, and post-restore stabilization for reliable production rollouts.</p>',
                'changelog'   => '' !== $changelog ? wp_kses_post( nl2br( $changelog ) ) : 'No changelog provided for this release.',
            ),
        );
    }

    public function clear_release_cache( $upgrader_object, $options ) {
        if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
            return;
        }

        if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
            return;
        }

        $is_target_plugin = false;

        if ( ! empty( $options['plugin'] ) && $this->plugin_basename === (string) $options['plugin'] ) {
            $is_target_plugin = true;
        }

        if ( ! $is_target_plugin && ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
            $is_target_plugin = in_array( $this->plugin_basename, $options['plugins'], true );
        }

        if ( ! $is_target_plugin ) {
            return;
        }

        delete_site_transient( self::RELEASE_CACHE_TRANSIENT );
        delete_site_transient( 'update_plugins' );

        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache( true );
        }
    }

    public function fix_github_package_source( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
            return $source;
        }

        if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return $source;
        }

        $is_target_plugin = false;

        if ( ! empty( $hook_extra['plugin'] ) && $this->plugin_basename === (string) $hook_extra['plugin'] ) {
            $is_target_plugin = true;
        }

        if ( ! $is_target_plugin && ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
            $is_target_plugin = in_array( $this->plugin_basename, $hook_extra['plugins'], true );
        }

        if ( ! $is_target_plugin || ! is_string( $source ) || '' === $source ) {
            return $source;
        }

        $main_file = basename( $this->plugin_file );
        if ( file_exists( trailingslashit( $source ) . $main_file ) ) {
            return $source;
        }

        $first_level_dirs = glob( trailingslashit( $source ) . '*', GLOB_ONLYDIR );
        if ( is_array( $first_level_dirs ) ) {
            foreach ( $first_level_dirs as $dir ) {
                if ( file_exists( trailingslashit( $dir ) . $main_file ) ) {
                    return $dir;
                }
            }

            foreach ( $first_level_dirs as $dir ) {
                $second_level_dirs = glob( trailingslashit( $dir ) . '*', GLOB_ONLYDIR );
                if ( ! is_array( $second_level_dirs ) ) {
                    continue;
                }

                foreach ( $second_level_dirs as $nested_dir ) {
                    if ( file_exists( trailingslashit( $nested_dir ) . $main_file ) ) {
                        return $nested_dir;
                    }
                }
            }
        }

        return $source;
    }

    private function get_latest_release( $force_refresh = false ) {
        $cached = get_site_transient( self::RELEASE_CACHE_TRANSIENT );
        if ( ! $force_refresh && is_array( $cached ) && ! empty( $cached ) ) {
            if ( isset( $cached['release'] ) && is_array( $cached['release'] ) ) {
                return $cached['release'];
            }

            return $cached;
        }

        $headers = array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'TCUK-Migrator-Updater/' . TCUK_MIGRATOR_VERSION,
        );

        $token = $this->get_github_token();
        if ( '' !== $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . $this->repo . '/releases/latest',
            array(
                'timeout' => 20,
                'headers' => $headers,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return array();
        }

        $decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) || empty( $decoded['tag_name'] ) ) {
            return array();
        }

        set_site_transient(
            self::RELEASE_CACHE_TRANSIENT,
            array(
                'fetched_at' => time(),
                'release'    => $decoded,
            ),
            30 * MINUTE_IN_SECONDS
        );

        return $decoded;
    }

    private function resolve_package_url( $release ) {
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                $asset_url  = (string) ( $asset['browser_download_url'] ?? '' );
                $asset_name = strtolower( (string) ( $asset['name'] ?? '' ) );

                if ( '' === $asset_url || '.zip' !== strtolower( substr( $asset_url, -4 ) ) ) {
                    continue;
                }

                if ( false !== strpos( $asset_name, 'tcuk' ) || false !== strpos( $asset_name, 'migrator' ) || false !== strpos( $asset_name, $this->plugin_slug ) ) {
                    return $asset_url;
                }
            }

            foreach ( $release['assets'] as $asset ) {
                $asset_url = (string) ( $asset['browser_download_url'] ?? '' );
                if ( '' !== $asset_url && '.zip' === strtolower( substr( $asset_url, -4 ) ) ) {
                    return $asset_url;
                }
            }
        }

        return (string) ( $release['zipball_url'] ?? '' );
    }

    private function get_github_token() {
        $settings = get_option( 'tcuk_aio_migrator_settings', array() );
        return trim( (string) ( $settings['github_token'] ?? '' ) );
    }

    private function is_repo_related_github_url( $url ) {
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        $host = strtolower( $host );

        $is_github_host = in_array( $host, array( 'api.github.com', 'github.com', 'objects.githubusercontent.com' ), true );
        if ( ! $is_github_host ) {
            return false;
        }

        $repo_fragment = strtolower( $this->repo );
        return false !== stripos( strtolower( $url ), $repo_fragment );
    }

    private function normalize_version( $tag ) {
        return ltrim( trim( (string) $tag ), "vV" );
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_GitHub_Sync {
    private $filesystem;

    public function __construct( TCUK_Migrator_Filesystem $filesystem ) {
        $this->filesystem = $filesystem;
    }

    public function pull_theme( $settings, $request ) {
        $repo       = sanitize_text_field( $request['github_repo'] ?? $settings['github_repo'] ?? '' );
        $branch     = sanitize_text_field( $request['github_branch'] ?? $settings['github_branch'] ?? 'main' );
        $theme_slug = sanitize_text_field( $request['github_theme_slug'] ?? $settings['github_theme_slug'] ?? '' );
        $token      = sanitize_text_field( $request['github_token'] ?? $settings['github_token'] ?? '' );
        $subdir     = sanitize_text_field( $request['github_repo_subdir'] ?? $settings['github_repo_subdir'] ?? '' );

        if ( '' === $repo || '' === $theme_slug ) {
            throw new RuntimeException( 'GitHub repo and theme slug are required.' );
        }

        $repo = $this->normalize_repo( $repo );
        if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ) {
            throw new RuntimeException( 'GitHub repo must be in owner/repo format.' );
        }

        $headers = array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        );

        if ( '' !== $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        list( $owner, $repository ) = explode( '/', $repo, 2 );
        $url      = sprintf( 'https://api.github.com/repos/%s/%s/zipball/%s', rawurlencode( $owner ), rawurlencode( $repository ), rawurlencode( $branch ) );
        $response = wp_remote_get(
            $url,
            array(
                'headers' => $headers,
                'timeout' => 180,
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            throw new RuntimeException( 'GitHub download failed with HTTP status ' . $code );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( '' === $body ) {
            throw new RuntimeException( 'GitHub returned an empty archive.' );
        }

        $temp_dir = $this->filesystem->create_temp_dir( 'github-' );
        $zip_file = trailingslashit( $temp_dir ) . 'repo.zip';
        if ( false === file_put_contents( $zip_file, $body ) ) {
            throw new RuntimeException( 'Unable to write downloaded GitHub archive to temporary storage.' );
        }

        $extract_dir = trailingslashit( $temp_dir ) . 'extract';
        $this->filesystem->unzip_to( $zip_file, $extract_dir );

        $entries = array_values( array_filter( scandir( $extract_dir ), static function ( $entry ) {
            return '.' !== $entry && '..' !== $entry;
        } ) );

        if ( empty( $entries ) ) {
            throw new RuntimeException( 'Unable to locate extracted GitHub archive.' );
        }

        $root = $extract_dir . '/' . $entries[0];

        // If a specific subdirectory was provided, use it. Otherwise try to
        // auto-detect a theme directory inside the extracted archive by
        // looking for folders that contain a top-level style.css with a
        // Theme Name header. This makes pulls robust when the repo root is
        // not the theme folder (common when the repository contains multiple
        // projects or is a monorepo).
        if ( '' !== $subdir ) {
            $root = rtrim( $root, '/\\' ) . '/' . ltrim( $subdir, '/\\' );
        } else {
            // Search for a candidate theme directory under $root. We'll look
            // up to a reasonable depth to avoid scanning huge trees.
            $found = '';
            $max_depth = 4;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if (! $item->isDir()) {
                    continue;
                }

                // compute depth relative to $root
                $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $item->getPathname()), '/\\'));
                $depth = $relative === '' ? 0 : substr_count($relative, '/') + 1;
                if ($depth > $max_depth) {
                    continue;
                }

                $style = $item->getPathname() . DIRECTORY_SEPARATOR . 'style.css';
                if (! file_exists($style)) {
                    continue;
                }

                // Read header portion and check for Theme Name or stylesheet header
                $contents = @file_get_contents($style, false, null, 0, 4096);
                if (false === $contents) {
                    continue;
                }

                if (preg_match('/Theme\s+Name\s*:/i', $contents) || preg_match('/Theme\s+URI\s*:/i', $contents)) {
                    $found = $item->getPathname();
                    break;
                }
            }

            if ('' !== $found) {
                $root = $found;
            }
        }

        if ( ! is_dir( $root ) ) {
            throw new RuntimeException( 'Configured repo subdirectory does not exist in the downloaded archive.' );
        }

        $target = WP_CONTENT_DIR . '/themes/' . $theme_slug;
        // Collect diagnostic info to help debug cases where WP reports
        // "stylesheet is missing" despite a successful pull.
        $diagnostics = array();
        $diagnostics[] = 'Resolved theme root: ' . $root;
        $diag_entries = @scandir( $root );
        if ( false !== $diag_entries ) {
            $visible = array_values( array_filter( $diag_entries, static function ( $e ) { return '.' !== $e && '..' !== $e; } ) );
            $diagnostics[] = 'Entries at root: ' . implode( ', ', array_slice( $visible, 0, 25 ) );
            $diagnostics[] = 'Contains style.css: ' . ( file_exists( rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . 'style.css' ) ? 'yes' : 'no' );
        } else {
            $diagnostics[] = 'Unable to read resolved root directory.';
        }

        $this->filesystem->copy_recursive( $root, $target, ! empty( $request['replace_existing'] ) );

        $this->filesystem->delete_recursive( $temp_dir );

        return array(
            'GitHub theme pull completed.',
            'Repository: ' . $repo,
            'Branch: ' . $branch,
            'Installed into: ' . $theme_slug,
        );
    }

    public function test_connection( $settings, $request ) {
        $repo   = sanitize_text_field( $request['github_repo'] ?? $settings['github_repo'] ?? '' );
        $branch = sanitize_text_field( $request['github_branch'] ?? $settings['github_branch'] ?? 'main' );
        $token  = sanitize_text_field( $request['github_token'] ?? $settings['github_token'] ?? '' );

        if ( '' === $repo ) {
            throw new RuntimeException( 'GitHub repository is required.' );
        }

        $repo = $this->normalize_repo( $repo );
        if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ) {
            throw new RuntimeException( 'GitHub repo must be in owner/repo format.' );
        }

        $headers = array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        );

        if ( '' !== $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        list( $owner, $repository ) = explode( '/', $repo, 2 );

        $repo_url = sprintf( 'https://api.github.com/repos/%s/%s', rawurlencode( $owner ), rawurlencode( $repository ) );
        $repo_res = wp_remote_get(
            $repo_url,
            array(
                'headers' => $headers,
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $repo_res ) ) {
            throw new RuntimeException( 'GitHub connection failed: ' . $repo_res->get_error_message() );
        }

        $repo_code = (int) wp_remote_retrieve_response_code( $repo_res );
        if ( 401 === $repo_code || 403 === $repo_code ) {
            throw new RuntimeException( 'GitHub authentication failed. Check token/PAT permissions for this repository.' );
        }

        if ( 404 === $repo_code ) {
            throw new RuntimeException( 'Repository not found or not accessible with current token.' );
        }

        if ( $repo_code < 200 || $repo_code >= 300 ) {
            throw new RuntimeException( 'GitHub repository check failed with HTTP ' . $repo_code . '.' );
        }

        $repo_body = json_decode( (string) wp_remote_retrieve_body( $repo_res ), true );
        $is_private = is_array( $repo_body ) && ! empty( $repo_body['private'] );

        $branch_url = sprintf( 'https://api.github.com/repos/%s/%s/branches/%s', rawurlencode( $owner ), rawurlencode( $repository ), rawurlencode( $branch ) );
        $branch_res = wp_remote_get(
            $branch_url,
            array(
                'headers' => $headers,
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $branch_res ) ) {
            throw new RuntimeException( 'GitHub branch check failed: ' . $branch_res->get_error_message() );
        }

        $branch_code = (int) wp_remote_retrieve_response_code( $branch_res );
        if ( 404 === $branch_code ) {
            throw new RuntimeException( 'GitHub branch not found: ' . $branch );
        }

        if ( $branch_code < 200 || $branch_code >= 300 ) {
            throw new RuntimeException( 'GitHub branch check failed with HTTP ' . $branch_code . '.' );
        }

        return array(
            'GitHub connection successful.',
            'Repository: ' . $repo,
            'Visibility: ' . ( $is_private ? 'Private' : 'Public' ),
            'Branch found: ' . $branch,
        );
    }

    private function normalize_repo( $repo ) {
        $repo = trim( $repo );

        if ( preg_match( '#github\.com/([^/]+/[^/]+)#', $repo, $matches ) ) {
            $repo = $matches[1];
        }

        $repo = preg_replace( '#\.git$#', '', $repo );

        return trim( $repo, '/ ' );
    }
}

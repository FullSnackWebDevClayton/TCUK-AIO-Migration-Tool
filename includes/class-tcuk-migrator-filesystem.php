<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_Filesystem {
    public function ensure_dir( $dir ) {
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            throw new RuntimeException( 'Unable to create directory: ' . $dir );
        }
    }

    public function delete_recursive( $path ) {
        if ( ! file_exists( $path ) ) {
            return;
        }

        if ( is_file( $path ) || is_link( $path ) ) {
            @unlink( $path );
            return;
        }

        $items = scandir( $path );
        if ( false === $items ) {
            throw new RuntimeException( 'Unable to read directory: ' . $path );
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $this->delete_recursive( $path . DIRECTORY_SEPARATOR . $item );
        }

        @rmdir( $path );
    }

    public function copy_recursive( $source, $destination, $replace_existing = true, $exclude_relative_paths = array() ) {
        $source = rtrim( $source, DIRECTORY_SEPARATOR );
        $destination = rtrim( $destination, DIRECTORY_SEPARATOR );
        $exclude_relative_paths = array_map( 'strval', (array) $exclude_relative_paths );

        if ( ! file_exists( $source ) ) {
            throw new RuntimeException( 'Source does not exist: ' . $source );
        }

        if ( is_file( $source ) ) {
            $this->ensure_dir( dirname( $destination ) );
            if ( $replace_existing || ! file_exists( $destination ) ) {
                if ( ! copy( $source, $destination ) ) {
                    throw new RuntimeException( 'Failed copying file: ' . $source );
                }
            }
            return;
        }

        if ( $replace_existing && is_dir( $destination ) ) {
            $this->delete_recursive( $destination );
        }

        $this->ensure_dir( $destination );

        $source_for_compare = rtrim( str_replace( '\\', '/', $source ), '/' );

        $directory_iterator = new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS );
        $filter_iterator    = new RecursiveCallbackFilterIterator(
            $directory_iterator,
            function ( $current ) use ( $source, $source_for_compare, $exclude_relative_paths ) {
                $current_path = str_replace( '\\', '/', $current->getPathname() );
                $relative_path = ltrim( str_replace( $source_for_compare, '', $current_path ), '/' );

                foreach ( $exclude_relative_paths as $excluded ) {
                    $excluded = trim( str_replace( '\\', '/', (string) $excluded ), '/' );
                    if ( '' === $excluded ) {
                        continue;
                    }

                    if ( $relative_path === $excluded || 0 === strpos( $relative_path, $excluded . '/' ) ) {
                        return false;
                    }
                }

                return true;
            }
        );

        $iterator = new RecursiveIteratorIterator(
            $filter_iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            $relative_path = ltrim( str_replace( $source, '', $item->getPathname() ), DIRECTORY_SEPARATOR );
            $target_path   = $destination . DIRECTORY_SEPARATOR . $relative_path;

            if ( $item->isDir() ) {
                $this->ensure_dir( $target_path );
                continue;
            }

            $this->ensure_dir( dirname( $target_path ) );
            if ( ! copy( $item->getPathname(), $target_path ) ) {
                throw new RuntimeException( 'Failed copying file: ' . $item->getPathname() );
            }
        }
    }

    public function zip_directory( $source_dir, $zip_file ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new RuntimeException( 'ZipArchive is not available on this server.' );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            throw new RuntimeException( 'Unable to create zip: ' . $zip_file );
        }

        $source_dir = rtrim( $source_dir, DIRECTORY_SEPARATOR );
        $iterator   = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            $path_name = $item->getPathname();
            $local     = ltrim( str_replace( $source_dir, '', $path_name ), DIRECTORY_SEPARATOR );

            if ( $item->isDir() ) {
                $zip->addEmptyDir( $local );
            } else {
                $zip->addFile( $path_name, $local );
            }
        }

        $zip->close();
    }

    public function unzip_to( $zip_file, $destination ) {
        $this->ensure_dir( $destination );

        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            $open_result = $zip->open( $zip_file );

            if ( true === $open_result ) {
                if ( ! $zip->extractTo( $destination ) ) {
                    $zip->close();
                    throw new RuntimeException( 'Unable to extract backup archive with ZipArchive.' );
                }

                $zip->close();
                return;
            }
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $result = unzip_file( $zip_file, $destination );
        if ( is_wp_error( $result ) ) {
            throw new RuntimeException( 'Unable to extract backup archive: ' . $result->get_error_message() );
        }
    }

    public function create_temp_dir( $prefix = 'tcuk-migrator-' ) {
        $upload_dir = wp_get_upload_dir();
        $base       = trailingslashit( $upload_dir['basedir'] ) . 'tcuk-migrator-temp';
        $this->ensure_dir( $base );

        $dir = trailingslashit( $base ) . $prefix . wp_generate_password( 8, false, false );
        $this->ensure_dir( $dir );

        return $dir;
    }

    public function list_subdirectories( $path ) {
        $directories = array();
        if ( ! is_dir( $path ) ) {
            return $directories;
        }

        $items = scandir( $path );
        if ( false === $items ) {
            return $directories;
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            if ( is_dir( $path . DIRECTORY_SEPARATOR . $item ) ) {
                $directories[] = $item;
            }
        }

        sort( $directories );
        return $directories;
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_SSH_Sync {
    private $filesystem;
    private $cli_key_file = null;
    private $cli_key_dir  = null;

    public function __construct( TCUK_Migrator_Filesystem $filesystem ) {
        $this->filesystem = $filesystem;
    }

    public function get_capabilities() {
        return array(
            'ssh2_available' => function_exists( 'ssh2_connect' ),
            'cli_available'  => $this->is_cli_transport_available(),
        );
    }

    public function test_connection( $settings ) {
        $config = $this->normalize_settings( $settings );
        $client = $this->open_client( $config, true );

        if ( 'ssh2' === $client['transport'] ) {
            return array(
                'SSH connection successful via PHP ssh2 transport.',
                'Host: ' . $config['host'] . ':' . $config['port'],
                'Authentication: ' . $client['auth_method'],
            );
        }

        $fingerprint = $client['fingerprint'] ?? '';
        $details      = array(
            'SSH connection successful via CLI ssh/scp fallback transport.',
            'Host: ' . $config['host'] . ':' . $config['port'],
            'Authentication: key',
        );
        if ( '' !== $fingerprint ) {
            $details[] = 'Key fingerprint: ' . $fingerprint;
        }

        return $details;
    }

    public function upload_backup_file( $local_file_path, $remote_file_name, $settings ) {
        $local_file_path = (string) $local_file_path;
        if ( '' === $local_file_path || ! file_exists( $local_file_path ) || ! is_readable( $local_file_path ) ) {
            throw new RuntimeException( 'Local backup file does not exist or is not readable.' );
        }

        $config           = $this->normalize_settings( $settings );
        $remote_file_name = sanitize_file_name( (string) $remote_file_name );
        if ( '' === $remote_file_name || '.zip' !== strtolower( substr( $remote_file_name, -4 ) ) ) {
            throw new RuntimeException( 'Remote backup file name must be a .zip file.' );
        }

        $remote_file_path = $this->join_remote_path( $config['remote_backup_dir'], $remote_file_name );
        $client           = $this->open_client( $config, false );

        if ( 'ssh2' === $client['transport'] ) {
            $this->upload_file_via_ssh2( $client, $local_file_path, $remote_file_path );
        } else {
            $this->upload_file_via_cli( $config, $local_file_path, $remote_file_path );
        }

        return array(
            'SSH backup push completed successfully.',
            'Remote file: ' . $remote_file_path,
            'Transport: ' . $client['transport'],
        );
    }

    public function download_backup_file( $remote_file_name, $local_target_path, $settings ) {
        $config           = $this->normalize_settings( $settings );
        $remote_file_name = sanitize_file_name( (string) $remote_file_name );

        if ( '' === $remote_file_name || '.zip' !== strtolower( substr( $remote_file_name, -4 ) ) ) {
            throw new RuntimeException( 'Remote backup file name must be a .zip file.' );
        }

        $local_target_path = (string) $local_target_path;
        if ( '' === $local_target_path ) {
            throw new RuntimeException( 'Local destination path is required for SSH backup pull.' );
        }

        $local_dir = dirname( $local_target_path );
        $this->filesystem->ensure_dir( $local_dir );

        $remote_file_path = $this->join_remote_path( $config['remote_backup_dir'], $remote_file_name );
        $client           = $this->open_client( $config, false );

        if ( 'ssh2' === $client['transport'] ) {
            $this->download_file_via_ssh2( $client, $remote_file_path, $local_target_path );
        } else {
            $this->download_file_via_cli( $config, $remote_file_path, $local_target_path );
        }

        if ( ! file_exists( $local_target_path ) || ! is_readable( $local_target_path ) ) {
            throw new RuntimeException( 'SSH pull completed but local backup file is missing or unreadable.' );
        }

        return array(
            'SSH backup pull completed successfully.',
            'Remote file: ' . $remote_file_path,
            'Transport: ' . $client['transport'],
        );
    }

    private function normalize_settings( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();

        $host = trim( (string) ( $settings['ssh_host'] ?? '' ) );
        $port = (int) ( $settings['ssh_port'] ?? 22 );
        $user = trim( (string) ( $settings['ssh_username'] ?? '' ) );

        if ( '' === $host || '' === $user ) {
            throw new RuntimeException( 'SSH host and username are required.' );
        }

        if ( $port <= 0 ) {
            $port = 22;
        }

        $auth_mode = sanitize_key( (string) ( $settings['ssh_auth_mode'] ?? 'auto' ) );
        if ( ! in_array( $auth_mode, array( 'auto', 'password', 'key' ), true ) ) {
            $auth_mode = 'auto';
        }

        $remote_backup_dir = trim( (string) ( $settings['ssh_remote_backup_dir'] ?? '' ) );
        if ( '' === $remote_backup_dir ) {
            $remote_backup_dir = '/tmp/tcuk-migrator-backups';
        }

        return array(
            'host'                     => $host,
            'port'                     => $port,
            'username'                 => $user,
            'auth_mode'                => $auth_mode,
            'password'                 => (string) ( $settings['ssh_password'] ?? '' ),
            'private_key'              => $this->normalize_key_text( (string) ( $settings['ssh_private_key'] ?? '' ) ),
            'public_key'               => $this->normalize_key_text( (string) ( $settings['ssh_public_key'] ?? '' ) ),
            'key_passphrase'           => (string) ( $settings['ssh_key_passphrase'] ?? '' ),
            'remote_backup_dir'        => $remote_backup_dir,
            'strict_host_key'          => ! empty( $settings['ssh_strict_host_key'] ),
            'host_fingerprint'         => trim( (string) ( $settings['ssh_host_fingerprint'] ?? '' ) ),
            'allow_cli_fallback'       => ! empty( $settings['ssh_allow_cli_fallback'] ),
            'connect_timeout'          => max( 5, (int) ( $settings['ssh_connect_timeout'] ?? 20 ) ),
        );
    }

    private function normalize_key_text( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }

        return str_replace( array( "\r\n", "\r" ), "\n", $value );
    }

    private function open_client( $config, $test_only ) {
        $errors = array();

        if ( function_exists( 'ssh2_connect' ) ) {
            try {
                return $this->open_ssh2_client( $config, $test_only );
            } catch ( Exception $e ) {
                $errors[] = 'ssh2 transport: ' . $e->getMessage();
            }
        }

        if ( ! empty( $config['allow_cli_fallback'] ) ) {
            try {
                $this->assert_cli_requirements( $config );
                $fingerprint = '';
                if ( $test_only ) {
                    $fingerprint = (string) $this->test_cli_connection( $config );
                }

                return array(
                    'transport'   => 'cli',
                    'auth_method' => 'key',
                    'fingerprint' => $fingerprint,
                );
            } catch ( Exception $e ) {
                $errors[] = 'cli fallback: ' . $e->getMessage();
            }
        }

        if ( empty( $errors ) ) {
            throw new RuntimeException( 'No usable SSH transport is available on this server.' );
        }

        throw new RuntimeException( implode( ' | ', $errors ) );
    }

    private function open_ssh2_client( $config, $test_only ) {
        $connection = @ssh2_connect( $config['host'], (int) $config['port'] );
        if ( ! $connection ) {
            throw new RuntimeException( 'Unable to open SSH connection. Verify host/port and firewall access.' );
        }

        $this->verify_ssh2_host_key( $connection, $config );
        $auth_method = $this->authenticate_ssh2( $connection, $config );

        $client = array(
            'transport'   => 'ssh2',
            'auth_method' => $auth_method,
            'connection'  => $connection,
            'sftp'        => null,
        );

        if ( ! $test_only ) {
            $sftp = @ssh2_sftp( $connection );
            if ( ! $sftp ) {
                throw new RuntimeException( 'SSH authenticated but SFTP subsystem is unavailable.' );
            }
            $client['sftp'] = $sftp;
        }

        return $client;
    }

    private function verify_ssh2_host_key( $connection, $config ) {
        if ( empty( $config['strict_host_key'] ) ) {
            return;
        }

        $expected = trim( (string) $config['host_fingerprint'] );
        if ( '' === $expected ) {
            throw new RuntimeException( 'Strict host key verification is enabled, but no host fingerprint is configured.' );
        }

        $fingerprint_options = 0;
        if ( defined( 'SSH2_FINGERPRINT_SHA1' ) ) {
            $fingerprint_options |= SSH2_FINGERPRINT_SHA1;
        }
        if ( defined( 'SSH2_FINGERPRINT_HEX' ) ) {
            $fingerprint_options |= SSH2_FINGERPRINT_HEX;
        }

        $actual = (string) @ssh2_fingerprint( $connection, $fingerprint_options );
        if ( '' === $actual ) {
            throw new RuntimeException( 'Unable to read server host fingerprint for verification.' );
        }

        $expected_normalized = strtolower( preg_replace( '/[^a-f0-9]/', '', $expected ) );
        $actual_normalized   = strtolower( preg_replace( '/[^a-f0-9]/', '', $actual ) );

        if ( '' === $expected_normalized || '' === $actual_normalized || $expected_normalized !== $actual_normalized ) {
            throw new RuntimeException( 'SSH host fingerprint mismatch. Connection aborted.' );
        }
    }

    private function authenticate_ssh2( $connection, $config ) {
        $mode = $config['auth_mode'];

        if ( 'password' === $mode ) {
            $this->authenticate_ssh2_password( $connection, $config );
            return 'password';
        }

        if ( 'key' === $mode ) {
            $this->authenticate_ssh2_key( $connection, $config );
            return 'key';
        }

        try {
            $this->authenticate_ssh2_key( $connection, $config );
            return 'key';
        } catch ( Exception $e ) {
            $this->authenticate_ssh2_password( $connection, $config );
            return 'password';
        }
    }

    private function authenticate_ssh2_password( $connection, $config ) {
        if ( '' === $config['password'] ) {
            throw new RuntimeException( 'SSH password is required for password authentication mode.' );
        }

        $auth_ok = @ssh2_auth_password( $connection, $config['username'], $config['password'] );
        if ( ! $auth_ok ) {
            throw new RuntimeException( 'SSH password authentication failed.' );
        }
    }

    private function authenticate_ssh2_key( $connection, $config ) {
        if ( '' === $config['private_key'] || '' === $config['public_key'] ) {
            throw new RuntimeException( 'SSH key authentication requires both private and public key values.' );
        }

        $temp_dir = $this->filesystem->create_temp_dir( 'ssh-key-' );

        try {
            $private_file = trailingslashit( $temp_dir ) . 'id_rsa';
            $public_file  = trailingslashit( $temp_dir ) . 'id_rsa.pub';

            $norm_private = $this->normalise_pem_key( $config['private_key'] );
            $norm_public  = $this->normalise_pem_key( $config['public_key'] );

            if ( false === file_put_contents( $private_file, $norm_private ) ) {
                throw new RuntimeException( 'Unable to create temporary SSH private key file.' );
            }
            if ( false === file_put_contents( $public_file, $norm_public ) ) {
                throw new RuntimeException( 'Unable to create temporary SSH public key file.' );
            }

            @chmod( $private_file, 0600 );
            @chmod( $public_file, 0600 );

            $auth_ok = @ssh2_auth_pubkey_file(
                $connection,
                $config['username'],
                $public_file,
                $private_file,
                (string) $config['key_passphrase']
            );

            if ( ! $auth_ok ) {
                throw new RuntimeException( 'SSH key authentication failed.' );
            }
        } finally {
            $this->filesystem->delete_recursive( $temp_dir );
        }
    }

    private function upload_file_via_ssh2( $client, $local_file_path, $remote_file_path ) {
        $sftp = $client['sftp'];
        $this->ensure_remote_directory_ssh2( $sftp, dirname( $remote_file_path ) );

        $remote_stream_path = $this->build_sftp_stream_path( $sftp, $remote_file_path );
        $remote_handle      = @fopen( $remote_stream_path, 'w' );
        if ( false === $remote_handle ) {
            throw new RuntimeException( 'Unable to open remote file for writing via SFTP.' );
        }

        $local_handle = @fopen( $local_file_path, 'rb' );
        if ( false === $local_handle ) {
            fclose( $remote_handle );
            throw new RuntimeException( 'Unable to open local backup file for reading.' );
        }

        try {
            while ( ! feof( $local_handle ) ) {
                $chunk = fread( $local_handle, 32768 );
                if ( false === $chunk ) {
                    throw new RuntimeException( 'Failed reading local backup file during SSH upload.' );
                }

                if ( '' !== $chunk && false === fwrite( $remote_handle, $chunk ) ) {
                    throw new RuntimeException( 'Failed writing to remote file during SSH upload.' );
                }
            }
        } finally {
            fclose( $local_handle );
            fclose( $remote_handle );
        }
    }

    private function download_file_via_ssh2( $client, $remote_file_path, $local_target_path ) {
        $sftp = $client['sftp'];

        $remote_stream_path = $this->build_sftp_stream_path( $sftp, $remote_file_path );
        $remote_handle      = @fopen( $remote_stream_path, 'rb' );
        if ( false === $remote_handle ) {
            throw new RuntimeException( 'Unable to open remote backup file for reading via SFTP.' );
        }

        $local_handle = @fopen( $local_target_path, 'wb' );
        if ( false === $local_handle ) {
            fclose( $remote_handle );
            throw new RuntimeException( 'Unable to open local destination file for SSH pull.' );
        }

        try {
            while ( ! feof( $remote_handle ) ) {
                $chunk = fread( $remote_handle, 32768 );
                if ( false === $chunk ) {
                    throw new RuntimeException( 'Failed reading remote file during SSH pull.' );
                }

                if ( '' !== $chunk && false === fwrite( $local_handle, $chunk ) ) {
                    throw new RuntimeException( 'Failed writing local file during SSH pull.' );
                }
            }
        } finally {
            fclose( $remote_handle );
            fclose( $local_handle );
        }
    }

    private function ensure_remote_directory_ssh2( $sftp, $remote_dir ) {
        $remote_dir = $this->normalize_remote_dir( $remote_dir );
        $parts      = array_values( array_filter( explode( '/', trim( $remote_dir, '/' ) ) ) );

        if ( empty( $parts ) ) {
            return;
        }

        $path = ( 0 === strpos( $remote_dir, '/' ) ) ? '/' : '';

        foreach ( $parts as $part ) {
            $path = ( '' === $path || '/' === $path ) ? $path . $part : $path . '/' . $part;

            $stream_path = $this->build_sftp_stream_path( $sftp, $path );
            if ( @is_dir( $stream_path ) ) {
                continue;
            }

            if ( ! @ssh2_sftp_mkdir( $sftp, $path, 0755, true ) && ! @is_dir( $stream_path ) ) {
                throw new RuntimeException( 'Unable to create remote directory for SSH transfer: ' . $path );
            }
        }
    }

    private function build_sftp_stream_path( $sftp, $remote_path ) {
        $remote_path = $this->normalize_remote_path( $remote_path );

        return 'ssh2.sftp://' . intval( $sftp ) . $remote_path;
    }

    private function upload_file_via_cli( $config, $local_file_path, $remote_file_path ) {
        $this->assert_cli_requirements( $config );

        $remote_dir     = dirname( $remote_file_path );
        $remote_command = 'mkdir -p ' . escapeshellarg( $remote_dir );

        $mkdir_command = sprintf(
            '%s %s %s %s',
            $this->escape_shell_binary( 'ssh' ),
            $this->build_cli_ssh_options( $config ),
            escapeshellarg( $config['username'] . '@' . $config['host'] ),
            escapeshellarg( $remote_command )
        );

        $this->run_cli_command( $mkdir_command, 'Unable to create remote backup directory over SSH.' );

        $scp_command = sprintf(
            '%s %s %s %s',
            $this->escape_shell_binary( 'scp' ),
            $this->build_cli_scp_options( $config ),
            escapeshellarg( $local_file_path ),
            escapeshellarg( $config['username'] . '@' . $config['host'] . ':' . $remote_file_path )
        );

        $this->run_cli_command( $scp_command, 'SSH CLI backup push failed.' );
    }

    private function download_file_via_cli( $config, $remote_file_path, $local_target_path ) {
        $this->assert_cli_requirements( $config );

        $scp_command = sprintf(
            '%s %s %s %s',
            $this->escape_shell_binary( 'scp' ),
            $this->build_cli_scp_options( $config ),
            escapeshellarg( $config['username'] . '@' . $config['host'] . ':' . $remote_file_path ),
            escapeshellarg( $local_target_path )
        );

        $this->run_cli_command( $scp_command, 'SSH CLI backup pull failed.' );
    }

    private function test_cli_connection( $config ) {
        $this->assert_cli_requirements( $config );

        $command = sprintf(
            '%s %s %s %s',
            $this->escape_shell_binary( 'ssh' ),
            $this->build_cli_ssh_options( $config ),
            escapeshellarg( $config['username'] . '@' . $config['host'] ),
            escapeshellarg( 'echo tcuk-ssh-ok' )
        );

        $output = $this->run_cli_command( $command, 'SSH CLI connection test failed.' );
        if ( false === strpos( $output, 'tcuk-ssh-ok' ) ) {
            throw new RuntimeException( 'SSH CLI connection test did not return expected response.' );
        }

        return $this->get_cli_key_fingerprint( $config['private_key'] );
    }

    /**
     * Derive the fingerprint of the private key using ssh-keygen -l.
     * Returns an empty string if ssh-keygen is not available or fails.
     *
     * @param string $private_key Raw private key PEM string.
     * @return string  e.g. "SHA256:abc123…" or "".
     */
    private function get_cli_key_fingerprint( $private_key ) {
        try {
            $key_file = $this->create_temp_cli_private_key_file( $private_key );
        } catch ( RuntimeException $e ) {
            return '';
        }

        $disabled = (string) ini_get( 'disable_functions' );
        if ( false !== strpos( $disabled, 'proc_open' ) || ! function_exists( 'proc_open' ) ) {
            return '';
        }

        $cmd    = 'ssh-keygen -l -E sha256 -f ' . escapeshellarg( $key_file ) . ' 2>&1';
        $output = @shell_exec( $cmd );
        if ( ! is_string( $output ) ) {
            return '';
        }

        $output_trim = trim( $output );
        // If ssh-keygen is not available the shell may return an error like
        // "sh: ssh-keygen: command not found". Treat those as absent fingerprint.
        if ( preg_match( '/command not found|ssh-keygen: not found|ssh-keygen: command not found/i', $output_trim ) ) {
            return '';
        }

        // Output line: "4096 SHA256:xxx comment (RSA)" — extract the hash part.
        if ( preg_match( '/\b(SHA256:[A-Za-z0-9+\/=]+)\b/', $output_trim, $m ) ) {
            return $m[1];
        }

        return $output_trim;
    }

    private function build_cli_ssh_options( $config ) {
        return $this->build_cli_common_options( $config, false );
    }

    private function build_cli_scp_options( $config ) {
        return $this->build_cli_common_options( $config, true );
    }

    private function build_cli_common_options( $config, $for_scp ) {
        $options = array(
            ( $for_scp ? '-P ' : '-p ' ) . (int) $config['port'],
            $for_scp ? '-B' : '-o BatchMode=yes',
            '-o ConnectTimeout=' . (int) $config['connect_timeout'],
        );

        if ( ! empty( $config['strict_host_key'] ) ) {
            $options[] = '-o StrictHostKeyChecking=yes';
        } else {
            $options[] = '-o StrictHostKeyChecking=no';
            $options[] = '-o UserKnownHostsFile=/dev/null';
        }

        if ( '' !== $config['private_key'] ) {
            $key_file = $this->create_temp_cli_private_key_file( $config['private_key'] );
            $options[] = '-i ' . escapeshellarg( $key_file );
            $options[] = '-o IdentitiesOnly=yes';
        }

        return implode( ' ', $options );
    }

    private function run_cli_command( $command, $error_prefix ) {
        $disabled = (string) ini_get( 'disable_functions' );
        if ( false !== strpos( $disabled, 'proc_open' ) || ! function_exists( 'proc_open' ) ) {
            throw new RuntimeException( 'CLI fallback requires proc_open(), but it is unavailable on this host.' );
        }

        $descriptor = array(
            0 => array( 'pipe', 'r' ),
            1 => array( 'pipe', 'w' ),
            2 => array( 'pipe', 'w' ),
        );

        $process = @proc_open( $command, $descriptor, $pipes );
        if ( ! is_resource( $process ) ) {
            throw new RuntimeException( $error_prefix . ' Unable to start process.' );
        }

        fclose( $pipes[0] );
        $stdout = stream_get_contents( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );

        $exit_code = proc_close( $process );

        if ( 0 !== (int) $exit_code ) {
            $error_message = trim( (string) $stderr );
            if ( '' === $error_message ) {
                $error_message = trim( (string) $stdout );
            }
            if ( '' === $error_message ) {
                $error_message = 'Unknown SSH CLI error.';
            }

            // Provide actionable hints for the most common failure modes.
            if ( false !== strpos( $error_message, 'Permission denied' ) ) {
                $error_message .= ' — The server rejected the public key. '
                    . 'Check that the matching public key is listed in ~/.ssh/authorized_keys on the remote server, '
                    . 'and that ~/.ssh (700) and authorized_keys (600) have correct permissions.';
            } elseif ( false !== strpos( $error_message, 'Connection refused' ) ) {
                $error_message .= ' — Verify the host address and port are correct and that sshd is running.';
            } elseif ( false !== strpos( $error_message, 'No route to host' ) || false !== strpos( $error_message, 'Could not resolve' ) ) {
                $error_message .= ' — Cannot reach the host. Check the hostname/IP and firewall rules.';
            }

            throw new RuntimeException( $error_prefix . ' ' . $error_message );
        }

        return (string) $stdout;
    }

    private function assert_cli_requirements( $config ) {
        if ( ! $this->is_cli_transport_available() ) {
            throw new RuntimeException( 'CLI fallback requires ssh and scp binaries.' );
        }

        if ( 'password' === $config['auth_mode'] ) {
            throw new RuntimeException( 'CLI fallback supports key authentication only. Use ssh2 transport for password mode.' );
        }

        if ( '' === $config['private_key'] ) {
            throw new RuntimeException( 'CLI fallback requires a private key.' );
        }
    }

    private function is_cli_transport_available() {
        return $this->binary_exists( 'ssh' ) && $this->binary_exists( 'scp' );
    }

    private function binary_exists( $binary ) {
        $binary = (string) $binary;
        if ( '' === $binary ) {
            return false;
        }

        foreach ( array( '/usr/bin/', '/bin/', '/usr/local/bin/' ) as $prefix ) {
            $path = $prefix . $binary;
            if ( is_executable( $path ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise a PEM/OpenSSH private key string so OpenSSH can read it cleanly.
     *
     * Fixes problems introduced when a key is pasted through a browser textarea:
     *   - CRLF or bare CR line endings → LF
     *   - Trailing whitespace on each line (corrupts base64 body)
     *   - Missing final newline (causes "invalid format" with some OpenSSH builds)
     *
     * @param string $key Raw key material from storage.
     * @return string Cleaned key ready to write to a temp file.
     */
    private function normalise_pem_key( $key ) {
        // Normalise line endings first.
        $key = str_replace( "\r\n", "\n", (string) $key );
        $key = str_replace( "\r", "\n", $key );

        // Strip trailing whitespace from every line — browsers often append
        // trailing spaces when text wraps, which silently breaks base64.
        $lines = explode( "\n", $key );
        $lines = array_map( 'rtrim', $lines );
        $key   = implode( "\n", $lines );

        // Remove any blank lines that may have crept in between header / body / footer.
        // Only do this for the body lines (preserve the header and footer as-is).
        // Actually, keep blank lines intact — some implementations wrap them.
        // Just ensure exactly one trailing newline.
        return rtrim( $key ) . "\n";
    }

    private function create_temp_cli_private_key_file( $private_key ) {
        if ( '' !== (string) $this->cli_key_file && file_exists( $this->cli_key_file ) ) {
            return $this->cli_key_file;
        }

        $temp_dir = $this->filesystem->create_temp_dir( 'ssh-cli-key-' );
        $key_file = trailingslashit( $temp_dir ) . 'id_cli';

        $key_content = $this->normalise_pem_key( $private_key );

        if ( false === file_put_contents( $key_file, $key_content ) ) {
            $this->filesystem->delete_recursive( $temp_dir );
            throw new RuntimeException( 'Unable to create temporary private key for CLI fallback.' );
        }

        @chmod( $key_file, 0600 );

        $this->cli_key_file = $key_file;
        $this->cli_key_dir  = $temp_dir;

        return $key_file;
    }

    public function __destruct() {
        if ( '' !== (string) $this->cli_key_dir ) {
            $this->filesystem->delete_recursive( $this->cli_key_dir );
            $this->cli_key_dir  = null;
            $this->cli_key_file = null;
        }
    }

    private function join_remote_path( $remote_dir, $file_name ) {
        $remote_dir = $this->normalize_remote_dir( $remote_dir );
        $file_name  = basename( (string) $file_name );

        return ( '/' === substr( $remote_dir, -1 ) ) ? $remote_dir . $file_name : $remote_dir . '/' . $file_name;
    }

    private function normalize_remote_dir( $remote_dir ) {
        $remote_dir = trim( (string) $remote_dir );
        $remote_dir = str_replace( '\\', '/', $remote_dir );

        if ( '' === $remote_dir ) {
            $remote_dir = 'tcuk-migrator-backups';
        }

        return rtrim( $remote_dir, '/' );
    }

    private function normalize_remote_path( $remote_path ) {
        $remote_path = str_replace( '\\', '/', (string) $remote_path );
        if ( '' === $remote_path ) {
            return '/';
        }

        if ( '/' !== substr( $remote_path, 0, 1 ) ) {
            $remote_path = '/' . $remote_path;
        }

        return $remote_path;
    }

    /**
     * List directory contents on the remote host.
     * Returns an array of items: ['name'=>..., 'type'=>'file'|'dir', 'size'=>int, 'mtime'=>int]
     */
    public function list_directory( $remote_dir, $settings ) {
        $config = $this->normalize_settings( $settings );
        $remote_dir = $this->normalize_remote_path( $remote_dir );

        $client = $this->open_client( $config, false );

        $items = array();

        if ( 'ssh2' === $client['transport'] ) {
            $sftp = $client['sftp'];
            $stream_base = $this->build_sftp_stream_path( $sftp, $remote_dir );

            $handle = @opendir( $stream_base );
            if ( false === $handle ) {
                throw new RuntimeException( 'Unable to open remote directory via SFTP: ' . $remote_dir );
            }

            while ( false !== ( $entry = readdir( $handle ) ) ) {
                if ( '.' === $entry || '..' === $entry ) {
                    continue;
                }

                $entry_path = $remote_dir . ( '/' === substr( $remote_dir, -1 ) ? '' : '/' ) . $entry;
                $stream_path = $this->build_sftp_stream_path( $sftp, $entry_path );
                $stat = @ssh2_sftp_stat( $sftp, $entry_path );

                $type = 'file';
                $size = 0;
                $mtime = 0;
                if ( is_array( $stat ) ) {
                    // mode bit 040000 indicates directory
                    if ( ! empty( $stat['mode'] ) && ( ( $stat['mode'] & 040000 ) === 040000 ) ) {
                        $type = 'dir';
                    }
                    $size = ! empty( $stat['size'] ) ? (int) $stat['size'] : 0;
                    $mtime = ! empty( $stat['mtime'] ) ? (int) $stat['mtime'] : 0;
                } else {
                    // Fallback to is_dir check
                    if ( @is_dir( $stream_path ) ) {
                        $type = 'dir';
                    }
                }

                $display_mtime = '';
                $iso_mtime = '';
                if ( $mtime ) {
                    $display_mtime = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mtime );
                    $iso_mtime = date_i18n( 'c', $mtime );
                }

                $items[] = array(
                    'name'         => $entry,
                    'type'         => $type,
                    'size'         => $size,
                    'mtime'        => $mtime,
                    'display_mtime'=> $display_mtime,
                    'iso_mtime'    => $iso_mtime,
                );
            }

            closedir( $handle );
        } else {
            // CLI fallback: use ls -A -p to distinguish dirs (trailing /)
            $cmd = 'ls -A -p ' . escapeshellarg( $remote_dir );
            $ssh_cmd = sprintf(
                '%s %s %s %s',
                $this->escape_shell_binary( 'ssh' ),
                $this->build_cli_ssh_options( $config ),
                escapeshellarg( $config['username'] . '@' . $config['host'] ),
                escapeshellarg( $cmd )
            );

            $output = $this->run_cli_command( $ssh_cmd, 'Unable to list remote directory via CLI.' );
            $lines = array_filter( array_map( 'trim', explode( "\n", $output ) ) );
            foreach ( $lines as $line ) {
                $type = 'file';
                $name = $line;
                if ( '/' === substr( $line, -1 ) ) {
                    $type = 'dir';
                    $name = substr( $line, 0, -1 );
                }

                $items[] = array(
                    'name'         => $name,
                    'type'         => $type,
                    'size'         => 0,
                    'mtime'        => 0,
                    'display_mtime'=> '',
                    'iso_mtime'    => '',
                );
            }
        }

        return $items;
    }

    /**
     * Create a compressed archive of selected items on the remote host.
     * Returns the remote archive full path.
     * Items should be relative names inside $base_dir.
     */
    public function create_remote_archive( $base_dir, $items, $archive_name, $settings ) {
        $config = $this->normalize_settings( $settings );
        $base_dir = rtrim( str_replace( '\\', '/', (string) $base_dir ), '/' );

        if ( ! is_array( $items ) || empty( $items ) ) {
            throw new RuntimeException( 'No items specified for remote archive.' );
        }

        $safe_items = array();
        foreach ( $items as $it ) {
            $safe_items[] = escapeshellarg( basename( (string) $it ) );
        }

        $archive_name = sanitize_file_name( (string) $archive_name );
        if ( '' === $archive_name ) {
            $archive_name = 'tcuk-archive-' . gmdate( 'Ymd-His' ) . '.tar.gz';
        }

        $remote_archive = $this->join_remote_path( $config['remote_backup_dir'], $archive_name );

        // Build tar command: cd base_dir && tar -czf /remote/path file1 file2 ...
        $tar_cmd = 'cd ' . escapeshellarg( $base_dir ) . ' && tar -czf ' . escapeshellarg( $remote_archive ) . ' ' . implode( ' ', $safe_items );

        $client = $this->open_client( $config, false );

        if ( 'ssh2' === $client['transport'] ) {
            $connection = $client['connection'];
            $stream = @ssh2_exec( $connection, $tar_cmd );
            if ( ! $stream ) {
                throw new RuntimeException( 'Unable to execute remote archive command via SSH2.' );
            }
            stream_set_blocking( $stream, true );
            $output = stream_get_contents( $stream );
            fclose( $stream );
        } else {
            $ssh_cmd = sprintf(
                '%s %s %s %s',
                $this->escape_shell_binary( 'ssh' ),
                $this->build_cli_ssh_options( $config ),
                escapeshellarg( $config['username'] . '@' . $config['host'] ),
                escapeshellarg( $tar_cmd )
            );

            $this->run_cli_command( $ssh_cmd, 'Unable to create remote archive via CLI.' );
        }

        return $remote_archive;
    }

    private function escape_shell_binary( $binary ) {
        foreach ( array( '/usr/bin/', '/bin/', '/usr/local/bin/' ) as $prefix ) {
            $candidate = $prefix . $binary;
            if ( is_executable( $candidate ) ) {
                return escapeshellarg( $candidate );
            }
        }

        return escapeshellarg( $binary );
    }
}

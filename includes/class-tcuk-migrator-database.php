<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_Database {
    public function replace_url_references_in_current_db( $source_url, $target_url ) {
        global $wpdb;

        $source_url = untrailingslashit( trim( (string) $source_url ) );
        $target_url = untrailingslashit( trim( (string) $target_url ) );

        if ( '' === $source_url || '' === $target_url || $source_url === $target_url ) {
            return 0;
        }

        $updated_rows = 0;

        $meta_tables = array(
            array( 'table' => $wpdb->options, 'id' => 'option_id', 'column' => 'option_value' ),
            array( 'table' => $wpdb->postmeta, 'id' => 'meta_id', 'column' => 'meta_value' ),
            array( 'table' => $wpdb->usermeta, 'id' => 'umeta_id', 'column' => 'meta_value' ),
            array( 'table' => $wpdb->commentmeta, 'id' => 'meta_id', 'column' => 'meta_value' ),
        );

        if ( property_exists( $wpdb, 'termmeta' ) && ! empty( $wpdb->termmeta ) ) {
            $meta_tables[] = array( 'table' => $wpdb->termmeta, 'id' => 'meta_id', 'column' => 'meta_value' );
        }

        foreach ( $meta_tables as $spec ) {
            if ( ! $this->table_exists( $spec['table'] ) ) {
                continue;
            }

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$spec['id']} AS row_id, {$spec['column']} AS row_value FROM {$spec['table']} WHERE {$spec['column']} LIKE %s",
                    '%' . $wpdb->esc_like( $source_url ) . '%'
                ),
                ARRAY_A
            );

            foreach ( (array) $rows as $row ) {
                $old_value = (string) ( $row['row_value'] ?? '' );
                $new_value = $this->replace_in_maybe_serialized_value( $old_value, $source_url, $target_url );

                if ( $new_value === $old_value ) {
                    continue;
                }

                $wpdb->update(
                    $spec['table'],
                    array( $spec['column'] => $new_value ),
                    array( $spec['id'] => (int) $row['row_id'] ),
                    array( '%s' ),
                    array( '%d' )
                );
                $updated_rows++;
            }
        }

        if ( $this->table_exists( $wpdb->posts ) ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_content, post_excerpt, guid, pinged, to_ping FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_excerpt LIKE %s OR guid LIKE %s OR pinged LIKE %s OR to_ping LIKE %s",
                    '%' . $wpdb->esc_like( $source_url ) . '%',
                    '%' . $wpdb->esc_like( $source_url ) . '%',
                    '%' . $wpdb->esc_like( $source_url ) . '%',
                    '%' . $wpdb->esc_like( $source_url ) . '%',
                    '%' . $wpdb->esc_like( $source_url ) . '%'
                ),
                ARRAY_A
            );

            foreach ( (array) $rows as $row ) {
                $updates = array();

                foreach ( array( 'post_content', 'post_excerpt', 'guid', 'pinged', 'to_ping' ) as $column ) {
                    $old = (string) ( $row[ $column ] ?? '' );
                    $new = str_replace( $source_url, $target_url, $old );
                    $new = str_replace(
                        str_replace( '/', '\\/', $source_url ),
                        str_replace( '/', '\\/', $target_url ),
                        $new
                    );
                    if ( $new !== $old ) {
                        $updates[ $column ] = $new;
                    }
                }

                if ( empty( $updates ) ) {
                    continue;
                }

                $format = array_fill( 0, count( $updates ), '%s' );
                $wpdb->update( $wpdb->posts, $updates, array( 'ID' => (int) $row['ID'] ), $format, array( '%d' ) );
                $updated_rows++;
            }
        }

        return $updated_rows;
    }

    public function repair_placeholder_tokens_in_current_db() {
        global $wpdb;

        $updated_rows = 0;

        $meta_tables = array(
            array( 'table' => $wpdb->options, 'id' => 'option_id', 'column' => 'option_value' ),
            array( 'table' => $wpdb->postmeta, 'id' => 'meta_id', 'column' => 'meta_value' ),
            array( 'table' => $wpdb->usermeta, 'id' => 'umeta_id', 'column' => 'meta_value' ),
            array( 'table' => $wpdb->commentmeta, 'id' => 'meta_id', 'column' => 'meta_value' ),
        );

        if ( property_exists( $wpdb, 'termmeta' ) && ! empty( $wpdb->termmeta ) ) {
            $meta_tables[] = array( 'table' => $wpdb->termmeta, 'id' => 'meta_id', 'column' => 'meta_value' );
        }

        foreach ( $meta_tables as $spec ) {
            if ( ! $this->table_exists( $spec['table'] ) ) {
                continue;
            }

            $rows = $wpdb->get_results(
                "SELECT {$spec['id']} AS row_id, {$spec['column']} AS row_value FROM {$spec['table']}",
                ARRAY_A
            ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            foreach ( (array) $rows as $row ) {
                $old_value = (string) ( $row['row_value'] ?? '' );
                $new_value = $this->normalize_placeholder_tokens_in_maybe_serialized_value( $old_value );

                if ( $new_value === $old_value ) {
                    continue;
                }

                $wpdb->update(
                    $spec['table'],
                    array( $spec['column'] => $new_value ),
                    array( $spec['id'] => (int) $row['row_id'] ),
                    array( '%s' ),
                    array( '%d' )
                );
                $updated_rows++;
            }
        }

        if ( $this->table_exists( $wpdb->posts ) ) {
            $rows = $wpdb->get_results(
                "SELECT ID, post_content, post_excerpt, guid, pinged, to_ping FROM {$wpdb->posts}",
                ARRAY_A
            ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            foreach ( (array) $rows as $row ) {
                $updates = array();

                foreach ( array( 'post_content', 'post_excerpt', 'guid', 'pinged', 'to_ping' ) as $column ) {
                    $old = (string) ( $row[ $column ] ?? '' );
                    $new = preg_replace( '/\{[a-f0-9]{64}\}/i', '%', $old );

                    if ( is_string( $new ) && $new !== $old ) {
                        $updates[ $column ] = $new;
                    }
                }

                if ( empty( $updates ) ) {
                    continue;
                }

                $format = array_fill( 0, count( $updates ), '%s' );
                $wpdb->update( $wpdb->posts, $updates, array( 'ID' => (int) $row['ID'] ), $format, array( '%d' ) );
                $updated_rows++;
            }
        }

        return $updated_rows;
    }

    public function test_external_connection( $config ) {
        $mysqli = $this->connect_external( $config );
        $mysqli->close();

        return true;
    }

    public function migrate_external_to_current( $source_config, $selection, $source_prefix, $target_prefix, $temp_dir ) {
        $sql_file = trailingslashit( $temp_dir ) . 'external-export.sql';
        $this->export_external_db( $source_config, $selection, $source_prefix, $sql_file );

        $replace_map = array();
        if ( $source_prefix && $target_prefix && $source_prefix !== $target_prefix ) {
            $replace_map[] = array(
                'search'  => '`' . $source_prefix,
                'replace' => '`' . $target_prefix,
            );
            $replace_map[] = array(
                'search'  => ' ' . $source_prefix,
                'replace' => ' ' . $target_prefix,
            );
        }

        $this->import_sql_into_current( $sql_file, $replace_map );
    }

    public function migrate_current_to_external( $target_config, $selection, $source_prefix, $target_prefix, $temp_dir ) {
        $sql_file = trailingslashit( $temp_dir ) . 'current-export.sql';
        $this->export_current_db( $selection, $source_prefix, $sql_file );

        $replace_map = array();
        if ( $source_prefix && $target_prefix && $source_prefix !== $target_prefix ) {
            $replace_map[] = array(
                'search'  => '`' . $source_prefix,
                'replace' => '`' . $target_prefix,
            );
            $replace_map[] = array(
                'search'  => ' ' . $source_prefix,
                'replace' => ' ' . $target_prefix,
            );
        }

        $this->import_sql_into_external( $sql_file, $target_config, $replace_map );
    }

    public function export_current_db( $selection, $prefix, $output_file ) {
        global $wpdb;

        $all_tables = $wpdb->get_col( 'SHOW TABLES' );
        $tables     = $this->resolve_tables( $selection, $prefix, $all_tables );

        if ( empty( $tables ) ) {
            throw new RuntimeException( 'No database tables were selected.' );
        }

        $sql = $this->build_sql_dump_from_wpdb( $wpdb, $tables );
        file_put_contents( $output_file, $sql );
    }

    public function export_external_db( $config, $selection, $prefix, $output_file ) {
        $mysqli = $this->connect_external( $config );

        $db_name = $config['name'];
        $result  = $mysqli->query( 'SHOW TABLES' );

        if ( ! $result ) {
            throw new RuntimeException( 'Unable to read external tables.' );
        }

        $all_tables = array();
        while ( $row = $result->fetch_array( MYSQLI_NUM ) ) {
            $all_tables[] = $row[0];
        }

        $tables = $this->resolve_tables( $selection, $prefix, $all_tables );

        if ( empty( $tables ) ) {
            throw new RuntimeException( 'No external database tables were selected.' );
        }

        $sql = $this->build_sql_dump_from_mysqli( $mysqli, $db_name, $tables );
        file_put_contents( $output_file, $sql );

        $mysqli->close();
    }

    public function import_sql_into_current( $sql_file, $replace_map = array() ) {
        $sql = file_get_contents( $sql_file );
        if ( false === $sql ) {
            throw new RuntimeException( 'Unable to read SQL file: ' . $sql_file );
        }

        $this->import_sql_string_into_current( $sql, $replace_map );
    }

    public function import_sql_string_into_current( $sql, $replace_map = array() ) {
        global $wpdb;

        $sql = $this->apply_replace_map( $sql, $replace_map );
        $statements = $this->split_sql_statements( $sql );

        foreach ( $statements as $statement ) {
            $trimmed = trim( $statement );
            if ( '' === $trimmed ) {
                continue;
            }

            $result = $wpdb->query( $statement ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( false === $result ) {
                throw new RuntimeException( 'SQL import failed: ' . $wpdb->last_error );
            }
        }
    }

    public function import_sql_into_external( $sql_file, $config, $replace_map = array() ) {
        $mysqli = $this->connect_external( $config );

        $sql = file_get_contents( $sql_file );
        if ( false === $sql ) {
            throw new RuntimeException( 'Unable to read SQL file: ' . $sql_file );
        }

        $sql        = $this->apply_replace_map( $sql, $replace_map );
        $statements = $this->split_sql_statements( $sql );

        foreach ( $statements as $statement ) {
            $trimmed = trim( $statement );
            if ( '' === $trimmed ) {
                continue;
            }

            if ( ! $mysqli->query( $statement ) ) {
                throw new RuntimeException( 'External SQL import failed: ' . $mysqli->error );
            }
        }

        $mysqli->close();
    }

    private function connect_external( $config ) {
        $host = $config['host'] ?? '';
        $user = $config['user'] ?? '';
        $pass = $config['pass'] ?? '';
        $name = $config['name'] ?? '';
        $port = isset( $config['port'] ) ? (int) $config['port'] : 3306;

        $mysqli = @new mysqli( $host, $user, $pass, $name, $port );
        if ( $mysqli->connect_error ) {
            throw new RuntimeException( 'External database connection failed: ' . $mysqli->connect_error );
        }

        $mysqli->set_charset( 'utf8mb4' );

        return $mysqli;
    }

    private function build_sql_dump_from_wpdb( $wpdb, $tables ) {
        $output = "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ( $tables as $table ) {
            $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( ! $create || empty( $create[1] ) ) {
                continue;
            }

            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $create[1] . ";\n\n";

            $rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( empty( $rows ) ) {
                continue;
            }

            foreach ( $rows as $row ) {
                $columns = array_map(
                    static function ( $column ) {
                        return '`' . $column . '`';
                    },
                    array_keys( $row )
                );

                $values = array_map(
                    function ( $value ) use ( $wpdb ) {
                        if ( null === $value ) {
                            return 'NULL';
                        }

                        $prepared = $wpdb->prepare( '%s', $value );
                        if ( null === $prepared ) {
                            return "''";
                        }

                        return (string) $prepared;
                    },
                    array_values( $row )
                );

                $output .= 'INSERT INTO `' . $table . '` (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ");\n";
            }

            $output .= "\n";
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $output;
    }

    private function build_sql_dump_from_mysqli( $mysqli, $db_name, $tables ) {
        $output = "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ( $tables as $table ) {
            $safe_table = $mysqli->real_escape_string( $table );
            $create_res = $mysqli->query( "SHOW CREATE TABLE `{$safe_table}`" );
            if ( ! $create_res ) {
                continue;
            }

            $create_row = $create_res->fetch_array( MYSQLI_NUM );
            if ( empty( $create_row[1] ) ) {
                continue;
            }

            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $create_row[1] . ";\n\n";

            $rows_res = $mysqli->query( "SELECT * FROM `{$safe_table}`" );
            if ( ! $rows_res ) {
                continue;
            }

            while ( $row = $rows_res->fetch_assoc() ) {
                $columns = array_map(
                    static function ( $column ) {
                        return '`' . $column . '`';
                    },
                    array_keys( $row )
                );

                $values = array_map(
                    static function ( $value ) use ( $mysqli ) {
                        if ( null === $value ) {
                            return 'NULL';
                        }
                        return "'" . $mysqli->real_escape_string( $value ) . "'";
                    },
                    array_values( $row )
                );

                $output .= 'INSERT INTO `' . $table . '` (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ");\n";
            }

            $output .= "\n";
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $output;
    }

    private function resolve_tables( $selection, $prefix, $all_tables ) {
        if ( ! empty( $selection['all_tables'] ) ) {
            return $all_tables;
        }

        $tables = array();
        $groups = $selection['groups'] ?? array();

        $map = array(
            'options'  => array( $prefix . 'options' ),
            'users'    => array( $prefix . 'users', $prefix . 'usermeta' ),
            'content'  => array( $prefix . 'posts', $prefix . 'postmeta' ),
            'taxonomy' => array( $prefix . 'terms', $prefix . 'term_taxonomy', $prefix . 'term_relationships', $prefix . 'termmeta' ),
            'comments' => array( $prefix . 'comments', $prefix . 'commentmeta' ),
        );

        foreach ( $groups as $group ) {
            if ( isset( $map[ $group ] ) ) {
                $tables = array_merge( $tables, $map[ $group ] );
            }
        }

        if ( ! empty( $selection['custom_tables'] ) ) {
            $raw = preg_split( '/[\r\n,]+/', $selection['custom_tables'] );
            foreach ( $raw as $table_name ) {
                $table_name = trim( $table_name );
                if ( '' === $table_name ) {
                    continue;
                }

                if ( 0 !== strpos( $table_name, $prefix ) ) {
                    $table_name = $prefix . $table_name;
                }

                $tables[] = $table_name;
            }
        }

        $tables = array_values( array_unique( $tables ) );

        return array_values( array_intersect( $tables, $all_tables ) );
    }

    private function split_sql_statements( $sql ) {
        $statements = array();
        $buffer     = '';
        $in_string  = false;
        $length     = strlen( $sql );

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $sql[ $i ];
            $prev = $i > 0 ? $sql[ $i - 1 ] : '';

            if ( "'" === $char && '\\' !== $prev ) {
                $in_string = ! $in_string;
            }

            $buffer .= $char;

            if ( ';' === $char && ! $in_string ) {
                $statements[] = $buffer;
                $buffer       = '';
            }
        }

        if ( trim( $buffer ) !== '' ) {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function apply_replace_map( $sql, $replace_map ) {
        foreach ( $replace_map as $pair ) {
            if ( empty( $pair['search'] ) ) {
                continue;
            }

            $sql = str_replace( $pair['search'], $pair['replace'] ?? '', $sql );
        }

        return $sql;
    }

    private function replace_in_maybe_serialized_value( $value, $search, $replace ) {
        $is_serialized = is_string( $value ) && is_serialized( $value );
        $data          = $is_serialized ? maybe_unserialize( $value ) : $value;
        $data          = $this->replace_recursive_strings( $data, $search, $replace );

        if ( $is_serialized ) {
            return maybe_serialize( $data );
        }

        return (string) $data;
    }

    private function normalize_placeholder_tokens_in_maybe_serialized_value( $value ) {
        $is_serialized = is_string( $value ) && is_serialized( $value );
        $data          = $is_serialized ? maybe_unserialize( $value ) : $value;
        $data          = $this->replace_placeholder_tokens_recursive( $data );

        if ( $is_serialized ) {
            return maybe_serialize( $data );
        }

        return (string) $data;
    }

    private function replace_recursive_strings( $value, $search, $replace ) {
        if ( is_string( $value ) ) {
            $value = str_replace( $search, $replace, $value );

            $search_escaped  = str_replace( '/', '\\/', $search );
            $replace_escaped = str_replace( '/', '\\/', $replace );

            return str_replace( $search_escaped, $replace_escaped, $value );
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                $value[ $key ] = $this->replace_recursive_strings( $child, $search, $replace );
            }
            return $value;
        }

        if ( is_object( $value ) ) {
            foreach ( $value as $key => $child ) {
                $value->$key = $this->replace_recursive_strings( $child, $search, $replace );
            }
            return $value;
        }

        return $value;
    }

    private function replace_placeholder_tokens_recursive( $value ) {
        if ( is_string( $value ) ) {
            $normalized = preg_replace( '/\{[a-f0-9]{64}\}/i', '%', $value );

            return is_string( $normalized ) ? $normalized : $value;
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                $value[ $key ] = $this->replace_placeholder_tokens_recursive( $child );
            }

            return $value;
        }

        if ( is_object( $value ) ) {
            foreach ( $value as $key => $child ) {
                $value->$key = $this->replace_placeholder_tokens_recursive( $child );
            }

            return $value;
        }

        return $value;
    }

    private function table_exists( $table_name ) {
        global $wpdb;

        $result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        return (string) $result === (string) $table_name;
    }
}

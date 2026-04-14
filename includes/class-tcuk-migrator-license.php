<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator_License {
    const SETTINGS_OPTION = 'tcuk_aio_migrator_settings';
    const DEFAULT_API_URL = 'https://licenses.aiomigrator.com/verify';

    public function get_api_url() {
        $url = (string) apply_filters( 'tcuk_migrator_license_api_url', self::DEFAULT_API_URL );
        return esc_url_raw( trim( $url ) );
    }

    public function get_status() {
        $settings = get_option( self::SETTINGS_OPTION, array() );

        $license_key = trim( (string) ( $settings['premium_license_key'] ?? '' ) );
        $api_url     = $this->get_api_url();

        if ( '' === $license_key || '' === $api_url ) {
            return array(
                'active'  => false,
                'message' => 'Premium inactive: add License Key.',
            );
        }

        $response = wp_remote_post(
            $api_url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'license_key' => $license_key,
                        'site_url'    => home_url(),
                        'plugin'      => 'tcuk-all-in-one-migrator',
                        'version'     => defined( 'TCUK_MIGRATOR_VERSION' ) ? TCUK_MIGRATOR_VERSION : '',
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'active'  => false,
                'message' => 'Premium inactive: license API request failed.',
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $http_code < 200 || $http_code >= 300 ) {
            return array(
                'active'  => false,
                'message' => 'Premium inactive: license API returned HTTP ' . $http_code . '.',
            );
        }

        $decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) ) {
            return array(
                'active'  => false,
                'message' => 'Premium inactive: invalid license API response.',
            );
        }

        $is_active = false;

        if ( isset( $decoded['active'] ) ) {
            $is_active = (bool) $decoded['active'];
        } elseif ( isset( $decoded['success'] ) ) {
            $is_active = (bool) $decoded['success'];
        } elseif ( isset( $decoded['status'] ) ) {
            $is_active = 'active' === strtolower( (string) $decoded['status'] );
        }

        $message = trim( (string) ( $decoded['message'] ?? '' ) );

        if ( $is_active ) {
            return array(
                'active'  => true,
                'message' => '' !== $message ? $message : 'Premium active.',
            );
        }

        return array(
            'active'  => false,
            'message' => '' !== $message ? $message : 'Premium inactive: license is not active for this site.',
        );
    }

    public function is_premium_active() {
        $status = $this->get_status();
        return ! empty( $status['active'] );
    }

    public function assert_premium_or_throw( $feature_label = 'This feature' ) {
        $status = $this->get_status();

        if ( ! empty( $status['active'] ) ) {
            return;
        }

        $message = trim( (string) ( $status['message'] ?? '' ) );
        if ( '' === $message ) {
            $message = 'Premium license required.';
        }

        throw new RuntimeException( $feature_label . ' requires an active premium license. ' . $message );
    }

    public function enforce_theme_only_components( $components ) {
        $components = array_values( array_unique( array_map( 'sanitize_text_field', (array) $components ) ) );

        if ( $this->is_premium_active() ) {
            return $components;
        }

        if ( in_array( 'theme', $components, true ) ) {
            return array( 'theme' );
        }

        return array();
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MPAP_Crypto {
    private static function key() {
        return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
    }

    public static function encrypt( $value ) {
        $value = (string) $value;
        if ( '' === $value ) {
            return '';
        }
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $value );
        }
        $iv     = random_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
        if ( false === $cipher ) {
            return '';
        }
        return base64_encode( $iv . $cipher );
    }

    public static function decrypt( $payload ) {
        $payload = (string) $payload;
        if ( '' === $payload ) {
            return '';
        }
        $raw = base64_decode( $payload, true );
        if ( false === $raw ) {
            return '';
        }
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return $raw;
        }
        if ( strlen( $raw ) < 17 ) {
            return '';
        }
        $iv     = substr( $raw, 0, 16 );
        $cipher = substr( $raw, 16 );
        $plain  = openssl_decrypt( $cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
        return false === $plain ? '' : $plain;
    }

    public static function encrypt_json( array $value ) {
        return self::encrypt( wp_json_encode( $value ) );
    }

    public static function decrypt_json( $payload ) {
        $json = self::decrypt( $payload );
        $data = $json ? json_decode( $json, true ) : array();
        return is_array( $data ) ? $data : array();
    }
}

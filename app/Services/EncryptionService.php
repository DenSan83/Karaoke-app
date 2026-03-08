<?php

class EncryptionService {
    // A simple secret key for the party. In a real app, this would be in a config file.
    private static $key = 'karaoke_secret_2026'; 
    private static $method = 'aes-128-cbc';

    /**
     * Encrypt a string/data into a URL-safe code
     */
    public static function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt(json_encode($data), self::$method, self::$key, 0, $iv);
        
        // Combine IV and encrypted data, then base64 for URL
        $combined = base64_encode($iv . $encrypted);
        return str_replace(['+', '/', '='], ['-', '_', ''], $combined);
    }

    /**
     * Decrypt a code back into the original data
     */
    public static function decrypt($code) {
        // Re-apply base64 padding
        $base64 = str_replace(['-', '_'], ['+', '/'], $code);
        $rem = strlen($base64) % 4;
        if ($rem) {
            $base64 .= str_repeat('=', 4 - $rem);
        }
        $data = base64_decode($base64);
        
        $iv_len = openssl_cipher_iv_length(self::$method);
        $iv = substr($data, 0, $iv_len);
        $encrypted = substr($data, $iv_len);
        
        $decrypted = openssl_decrypt($encrypted, self::$method, self::$key, 0, $iv);
        return json_decode($decrypted, true);
    }
}

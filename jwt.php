<?php
// jwt.php

// Secret key used for signing JWTs
define('JWT_SECRET', 'vyala_task_pad_super_secret_key_130555_session');

/**
 * Base64URL encode function
 */
function base64url_encode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Base64URL decode function
 */
function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}

/**
 * Generate a JWT token signed with HMAC SHA-256
 */
function generate_jwt($payload, $secret = JWT_SECRET, $expiry_seconds = 86400) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    
    if (!isset($payload['iat'])) {
        $payload['iat'] = time();
    }
    if (!isset($payload['exp'])) {
        $payload['exp'] = time() + $expiry_seconds;
    }
    
    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode(json_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64url_encode($signature);
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Verify a JWT token's signature and expiration
 */
function verify_jwt($token, $secret = JWT_SECRET) {
    if (empty($token)) {
        return false;
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
    
    $signature = base64url_decode($base64UrlSignature);
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    
    if (!hash_equals($signature, $expectedSignature)) {
        return false;
    }
    
    $payload = json_decode(base64url_decode($base64UrlPayload), true);
    
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false; // Expired
    }
    
    return $payload;
}


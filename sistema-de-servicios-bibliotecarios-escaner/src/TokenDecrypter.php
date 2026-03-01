<?php

class TokenDecrypter
{
    private static $key = 'ahgsdahske9qwk';
    private static $iv = 'Devjoker7.37hAES';
    private static $method = 'AES-128-CBC';

    /**
     * Decrypts the given token.
     * Returns the decrypted string (matricula) or null if decryption fails.
     */
    public static function decrypt($encryptedToken)
    {
        try {
            $inputToken = trim($encryptedToken);

            // 1. Check if the input is directly a raw JWT token from the QR (starts with 'eyJ')
            if (strpos($inputToken, 'eyJ') === 0) {
                return self::extractFromJwt($inputToken);
            }

            // 2. Not a direct JWT, maybe it's AES encrypted (legacy/manual base64)? Check if base64 format
            if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $inputToken)) {
                $decrypted = openssl_decrypt(
                    $inputToken,
                    self::$method,
                    self::$key,
                    0,
                    self::$iv
                );

                if ($decrypted !== false) {
                    $resultado = trim($decrypted);

                    // The decrypted payload might be a JWT itself
                    if (strpos($resultado, 'eyJ') === 0) {
                        return self::extractFromJwt($resultado);
                    }

                    // Or just a raw string
                    return $resultado;
                }
            }

            // 3. Fallback: maybe just a raw plain-text matricula scan
            return $inputToken;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function extractFromJwt($jwtString)
    {
        $partes = explode('.', $jwtString);
        if (count($partes) === 3) {
            $payloadBase64Url = $partes[1];
            // Normalize Base64Url to standard Base64
            $payloadBase64 = str_replace(['-', '_'], ['+', '/'], $payloadBase64Url);

            // Add padding if missing (crucial for base64_decode)
            $pad = strlen($payloadBase64) % 4;
            if ($pad) {
                $payloadBase64 .= str_repeat('=', 4 - $pad);
            }

            $payloadJson = base64_decode($payloadBase64);

            if ($payloadJson) {
                $payload = json_decode($payloadJson, true);
                if ($payload) {
                    // Validar expiración si existe (exp)
                    if (isset($payload['exp']) && time() > $payload['exp']) {

                        return null;
                    }

                    if (isset($payload['mat']))
                        return $payload['mat'];
                    if (isset($payload['matricula']))
                        return $payload['matricula'];
                    if (isset($payload['numero_empleado']))
                        return $payload['numero_empleado'];
                    if (isset($payload['emp']))
                        return $payload['emp'];
                }
            }
        }
        return null; // Invalid JWT structure
    }
}

<?php
/**
 * JWT mínimo, en PHP puro (sin composer/librerías externas), firmado con
 * HMAC-SHA256. Sigue el estándar (header.payload.signature en Base64URL),
 * así que cualquier librería estándar de JWT en otro lenguaje también
 * podría leerlo -- no es un formato inventado, es el mismo formato real.
 *
 * Uso:
 *   $token = jwt_encode(['sub' => 5, 'role' => 'admin'], $secret, 3600);
 *   $payload = jwt_decode($token, $secret); // lanza excepción si es inválido/expiró
 */

class JwtException extends \Exception {}

function jwt_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode(string $data): string
{
    $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
    return base64_decode(strtr($padded, '-_', '+/'));
}

/**
 * Genera un JWT firmado. $claims es el contenido (ej. id de usuario, rol).
 * $ttlSeconds es cuánto dura el token antes de expirar.
 */
function jwt_encode(array $claims, string $secret, int $ttlSeconds = 3600): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload = array_merge($claims, [
        'iat' => $now,
        'exp' => $now + $ttlSeconds,
    ]);

    $headerEncoded = jwt_base64url_encode(json_encode($header));
    $payloadEncoded = jwt_base64url_encode(json_encode($payload));

    $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true);
    $signatureEncoded = jwt_base64url_encode($signature);

    return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
}

/**
 * Verifica la firma y expiración de un JWT, y devuelve sus claims.
 * Lanza JwtException si el token está mal formado, la firma no coincide
 * (fue alterado o firmado con otro secreto), o ya expiró.
 */
function jwt_decode(string $token, string $secret): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new JwtException('Token con formato inválido');
    }
    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    $expectedSignature = jwt_base64url_encode(
        hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true)
    );

    // hash_equals evita ataques de "timing" al comparar la firma.
    if (!hash_equals($expectedSignature, $signatureEncoded)) {
        throw new JwtException('Firma inválida: el token fue alterado o no fue emitido por este servidor');
    }

    $payload = json_decode(jwt_base64url_decode($payloadEncoded), true);
    if (!is_array($payload)) {
        throw new JwtException('Payload del token no es JSON válido');
    }

    if (!isset($payload['exp']) || time() > $payload['exp']) {
        throw new JwtException('Token expirado');
    }

    return $payload;
}

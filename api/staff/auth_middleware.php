<?php
require_once __DIR__ . '/../../lib/Jwt.php';
require_once __DIR__ . '/../../lib/MaintenanceReports.php';

function staff_jwt_secret(): string
{
    $path = __DIR__ . '/jwt_secret.php';
    if (!file_exists($path)) {
        json_response(['error' => 'Falta api/staff/jwt_secret.php. Copia jwt_secret.example.php, renómbralo, y pon un secreto aleatorio.'], 500);
    }
    return require $path;
}

/**
 * Exige un JWT válido en el header "Authorization: Bearer <token>".
 * Devuelve el payload del token (incluye 'sub' = id de usuario, 'role').
 * Si no hay token o es inválido/expirado, corta la ejecución con 401.
 */
function staff_require_auth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        json_response(['error' => 'Falta el token de autenticación'], 401);
    }

    try {
        return jwt_decode($m[1], staff_jwt_secret());
    } catch (JwtException $e) {
        json_response(['error' => 'Token inválido: ' . $e->getMessage()], 401);
    }
}

/** Exige, además de estar autenticado, que el rol tenga permiso para $action. */
function staff_require_permission(string $action): array
{
    $payload = staff_require_auth();
    if (!mr_can($payload['role'] ?? '', $action)) {
        json_response(['error' => 'No tienes permiso para esta acción'], 403);
    }
    return $payload;
}

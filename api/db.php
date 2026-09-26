<?php
/**
 * Conexión a la base de datos.
 *
 * ESTE ARCHIVO YA NO TIENE CREDENCIALES — no lo edites. Las credenciales
 * reales viven en db.config.php (un archivo separado que tú creas una sola
 * vez a partir de db.config.example.php, y que nunca se vuelve a sobrescribir).
 *
 * Si ves el error "No se encontró db.config.php", es porque aún no lo has
 * creado: copia db.config.example.php, renómbralo a db.config.php, y edita
 * esa copia con tus credenciales reales.
 */

// Activa el buffer de salida desde el inicio de cada script de la API. Así,
// si algo (un Warning/Notice de PHP, un mensaje de una librería, etc.) se
// imprime por accidente antes de json_response(), queda atrapado en este
// buffer en vez de mezclarse con la respuesta JSON y corromperla.
if (!ob_get_level()) {
    ob_start();
}

$__db_config_path = __DIR__ . '/db.config.php';
if (!file_exists($__db_config_path)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Falta el archivo api/db.config.php. Copia api/db.config.example.php, renómbralo a db.config.php, y pon ahí tus credenciales reales de MySQL.',
    ]);
    exit;
}
$__db_config = require $__db_config_path;

function get_db(): PDO {
    global $__db_config;
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$__db_config['host']};dbname={$__db_config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $__db_config['user'], $__db_config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Encabezados comunes para las respuestas de la API (JSON + CORS básico
// para que el widget funcione aunque se sirva desde una ruta distinta).
function json_response($data, int $status = 200): void {
    // Descarta cualquier cosa que se haya impreso por accidente antes de
    // este punto (warnings de mail(), notices, etc.) para que la respuesta
    // sea SIEMPRE JSON puro y nunca falle el parseo en el navegador.
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

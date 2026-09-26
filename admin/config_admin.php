<?php
/**
 * Contraseña de acceso al panel de administración (/admin/).
 *
 * IMPORTANTE: cambia esta contraseña antes de usarlo.
 * Genera un nuevo hash corriendo esto una vez:
 *   php -r "echo password_hash('TU_CONTRASEÑA_NUEVA', PASSWORD_DEFAULT);"
 * y pega el resultado abajo.
 *
 * La contraseña de ejemplo ahora mismo es: habitaraAdmin2026
 * (es independiente de la de /reportes/ — puedes usar la misma si prefieres
 * que tu equipo solo recuerde una, o dejarlas distintas)
 */
define('ADMIN_PASSWORD_HASH', '$2y$10$ffJbDuIK9hLHAqvMeekRTOpOEL782fnENNORa58NcV1NHpG6f4l96');

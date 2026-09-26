<?php
/**
 * Contraseña de acceso al panel de reportes (/reportes/).
 *
 * IMPORTANTE: cambia esta contraseña antes de usarlo.
 * 1) Elige una contraseña nueva.
 * 2) Genera su hash corriendo esto UNA VEZ en tu terminal (o en un script temporal):
 *      php -r "echo password_hash('TU_CONTRASEÑA_NUEVA', PASSWORD_DEFAULT);"
 * 3) Copia el resultado (empieza con $2y$...) y pégalo abajo, reemplazando el valor actual.
 *
 * La contraseña de ejemplo ahora mismo es: habitara2026
 * (CÁMBIALA antes de subir esto a producción)
 */
define('REPORTES_PASSWORD_HASH', '$2y$10$6dUclu5zhZwn2UhZVfyBC.mjKQxPPmjsj/q4mBJZisJ3.Wzhip8y6');

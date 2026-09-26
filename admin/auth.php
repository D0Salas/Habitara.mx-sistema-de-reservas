<?php
// Sesión de 60 días: una vez que inicien sesión en su dispositivo, no tienen
// que volver a escribir la contraseña por dos meses (a menos que borren las
// cookies del navegador o cierren sesión manualmente).
session_set_cookie_params(60 * 24 * 60 * 60);
session_start();

function require_admin_login(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

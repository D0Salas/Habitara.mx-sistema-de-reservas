<?php
/**
 * PLANTILLA del secreto para firmar JWT.
 *
 * Igual que con db.config.php: copia este archivo, renómbralo a
 * jwt_secret.php, y cambia el valor por algo largo y aleatorio (no
 * el de ejemplo). Este archivo NO se vuelve a subir en actualizaciones
 * futuras, así que tu secreto real nunca se sobrescribe.
 *
 * Puedes generar uno aleatorio corriendo:
 *   php -r "echo bin2hex(random_bytes(32));"
 */
return 'CAMBIA_ESTE_SECRETO_POR_ALGO_ALEATORIO_Y_LARGO';

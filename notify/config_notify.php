<?php
/**
 * Configuración de notificaciones por correo.
 * Edita estos valores antes de subir a producción.
 */

// A quién le llegan los avisos de nueva reserva. Puedes poner varios.
const NOTIFY_TO = [
    'duenos@habitara.mx',   // <-- cámbialo por el correo real de los dueños
];

// Remitente. Idealmente crea este buzón real en hPanel > Correo electrónico
// (aunque nadie lo revise) — mejora mucho que el correo no caiga en spam,
// porque coincide con tu propio dominio en vez de un remitente genérico.
const NOTIFY_FROM = 'reservas@habitara.mx';
const NOTIFY_FROM_NAME = 'Habitara — Reservas';

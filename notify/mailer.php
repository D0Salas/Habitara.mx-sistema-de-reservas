<?php
require_once __DIR__ . '/config_notify.php';

const ORIGEN_LABEL = ['directo' => 'Directo (sitio web)', 'airbnb' => 'Airbnb', 'booking' => 'Booking.com'];

/**
 * Envía el correo de aviso de nueva reserva a los dueños.
 * No lanza excepciones: si el correo falla, se registra en el log de PHP
 * pero NO interrumpe el flujo de la reserva (la reserva ya quedó guardada
 * en la base de datos, que es lo importante; el aviso es secundario).
 */
function notify_new_reservation(array $r): void
{
    $status = $r['status'] ?? 'confirmada';
    $emoji = $status === 'pendiente' ? '⏳' : '🛎️';
    $etiqueta = $status === 'pendiente' ? 'Nueva reserva PENDIENTE de confirmar' : 'Nueva reserva confirmada';
    $subject = $emoji . ' ' . $etiqueta . ' — ' . ($r['property_name'] ?? '') . ' / ' . ($r['room_name'] ?? '');

    $origen = ORIGEN_LABEL[$r['source']] ?? $r['source'];
    $total  = isset($r['total_price']) && $r['total_price'] > 0
        ? '$' . number_format((float)$r['total_price'], 2) . ' MXN'
        : 'No disponible (reserva de plataforma externa)';

    $avisoPendiente = $status === 'pendiente'
        ? "\nYa se bloquearon estas fechas en el calendario. Entra al panel de Administración para marcarla como confirmada (si el huésped se queda) o cancelarla (si no) -- eso sí libera las fechas.\n"
        : '';

    $body = "Se registró una nueva reserva ({$etiqueta}):\n"
        . $avisoPendiente . "\n"
        . "Propiedad:  " . ($r['property_name'] ?? '') . "\n"
        . "Cuarto:     " . ($r['room_name'] ?? '') . "\n"
        . "Huésped:    " . ($r['guest_name'] ?? '') . "\n"
        . "Teléfono:   " . ($r['guest_phone'] ?? '(no proporcionado)') . "\n"
        . "Correo:     " . ($r['guest_email'] ?? '(no proporcionado)') . "\n"
        . "Entrada:    " . $r['check_in'] . "\n"
        . "Salida:     " . $r['check_out'] . "\n"
        . "Noches:     " . $r['nights'] . "\n"
        . "Origen:     " . $origen . "\n"
        . "Total:      " . $total . "\n\n"
        . "— Enviado automáticamente por el sistema de reservas de Habitara.";

    $headers = [
        'From: ' . NOTIFY_FROM_NAME . ' <' . NOTIFY_FROM . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    foreach (NOTIFY_TO as $to) {
        try {
            $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
            if (!$ok) {
                error_log("[Habitara] No se pudo enviar el correo de notificación a {$to}");
            }
        } catch (\Throwable $e) {
            error_log('[Habitara] Error enviando notificación: ' . $e->getMessage());
        }
    }
}

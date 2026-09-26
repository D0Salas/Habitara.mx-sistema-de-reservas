<?php
/**
 * GET /api/ical_export.php?room_id=qroo-cuarto-1
 *
 * Genera un archivo .ics con las reservas que YA BLOQUEAN fechas para
 * ese cuarto: 'pendiente' Y 'confirmada' (ambas ocupan el calendario
 * desde el momento en que se crean -- 'pendiente' es solo un estado
 * de registro interno, no significa "sin bloquear"). Se excluyen
 * únicamente las 'cancelada'.
 * Copia esta URL y pégala en:
 *   Airbnb  -> Calendario > Disponibilidad > Sincronización de calendarios > Importar calendario
 *   Booking.com -> Extranet > Calendario y precios > Sincronización de calendarios
 * Así, cuando alguien reserve directo contigo (confirmada o aún pendiente
 * de revisar), esas fechas se bloquean automáticamente también en
 * Airbnb y Booking -- no hay que esperar a confirmarla primero.
 */
require_once __DIR__ . '/db.php';

$room_id = $_GET['room_id'] ?? '';
if (!$room_id) {
    http_response_code(400);
    echo 'Falta room_id';
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare(
    "SELECT id, check_in, check_out, guest_name, source
     FROM reservations
     WHERE room_id = :room_id AND status IN ('pendiente', 'confirmada')
     ORDER BY check_in"
);
$stmt->execute(['room_id' => $room_id]);
$reservations = $stmt->fetchAll();

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="habitara-' . $room_id . '.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Habitara//Booking//ES\r\n";
echo "CALSCALE:GREGORIAN\r\n";

foreach ($reservations as $r) {
    $uid = 'habitara-' . $r['id'] . '@habitara.mx';
    $dtStart = str_replace('-', '', $r['check_in']);
    $dtEnd   = str_replace('-', '', $r['check_out']);
    // No exponemos el nombre del huésped si vino de otra plataforma (evita datos duplicados/confusos).
    $summary = $r['source'] === 'directo' ? 'Reservado - Habitara' : 'No disponible';

    echo "BEGIN:VEVENT\r\n";
    echo "UID:{$uid}\r\n";
    echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    echo "DTSTART;VALUE=DATE:{$dtStart}\r\n";
    echo "DTEND;VALUE=DATE:{$dtEnd}\r\n";
    echo "SUMMARY:{$summary}\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";

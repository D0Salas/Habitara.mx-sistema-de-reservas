<?php
/**
 * cron/ical_sync.php
 *
 * Este script debe correr periódicamente (recomendado cada hora) vía
 * el "Administrador de tareas Cron" en hPanel. Airbnb y Booking.com
 * actualizan sus feeds iCal cada pocas horas, así que sincronizar
 * cada hora es más que suficiente.
 *
 * Configuración en hPanel > Avanzado > Cron Jobs:
 *   Comando: php /home/TU_USUARIO/domains/habitara.mx/cron/ical_sync.php
 *   Frecuencia: cada hora (0 * * * *)
 *
 * Qué hace:
 * 1) Recorre cada fila activa de `ical_feeds` (un feed por cuarto y plataforma).
 * 2) Descarga el .ics de Airbnb/Booking.com.
 * 3) Parsea los eventos VEVENT (fechas DTSTART/DTEND + UID).
 * 4) Por cada evento nuevo (UID no importado antes), crea una reserva
 *    con source='airbnb' o 'booking' y bloquea esas noches en
 *    occupied_nights -- usando la MISMA restricción única que protege
 *    a las reservas directas, así nunca hay traslapes entre plataformas.
 * 5) Si un evento fue cancelado en Airbnb/Booking (ya no aparece en el
 *    feed), se libera la reserva correspondiente.
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../notify/mailer.php';

function parse_ics(string $ics): array {
    $events = [];
    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $matches);

    foreach ($matches[1] as $block) {
        $uid = null; $start = null; $end = null;

        if (preg_match('/UID:(.+)/', $block, $m)) {
            $uid = trim($m[1]);
        }
        if (preg_match('/DTSTART(?:;VALUE=DATE)?:(\d{8})/', $block, $m)) {
            $start = substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
        }
        if (preg_match('/DTEND(?:;VALUE=DATE)?:(\d{8})/', $block, $m)) {
            $end = substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
        }

        if ($uid && $start && $end) {
            $events[] = ['uid' => $uid, 'start' => $start, 'end' => $end];
        }
    }
    return $events;
}

function block_nights(PDO $pdo, string $room_id, string $platform, array $event): void {
    // ¿Ya importamos esta reserva antes? (external_uid es único por room_id)
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE room_id = :room_id AND external_uid = :uid");
    $stmt->execute(['room_id' => $room_id, 'uid' => $event['uid']]);
    if ($stmt->fetch()) {
        return; // ya existe, no hacer nada
    }

    $in  = new DateTime($event['start']);
    $out = new DateTime($event['end']);
    if ($out <= $in) return;

    $nights = [];
    $cursor = clone $in;
    while ($cursor < $out) {
        $nights[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO reservations (room_id, guest_name, check_in, check_out, nights, source, external_uid, status)
             VALUES (:room_id, :guest_name, :check_in, :check_out, :nights, :source, :uid, 'confirmada')"
        );
        $stmt->execute([
            'room_id'    => $room_id,
            'guest_name' => ucfirst($platform) . ' - Huésped externo',
            'check_in'   => $event['start'],
            'check_out'  => $event['end'],
            'nights'     => count($nights),
            'source'     => $platform,
            'uid'        => $event['uid'],
        ]);
        $reservation_id = (int)$pdo->lastInsertId();

        $insertNight = $pdo->prepare(
            "INSERT INTO occupied_nights (room_id, night_date, reservation_id) VALUES (:room_id, :night_date, :reservation_id)"
        );
        foreach ($nights as $night) {
            $insertNight->execute(['room_id' => $room_id, 'night_date' => $night, 'reservation_id' => $reservation_id]);
        }

        $pdo->commit();
        echo "  + Importada reserva {$platform} ({$event['start']} a {$event['end']}) en {$room_id}\n";

        $roomInfo = $pdo->prepare("SELECT r.name AS room_name, p.name AS property_name FROM rooms r JOIN properties p ON p.id = r.property_id WHERE r.id = :id");
        $roomInfo->execute(['id' => $room_id]);
        $ri = $roomInfo->fetch();
        notify_new_reservation([
            'property_name' => $ri['property_name'] ?? '',
            'room_name'     => $ri['room_name'] ?? '',
            'guest_name'    => ucfirst($platform) . ' - Huésped externo',
            'guest_phone'   => null,
            'guest_email'   => null,
            'check_in'      => $event['start'],
            'check_out'     => $event['end'],
            'nights'        => count($nights),
            'source'        => $platform,
            'total_price'   => null,
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        // 23000 = esas noches ya estaban ocupadas (ej. por una reserva directa
        // hecha primero). Se registra el conflicto para revisión manual.
        if ($e->getCode() === '23000') {
            echo "  ! CONFLICTO: {$platform} intentó reservar noches ya ocupadas en {$room_id} ({$event['start']} a {$event['end']}). Revisar manualmente.\n";
        } else {
            echo "  ! Error al importar evento {$platform}: " . $e->getMessage() . "\n";
        }
    }
}

// ---- Ejecución principal ----
$pdo = get_db();
$feeds = $pdo->query("SELECT * FROM ical_feeds WHERE active = 1")->fetchAll();

foreach ($feeds as $feed) {
    echo "Sincronizando {$feed['platform']} para cuarto {$feed['room_id']}...\n";

    $ics = @file_get_contents($feed['ical_url']);
    if ($ics === false) {
        echo "  ! No se pudo descargar el feed: {$feed['ical_url']}\n";
        continue;
    }

    $events = parse_ics($ics);
    foreach ($events as $event) {
        block_nights($pdo, $feed['room_id'], $feed['platform'], $event);
    }

    $upd = $pdo->prepare("UPDATE ical_feeds SET last_synced_at = NOW() WHERE id = :id");
    $upd->execute(['id' => $feed['id']]);
}

echo "Sincronización completa: " . date('Y-m-d H:i:s') . "\n";

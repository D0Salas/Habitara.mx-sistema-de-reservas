<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../lib/MinimalXlsxWriter.php';

const MESES_ES = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

/**
 * Genera el archivo .xlsx de una propiedad (todas sus reservas confirmadas,
 * una hoja de resumen + una hoja por mes) y lo guarda en reportes/files/.
 * Devuelve la ruta del archivo generado.
 */
function generate_property_report(string $property_id): string
{
    $pdo = get_db();

    $stmt = $pdo->prepare(
        "SELECT r.name AS room_name, res.guest_name, res.guest_phone, res.check_in, res.check_out,
                res.nights, res.source, res.total_price, res.status
         FROM reservations res
         JOIN rooms r ON r.id = res.room_id
         WHERE r.property_id = :property_id AND res.status = 'confirmada'
         ORDER BY res.check_in"
    );
    $stmt->execute(['property_id' => $property_id]);
    $reservations = $stmt->fetchAll();

    // Agrupar por mes (según fecha de entrada)
    $byMonth = [];      // '2026-10' => [rows...]
    $summary = [];      // '2026-10' => ['nights' => N, 'revenue' => N, 'directo' => N, 'airbnb' => N, 'booking' => N]

    foreach ($reservations as $res) {
        $key = substr($res['check_in'], 0, 7); // YYYY-MM
        $byMonth[$key][] = $res;

        if (!isset($summary[$key])) {
            $summary[$key] = ['nights' => 0, 'revenue' => 0, 'directo' => 0, 'airbnb' => 0, 'booking' => 0];
        }
        $summary[$key]['nights']  += (int)$res['nights'];
        $summary[$key]['revenue'] += (float)$res['total_price'];
        $summary[$key][$res['source']] += 1;
    }

    ksort($byMonth);
    ksort($summary);

    $xlsx = new MinimalXlsxWriter();

    // --- Hoja de resumen ---
    $summaryHeaders = ['Mes', 'Noches vendidas', 'Ingresos (MXN)', 'Reservas directas', 'Reservas Airbnb', 'Reservas Booking.com'];
    $summaryRows = [];
    foreach ($summary as $key => $s) {
        [$year, $month] = explode('-', $key);
        $label = MESES_ES[(int)$month] . ' ' . $year;
        $summaryRows[] = [$label, $s['nights'], round($s['revenue'], 2), $s['directo'], $s['airbnb'], $s['booking']];
    }
    $xlsx->addSheet('Resumen', $summaryHeaders, $summaryRows);

    // --- Una hoja por mes con el detalle ---
    $detailHeaders = ['Cuarto', 'Huésped', 'Teléfono', 'Entrada', 'Salida', 'Noches', 'Origen', 'Total (MXN)'];
    $origenLabel = ['directo' => 'Directo', 'airbnb' => 'Airbnb', 'booking' => 'Booking.com'];

    foreach ($byMonth as $key => $rows) {
        [$year, $month] = explode('-', $key);
        $sheetName = MESES_ES[(int)$month] . ' ' . $year;

        $detailRows = [];
        foreach ($rows as $res) {
            $detailRows[] = [
                $res['room_name'],
                $res['guest_name'],
                $res['guest_phone'] ?: '',
                $res['check_in'],
                $res['check_out'],
                $res['nights'],
                $origenLabel[$res['source']] ?? $res['source'],
                round((float)$res['total_price'], 2),
            ];
        }
        $xlsx->addSheet($sheetName, $detailHeaders, $detailRows);
    }

    if (empty($byMonth)) {
        // Sin reservas todavía: deja al menos una hoja vacía con encabezados, para que el archivo no quede en blanco.
        $xlsx->addSheet('Sin reservas', $detailHeaders, []);
    }

    $outDir = __DIR__ . '/files';
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }
    $path = $outDir . '/' . preg_replace('/[^a-z0-9\-]/', '', $property_id) . '.xlsx';
    $xlsx->save($path);

    return $path;
}

/** Genera el reporte de todas las propiedades activas. Usado por el cron. */
function generate_all_reports(): array
{
    $pdo = get_db();
    $properties = $pdo->query("SELECT id FROM properties")->fetchAll();
    $generated = [];
    foreach ($properties as $p) {
        $generated[] = generate_property_report($p['id']);
    }
    return $generated;
}

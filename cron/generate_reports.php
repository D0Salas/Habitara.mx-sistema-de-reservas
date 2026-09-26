<?php
/**
 * cron/generate_reports.php
 *
 * Regenera el Excel de cada propiedad automáticamente.
 * Configúralo en hPanel > Avanzado > Cron Jobs:
 *   Comando: php /home/TU_USUARIO/domains/habitara.mx/public_html/cron/generate_reports.php
 *   Frecuencia recomendada: una vez al día (ej. 2:00 AM) -> 0 2 * * *
 */
require_once __DIR__ . '/../reportes/generate_report.php';

$paths = generate_all_reports();
foreach ($paths as $p) {
    echo "Generado: {$p}\n";
}
echo "Listo: " . date('Y-m-d H:i:s') . "\n";

<?php
/**
 * Verifica que la cobertura de líneas del reporte Clover (coverage.xml)
 * sea igual o mayor al umbral pedido. Se usa en el pipeline de CI para
 * hacer fallar el paso si la cobertura del módulo cae por debajo de lo
 * requerido (la rúbrica pide ≥80%).
 *
 * Uso: php check_coverage.php coverage.xml 80
 */
$path = $argv[1] ?? 'coverage.xml';
$threshold = (float)($argv[2] ?? 80);

if (!file_exists($path)) {
    fwrite(STDERR, "No se encontró el archivo de cobertura: {$path}\n");
    exit(1);
}

$xml = simplexml_load_file($path);
$projectMetrics = $xml->xpath('/coverage/project/metrics');

if (!$projectMetrics) {
    fwrite(STDERR, "No se encontraron métricas de proyecto en el reporte de cobertura\n");
    exit(1);
}

$m = $projectMetrics[0];
$total = (int)$m['statements'];
$covered = (int)$m['coveredstatements'];
$pct = $total > 0 ? ($covered / $total) * 100 : 0;

printf("Cobertura de líneas: %.2f%% (%d de %d líneas ejecutables)\n", $pct, $covered, $total);
printf("Umbral requerido: %.2f%%\n", $threshold);

if ($pct < $threshold) {
    fwrite(STDERR, "❌ La cobertura está por debajo del umbral requerido.\n");
    exit(1);
}

echo "✅ Cobertura suficiente.\n";
exit(0);

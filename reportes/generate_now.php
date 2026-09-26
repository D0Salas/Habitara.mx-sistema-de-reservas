<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/generate_report.php';

$property_id = $_POST['property_id'] ?? '';
if ($property_id) {
    generate_property_report($property_id);
}
header('Location: index.php');
exit;

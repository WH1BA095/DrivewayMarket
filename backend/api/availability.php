<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
if (empty($ids)) { echo json_encode([]); exit; }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$st = Database::getInstance()->getConnection()
    ->prepare("SELECT id, quantity - reserved AS available FROM products WHERE id IN ($placeholders)");
$st->execute($ids);

$result = [];
foreach ($st->fetchAll() as $row) {
    $result[(int)$row['id']] = max(0, (int)$row['available']);
}
echo json_encode($result);

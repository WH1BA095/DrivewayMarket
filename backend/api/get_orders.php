<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'orders' => []]);
    exit;
}

$db  = Database::getInstance()->getConnection();
$uid = (int)$_SESSION['user_id'];

$tables = $db->query("SHOW TABLES LIKE 'orders'")->fetchAll();
if (empty($tables)) {
    echo json_encode(['success' => true, 'orders' => []]);
    exit;
}

$st = $db->prepare("
    SELECT o.id, o.user_order_number, o.status, o.total, o.address, o.comment, o.created_at,
           GROUP_CONCAT(oi.name ORDER BY oi.id SEPARATOR ', ') AS items_summary,
           COUNT(oi.id) AS item_count, SUM(oi.quantity) AS total_qty
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.user_order_number DESC
");
$st->execute([$uid]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as &$o) {
    $itemsSt = $db->prepare('SELECT name, article, price, quantity FROM order_items WHERE order_id=?');
    $itemsSt->execute([$o['id']]);
    $rawItems = $itemsSt->fetchAll(PDO::FETCH_ASSOC);

    $o['items'] = array_map(function($i) {
        return [
            'name'     => $i['name'],
            'article'  => $i['article'],
            'price'    => (float)$i['price'],
            'qty'      => (int)$i['quantity'],
        ];
    }, $rawItems);

    $o['total']             = (float)$o['total'];
    $o['item_count']        = (int)$o['item_count'];
    $o['id']                = (int)$o['id'];
    $o['user_order_number'] = (int)($o['user_order_number'] ?? $o['id']);
    $o['source']            = 'db';
    unset($o['items_summary'], $o['total_qty']);
}

echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);

<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

function jsonOk(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'reason' => 'not_logged_in']); exit;
}

$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);
$orders = $data['orders'] ?? [];

if (empty($orders)) {
    jsonOk(['synced' => 0]);
}

$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

$db->exec("CREATE TABLE IF NOT EXISTS `orders` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `status`     ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
    `total`      DECIMAL(10,2) NOT NULL DEFAULT 0,
    `address`    VARCHAR(500) NOT NULL DEFAULT '',
    `comment`    TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS `order_items` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`   INT NOT NULL,
    `product_id` INT,
    `name`       VARCHAR(255) NOT NULL,
    `article`    VARCHAR(100) NOT NULL DEFAULT '',
    `price`      DECIMAL(10,2) NOT NULL,
    `quantity`   INT NOT NULL DEFAULT 1,
    KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// дедупликация по user_id + total + день
$existingSt = $db->prepare(
    "SELECT total, DATE(created_at) AS day FROM orders WHERE user_id=?"
);
$existingSt->execute([$userId]);
$existing = [];
foreach ($existingSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $key = $row['total'] . '|' . $row['day'];
    $existing[$key] = true;
}

$synced = 0;
foreach ($orders as $order) {
    $items    = $order['items'] ?? [];
    $total    = (float)($order['total'] ?? 0);
    $address  = '';
    if (!empty($order['delivery']['address'])) {
        $address = trim($order['delivery']['address']);
        if (!empty($order['delivery']['apt'])) $address .= ', кв. ' . $order['delivery']['apt'];
    }
    $comment  = trim($order['comment'] ?? '');
    $status   = $order['status'] ?? 'pending';
    $validStatuses = ['pending','confirmed','shipped','delivered','cancelled'];
    if (!in_array($status, $validStatuses)) $status = 'pending';

    $createdAt = null;
    if (!empty($order['datetime'])) {
        try {
            $dt = new DateTime($order['datetime']);
            $createdAt = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {}
    } elseif (!empty($order['date'])) {
        // формат "01.06.2025" из localStorage
        $parts = explode('.', $order['date']);
        if (count($parts) === 3) {
            $createdAt = $parts[2] . '-' . $parts[1] . '-' . $parts[0] . ' 00:00:00';
        }
    }
    if (!$createdAt) $createdAt = date('Y-m-d H:i:s');

    $day = substr($createdAt, 0, 10);
    $key = number_format($total, 2, '.', '') . '|' . $day;
    if (isset($existing[$key])) continue;

    $db->beginTransaction();
    try {
        $st = $db->prepare(
            "INSERT INTO orders (user_id, status, total, address, comment, created_at) VALUES (?,?,?,?,?,?)"
        );
        $st->execute([$userId, $status, $total, $address, $comment, $createdAt]);
        $orderId = (int)$db->lastInsertId();

        $iSt = $db->prepare(
            "INSERT INTO order_items (order_id, product_id, name, article, price, quantity) VALUES (?,?,?,?,?,?)"
        );
        foreach ($items as $item) {
            $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
            $iSt->execute([
                $orderId,
                (int)($item['id'] ?? $item['product_id'] ?? 0) ?: null,
                $item['name']    ?? '',
                $item['article'] ?? '',
                (float)($item['price'] ?? 0),
                $qty,
            ]);
        }
        $db->commit();
        $existing[$key] = true;
        $synced++;
    } catch (\Throwable $e) {
        $db->rollBack();
    }
}

jsonOk(['synced' => $synced]);

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
function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('Метод не поддерживается', 405);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) jsonErr('Неверный JSON');

$items   = $data['items']   ?? [];
$address = trim($data['address']  ?? '');
$comment = trim($data['comment']  ?? '');
$userId  = null;

if (isLoggedIn()) {
    $userId = (int)$_SESSION['user_id'];
} elseif (!empty($data['email'])) {
    $db  = Database::getInstance()->getConnection();
    $st  = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $st->execute([strtolower(trim($data['email']))]);
    $row = $st->fetch();
    if ($row) $userId = (int)$row['id'];
}

// Гостей без аккаунта не сохраняем в БД (заказ остаётся в localStorage)
if (!$userId) {
    jsonOk(['order_id' => null, 'saved' => false]);
}

if (empty($items)) jsonErr('Корзина пуста');

$total = 0;
foreach ($items as $item) {
    $total += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? $item['quantity'] ?? 1);
}

$db = Database::getInstance()->getConnection();

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

try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_order_number INT UNSIGNED DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_type VARCHAR(50) DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_label VARCHAR(100) DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_address VARCHAR(500) DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_type VARCHAR(50) DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_label VARCHAR(100) DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS contact_name VARCHAR(200) DEFAULT NULL"); } catch (\Throwable $e) {}
try { $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(50) DEFAULT NULL"); } catch (\Throwable $e) {}

$delivery        = $data['delivery'] ?? [];
$payment         = $data['payment']  ?? [];
$contact         = $data['contact']  ?? [];
$deliveryType    = $delivery['type']    ?? null;
$deliveryLabel   = $delivery['label']   ?? null;
$deliveryAddress = $address ?: ($delivery['address'] ?? '');
$paymentType     = $payment['type']     ?? null;
$paymentLabel    = $payment['label']    ?? null;
$contactName     = $contact['name']     ?? null;
$contactPhone    = $contact['phone']    ?? null;

$db->beginTransaction();
try {
    $nSt = $db->prepare('SELECT COALESCE(MAX(user_order_number), 0) + 1 FROM orders WHERE user_id=?');
    $nSt->execute([$userId]);
    $userOrderNum = (int)$nSt->fetchColumn();

    $st = $db->prepare('INSERT INTO orders (user_id, status, total, address, comment, user_order_number, delivery_type, delivery_label, delivery_address, payment_type, payment_label, contact_name, contact_phone) VALUES (?, \'pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$userId, $total, $deliveryAddress, $comment, $userOrderNum, $deliveryType, $deliveryLabel, $deliveryAddress, $paymentType, $paymentLabel, $contactName, $contactPhone]);
    $orderId = (int)$db->lastInsertId();

    $st = $db->prepare('INSERT INTO order_items (order_id, product_id, name, article, price, quantity) VALUES (?,?,?,?,?,?)');
    foreach ($items as $item) {
        $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        $st->execute([
            $orderId,
            (int)($item['id'] ?? $item['product_id'] ?? 0) ?: null,
            $item['name']    ?? '',
            $item['article'] ?? '',
            (float)($item['price'] ?? 0),
            $qty,
        ]);
    }
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    jsonErr('Ошибка сохранения заказа: ' . $e->getMessage());
}

jsonOk(['order_id' => $orderId]);

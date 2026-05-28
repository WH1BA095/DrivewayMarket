<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

function ok(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $msg): void {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function db(): PDO { return Database::getInstance()->getConnection(); }

ensureCartTable();

function ensureCartTable(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `user_cart` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `product_id` INT NOT NULL,
        `quantity`   INT NOT NULL DEFAULT 1,
        `name`       VARCHAR(255) NOT NULL DEFAULT '',
        `price`      DECIMAL(10,2) NOT NULL DEFAULT 0,
        `image`      VARCHAR(500) NOT NULL DEFAULT '',
        `article`    VARCHAR(100) NOT NULL DEFAULT '',
        `available`  INT NOT NULL DEFAULT 99,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_product` (`user_id`, `product_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$user = getCurrentUser();
if (!$user) { err('Не авторизован'); }

$uid    = (int)$user['id'];
$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $st = db()->prepare("SELECT product_id AS id, quantity AS qty, name, price, image, article, available FROM user_cart WHERE user_id = ? ORDER BY updated_at DESC");
    $st->execute([$uid]);
    ok(['items' => $st->fetchAll()]);
}

if ($action === 'sync') {
    $raw   = file_get_contents('php://input');
    $body  = json_decode($raw, true) ?: [];
    $items = $body['items'] ?? [];

    db()->prepare("DELETE FROM user_cart WHERE user_id = ?")->execute([$uid]);
    if (!empty($items)) {
        $st = db()->prepare("INSERT INTO user_cart (user_id, product_id, quantity, name, price, image, article, available) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($items as $item) {
            $pid = (int)($item['id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            if (!$pid) continue;
            $st->execute([
                $uid, $pid, $qty,
                mb_substr(trim($item['name'] ?? ''), 0, 255),
                (float)($item['price'] ?? 0),
                mb_substr(trim($item['image'] ?? ''), 0, 500),
                mb_substr(trim($item['article'] ?? ''), 0, 100),
                (int)($item['available'] ?? 99),
            ]);
        }
    }
    ok();
}

err('Неизвестное действие');

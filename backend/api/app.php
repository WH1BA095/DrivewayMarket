<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';

ensureTokensTable();
ensureOrdersTables();
ensureCartTable();

function db(): PDO { return Database::getInstance()->getConnection(); }

function ok(array $data = []): void {
    ob_end_clean();
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $msg, int $code = 400): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $b   = json_decode($raw, true) ?: $_POST;
    }
    return $b;
}
function bp(string $key): string { return trim(body()[$key] ?? ''); }

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

function ensureTokensTable(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `user_tokens` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `token`      VARCHAR(64) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_token` (`token`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureOrdersTables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id`                INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`           INT UNSIGNED NOT NULL,
        `user_order_number` INT UNSIGNED DEFAULT NULL,
        `status`            ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
        `total`             DECIMAL(10,2) NOT NULL DEFAULT 0,
        `address`           VARCHAR(500) NOT NULL DEFAULT '',
        `comment`           TEXT DEFAULT NULL,
        `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`        TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Добавляем колонку если нет — для старых БД без неё
    try {
        db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_order_number INT UNSIGNED DEFAULT NULL");
        // Нумеруем существующие заказы без порядкового номера
        db()->exec("
            UPDATE orders o
            INNER JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY id) AS rn
                FROM orders
            ) ranked ON o.id = ranked.id
            SET o.user_order_number = ranked.rn
            WHERE o.user_order_number IS NULL
        ");
    } catch (\Throwable $e) { /* колонка уже есть — игнорируем */ }

    db()->exec("CREATE TABLE IF NOT EXISTS `order_items` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `order_id`   INT NOT NULL,
        `product_id` INT,
        `name`       VARCHAR(255) NOT NULL,
        `article`    VARCHAR(100) NOT NULL DEFAULT '',
        `price`      DECIMAL(10,2) NOT NULL,
        `quantity`   INT NOT NULL DEFAULT 1,
        KEY `idx_order` (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS `product_questions` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `product_id`  INT NOT NULL,
        `user_id`     INT UNSIGNED NOT NULL,
        `question`    TEXT NOT NULL,
        `answer`      TEXT DEFAULT NULL,
        `status`      ENUM('pending','answered') DEFAULT 'pending',
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `answered_at` TIMESTAMP NULL,
        KEY `idx_product` (`product_id`),
        KEY `idx_user`    (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function generateToken(): string { return bin2hex(random_bytes(32)); }

function getAuthUser(): ?array {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $hdr, $m)) return null;
    $st = db()->prepare('SELECT u.* FROM users u JOIN user_tokens t ON t.user_id=u.id WHERE t.token=? LIMIT 1');
    $st->execute([$m[1]]);
    return $st->fetch() ?: null;
}

function requireAuth(): array {
    $u = getAuthUser();
    if (!$u) err('Не авторизован', 401);
    return $u;
}

function formatUser(array $u): array {
    return [
        'id'        => (int)$u['id'],
        'firstname' => $u['firstname'] ?? '',
        'lastname'  => $u['lastname']  ?? '',
        'full_name' => $u['full_name'] ?? '',
        'email'     => $u['email'],
        'phone'     => $u['phone']     ?? '',
        'address'   => $u['address']   ?? '',
        'birthdate' => $u['birthdate'] ?? null,
        'avatar'    => $u['avatar']    ?? null,
        'is_admin'  => (bool)($u['is_admin'] ?? false),
    ];
}

$action = $_GET['action'] ?? (body()['action'] ?? '');

switch ($action) {
    case 'categories':      getCategories();    break;
    case 'products':        getProducts();      break;
    case 'product':         getProduct();       break;
    case 'brands':          getBrands();        break;
    case 'models':          getModels();        break;
    case 'login':           handleLogin();      break;
    case 'register':        handleRegister();   break;
    case 'logout':          handleLogout();     break;
    case 'profile':         getProfile();       break;
    case 'update_profile':  updateProfile();    break;
    case 'change_password': changePassword();   break;
    case 'favorites':       getFavorites();     break;
    case 'toggle_favorite': toggleFavorite();   break;
    case 'user_cars':       getUserCars();      break;
    case 'add_car':         addCar();           break;
    case 'delete_car':      deleteCar();        break;
    case 'my_reviews':      getMyReviews();     break;
    case 'add_review':      addReview();        break;
    case 'my_questions':    getMyQuestions();   break;
    case 'my_orders':       getMyOrders();      break;
    case 'order_detail':    getOrderDetail();   break;
    case 'place_order':     placeOrder();       break;
    case 'cancel_order':    cancelOrder();      break;
    case 'upload_avatar':   uploadAvatar();     break;
    case 'get_questions':   getProductQuestions(); break;
    case 'add_question':    addProductQuestion();  break;
    case 'send_support':    sendSupport();         break;
    case 'my_support':      getMySupport();        break;
    case 'get_cart':        getCart();             break;
    case 'sync_cart':       syncCart();            break;
    case 'clear_cart':      clearCart();           break;
    default: err('Неизвестное действие');
}

function getCategories(): void {
    $st = db()->query('SELECT id, name, slug, icon FROM categories ORDER BY sort_order');
    ok(['categories' => $st->fetchAll()]);
}

function getProducts(): void {
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(20, max(1, (int)($_GET['per_page'] ?? 12)));
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['category_id'])) { $where[] = 'p.category_id=?'; $params[] = (int)$_GET['category_id']; }
    if (!empty($_GET['brand_id']))    { $where[] = 'p.brand_id=?';    $params[] = (int)$_GET['brand_id']; }
    if (!empty($_GET['model_id']))    { $where[] = 'p.model_id=?';    $params[] = (int)$_GET['model_id']; }
    if (!empty($_GET['in_stock']))    { $where[] = 'p.quantity-p.reserved>0'; }
    if (!empty($_GET['price_min']))   { $where[] = 'p.price>=?'; $params[] = (float)$_GET['price_min']; }
    if (!empty($_GET['price_max']))   { $where[] = 'p.price<=?'; $params[] = (float)$_GET['price_max']; }
    if (!empty($_GET['search'])) {
        $where[] = '(p.name LIKE ? OR p.article LIKE ?)';
        $q = '%' . $_GET['search'] . '%';
        $params[] = $q; $params[] = $q;
    }

    $w = implode(' AND ', $where);

    $cSt = db()->prepare("SELECT COUNT(*) FROM products p WHERE $w");
    $cSt->execute($params);
    $total = (int)$cSt->fetchColumn();

    $pParams = array_merge($params, [$perPage, $offset]);
    $st = db()->prepare("
        SELECT p.id, p.name, p.price, p.image, p.article,
               p.quantity-p.reserved AS available, p.in_stock,
               c.name AS category_name, cb.name AS brand_name, cm.name AS model_name,
               COALESCE(AVG(r.rating),0) AS avg_rating,
               COUNT(DISTINCT r.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN car_brands cb ON cb.id=p.brand_id
        LEFT JOIN car_models cm ON cm.id=p.model_id
        LEFT JOIN product_reviews r ON r.product_id=p.id AND r.status='approved'
        WHERE $w
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?
    ");
    $st->execute($pParams);
    $products = $st->fetchAll();

    $ids = array_column($products, 'id');
    $extraImages = [];
    if ($ids) {
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $iSt = db()->prepare("SELECT product_id, filename FROM product_images WHERE product_id IN ($ph) ORDER BY sort_order");
        $iSt->execute($ids);
        foreach ($iSt->fetchAll() as $row) {
            $extraImages[$row['product_id']][] = $row['filename'];
        }
    }

    foreach ($products as &$p) {
        $p['avg_rating']   = round((float)$p['avg_rating'], 1);
        $p['review_count'] = (int)$p['review_count'];
        $p['available']    = (int)$p['available'];
        $p['price']        = (float)$p['price'];
        $imgs = $extraImages[$p['id']] ?? [];
        if (empty($imgs) && $p['image']) $imgs = [$p['image']];
        $p['images'] = $imgs;
        if ($imgs) $p['image'] = $imgs[0];
    }

    ok(['products' => $products, 'total' => $total, 'page' => $page,
        'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)]);
}

function getProduct(): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) err('ID не указан');

    $st = db()->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               cb.name AS brand_name, cm.name AS model_name, pt.name AS type_name,
               COALESCE(AVG(r.rating),0) AS avg_rating, COUNT(DISTINCT r.id) AS review_count
        FROM products p
        LEFT JOIN categories c  ON c.id=p.category_id
        LEFT JOIN car_brands cb ON cb.id=p.brand_id
        LEFT JOIN car_models cm ON cm.id=p.model_id
        LEFT JOIN product_types pt ON pt.id=p.type_id
        LEFT JOIN product_reviews r ON r.product_id=p.id AND r.status='approved'
        WHERE p.id=? GROUP BY p.id
    ");
    $st->execute([$id]);
    $product = $st->fetch();
    if (!$product) err('Товар не найден', 404);

    $product['avg_rating']   = round((float)$product['avg_rating'], 1);
    $product['review_count'] = (int)$product['review_count'];
    $product['price']        = (float)$product['price'];
    $product['available']    = (int)($product['quantity'] - $product['reserved']);

    $imgSt = db()->prepare('SELECT id, filename FROM product_images WHERE product_id=? ORDER BY sort_order');
    $imgSt->execute([$id]);
    $images = $imgSt->fetchAll();
    if (empty($images) && $product['image']) $images = [['id'=>0,'filename'=>$product['image']]];

    $revSt = db()->prepare("
        SELECT r.id, r.rating, r.title, r.body, r.admin_reply, r.created_at,
               CONCAT(u.firstname,' ',u.lastname) AS author, u.avatar
        FROM product_reviews r JOIN users u ON u.id=r.user_id
        WHERE r.product_id=? AND r.status='approved'
        ORDER BY r.created_at DESC LIMIT 15
    ");
    $revSt->execute([$id]);
    $reviews = $revSt->fetchAll();

    $simSt = db()->prepare('SELECT id, name, price, image FROM products WHERE category_id=? AND id!=? LIMIT 6');
    $simSt->execute([$product['category_id'], $id]);
    $similar = $simSt->fetchAll();
    foreach ($similar as &$s) $s['price'] = (float)$s['price'];

    $isFav = false;
    if ($user = getAuthUser()) {
        $fSt = db()->prepare('SELECT 1 FROM user_favorites WHERE user_id=? AND product_id=?');
        $fSt->execute([$user['id'], $id]);
        $isFav = (bool)$fSt->fetchColumn();
    }

    ok(['product'=>$product, 'images'=>$images, 'reviews'=>$reviews, 'similar'=>$similar, 'is_favorited'=>$isFav]);
}

function getBrands(): void {
    $st = db()->query('SELECT id, name, slug FROM car_brands ORDER BY name');
    ok(['brands' => $st->fetchAll()]);
}
function getModels(): void {
    $bid = (int)($_GET['brand_id'] ?? 0);
    if (!$bid) err('brand_id не указан');
    $st = db()->prepare('SELECT id, name FROM car_models WHERE brand_id=? ORDER BY name');
    $st->execute([$bid]);
    ok(['models' => $st->fetchAll()]);
}

function handleLogin(): void {
    $email    = bp('email');
    $password = body()['password'] ?? '';
    if (!$email || !$password) err('Введите email и пароль');

    $st = db()->prepare('SELECT * FROM users WHERE email=?');
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) err('Неверный email или пароль');

    $token = generateToken();
    db()->prepare('INSERT INTO user_tokens (user_id,token) VALUES (?,?)')->execute([$user['id'], $token]);
    ok(['token' => $token, 'user' => formatUser($user)]);
}

function handleRegister(): void {
    $firstname = bp('firstname');
    $lastname  = bp('lastname');
    $email     = bp('email');
    $phone     = bp('phone');
    $password  = body()['password'] ?? '';

    if (!$firstname || !$email || !$password) err('Заполните обязательные поля');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('Некорректный email');
    if (strlen($password) < 6) err('Пароль минимум 6 символов');

    $st = db()->prepare('SELECT id FROM users WHERE email=?');
    $st->execute([$email]);
    if ($st->fetch()) err('Email уже используется');

    $hash     = password_hash($password, PASSWORD_DEFAULT);
    $fullName = trim("$firstname $lastname");

    $st = db()->prepare('INSERT INTO users (email,password_hash,full_name,firstname,lastname,phone,is_admin,created_at) VALUES (?,?,?,?,?,?,0,NOW())');
    $st->execute([$email, $hash, $fullName, $firstname, $lastname, $phone]);
    $userId = (int)db()->lastInsertId();

    $token = generateToken();
    db()->prepare('INSERT INTO user_tokens (user_id,token) VALUES (?,?)')->execute([$userId, $token]);

    ok(['token'=>$token,'user'=>['id'=>$userId,'firstname'=>$firstname,'lastname'=>$lastname,
        'full_name'=>$fullName,'email'=>$email,'phone'=>$phone,'is_admin'=>false]]);
}

function handleLogout(): void {
    requireAuth();
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $hdr, $m)) {
        db()->prepare('DELETE FROM user_tokens WHERE token=?')->execute([$m[1]]);
    }
    ok();
}

function getProfile(): void {
    $user = requireAuth();
    $st = db()->prepare('SELECT id,firstname,lastname,full_name,email,phone,birthdate,address,avatar,created_at FROM users WHERE id=?');
    $st->execute([$user['id']]);
    ok(['user' => $st->fetch()]);
}

function updateProfile(): void {
    $user = requireAuth();
    $firstname = bp('firstname'); $lastname = bp('lastname');
    $phone = bp('phone'); $birthdate = bp('birthdate') ?: null; $address = bp('address');
    if (!$firstname) err('Имя обязательно');
    $fullName = trim("$firstname $lastname");
    $st = db()->prepare('UPDATE users SET firstname=?,lastname=?,full_name=?,phone=?,birthdate=?,address=?,updated_at=NOW() WHERE id=?');
    $st->execute([$firstname,$lastname,$fullName,$phone,$birthdate,$address,$user['id']]);
    ok();
}

function changePassword(): void {
    $user    = requireAuth();
    $current = body()['current_password'] ?? '';
    $new1    = body()['new_password']     ?? '';
    $new2    = body()['new_password2']    ?? '';
    if (!$current || !$new1) err('Заполните все поля');
    if (strlen($new1) < 6)   err('Пароль минимум 6 символов');
    if ($new1 !== $new2)      err('Пароли не совпадают');
    $st = db()->prepare('SELECT password_hash FROM users WHERE id=?');
    $st->execute([$user['id']]);
    if (!password_verify($current, $st->fetch()['password_hash'])) err('Неверный текущий пароль');
    db()->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')
        ->execute([password_hash($new1, PASSWORD_DEFAULT), $user['id']]);
    ok();
}

function getFavorites(): void {
    $user = requireAuth();
    $st = db()->prepare("
        SELECT p.id, p.name, p.price, p.image, p.article,
               p.quantity-p.reserved AS available,
               COALESCE(AVG(r.rating),0) AS avg_rating, COUNT(DISTINCT r.id) AS review_count
        FROM user_favorites f
        JOIN products p ON p.id=f.product_id
        LEFT JOIN product_reviews r ON r.product_id=p.id AND r.status='approved'
        WHERE f.user_id=?
        GROUP BY p.id ORDER BY f.created_at DESC
    ");
    $st->execute([$user['id']]);
    $products = $st->fetchAll();
    foreach ($products as &$p) {
        $p['avg_rating']   = round((float)$p['avg_rating'], 1);
        $p['price']        = (float)$p['price'];
        $p['review_count'] = (int)$p['review_count'];
    }
    ok(['favorites' => $products]);
}

function toggleFavorite(): void {
    $user = requireAuth();
    $pid  = (int)(body()['product_id'] ?? 0);
    if (!$pid) err('product_id не указан');
    $st = db()->prepare('SELECT id FROM user_favorites WHERE user_id=? AND product_id=?');
    $st->execute([$user['id'], $pid]);
    if ($st->fetch()) {
        db()->prepare('DELETE FROM user_favorites WHERE user_id=? AND product_id=?')->execute([$user['id'],$pid]);
        ok(['action'=>'removed']);
    } else {
        db()->prepare('INSERT INTO user_favorites (user_id,product_id) VALUES (?,?)')->execute([$user['id'],$pid]);
        ok(['action'=>'added']);
    }
}

function getUserCars(): void {
    $user = requireAuth();
    $st = db()->prepare('SELECT id,brand,model,year FROM user_cars WHERE user_id=? ORDER BY id DESC');
    $st->execute([$user['id']]);
    ok(['cars' => $st->fetchAll()]);
}

function addCar(): void {
    $user  = requireAuth();
    $brand = bp('brand'); $model = bp('model'); $year = (int)(body()['year'] ?? 0) ?: null;
    if (!$brand || !$model) err('Укажите марку и модель');
    $st = db()->prepare('INSERT INTO user_cars (user_id,brand,model,year) VALUES (?,?,?,?)');
    $st->execute([$user['id'],$brand,$model,$year]);
    ok(['car'=>['id'=>(int)db()->lastInsertId(),'brand'=>$brand,'model'=>$model,'year'=>$year]]);
}

function deleteCar(): void {
    $user  = requireAuth();
    $carId = (int)(body()['car_id'] ?? $_GET['car_id'] ?? 0);
    if (!$carId) err('car_id не указан');
    db()->prepare('DELETE FROM user_cars WHERE id=? AND user_id=?')->execute([$carId,$user['id']]);
    ok();
}

function getMyReviews(): void {
    $user = requireAuth();
    $st = db()->prepare("
        SELECT r.id, r.product_id, r.rating, r.title, r.body, r.admin_reply, r.status, r.created_at,
               p.name AS product_name, p.image AS product_image
        FROM product_reviews r JOIN products p ON p.id=r.product_id
        WHERE r.user_id=? ORDER BY r.created_at DESC
    ");
    $st->execute([$user['id']]);
    ok(['reviews' => $st->fetchAll()]);
}

function addReview(): void {
    $user      = requireAuth();
    $productId = (int)(body()['product_id'] ?? 0);
    $rating    = max(1, min(5, (int)(body()['rating'] ?? 5)));
    $title     = bp('title');
    $body_text = bp('body');
    if (!$productId) err('product_id не указан');
    if (!$body_text) err('Напишите текст отзыва');
    $st = db()->prepare("INSERT INTO product_reviews (product_id,user_id,rating,title,body,status)
        VALUES (?,?,?,?,?,'approved')
        ON DUPLICATE KEY UPDATE rating=VALUES(rating),title=VALUES(title),body=VALUES(body)");
    $st->execute([$productId,$user['id'],$rating,$title,$body_text]);
    ok();
}

function getMyQuestions(): void {
    $user = requireAuth();
    $st = db()->prepare("
        SELECT q.id, q.product_id, q.question, q.answer, q.status, q.created_at, q.answered_at,
               p.name AS product_name, p.image AS product_image
        FROM product_questions q JOIN products p ON p.id=q.product_id
        WHERE q.user_id=? ORDER BY q.created_at DESC
    ");
    $st->execute([$user['id']]);
    ok(['questions' => $st->fetchAll()]);
}

function getMyOrders(): void {
    $user = requireAuth();
    try {
        $st = db()->prepare("
            SELECT o.id, o.user_order_number, o.status, o.total, o.address, o.comment, o.created_at,
                   COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id=o.id
            WHERE o.user_id=?
            GROUP BY o.id ORDER BY o.created_at DESC
        ");
        $st->execute([$user['id']]);
        $orders = $st->fetchAll();

        foreach ($orders as &$o) {
            $o['total']             = (float)$o['total'];
            $o['item_count']        = (int)$o['item_count'];
            $o['id']                = (int)$o['id'];
            $o['user_order_number'] = (int)($o['user_order_number'] ?? $o['id']);

            $iSt = db()->prepare('SELECT name, article, price, quantity FROM order_items WHERE order_id=?');
            $iSt->execute([$o['id']]);
            $items = $iSt->fetchAll();
            $o['items'] = array_map(fn($i) => [
                'name'     => $i['name'],
                'article'  => $i['article'],
                'price'    => (float)$i['price'],
                'quantity' => (int)$i['quantity'],
            ], $items);
        }
        ok(['orders' => $orders]);
    } catch (\Throwable $e) {
        ok(['orders' => [], 'error' => $e->getMessage()]);
    }
}

function getOrderDetail(): void {
    $user = requireAuth();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) err('ID не указан');

    $st = db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=?');
    $st->execute([$id, $user['id']]);
    $order = $st->fetch();
    if (!$order) err('Заказ не найден', 404);
    $order['total'] = (float)$order['total'];

    $st = db()->prepare('SELECT * FROM order_items WHERE order_id=?');
    $st->execute([$id]);
    $items = $st->fetchAll();
    foreach ($items as &$i) { $i['price'] = (float)$i['price']; $i['quantity'] = (int)$i['quantity']; }

    ok(['order' => $order, 'items' => $items]);
}

function placeOrder(): void {
    $user     = requireAuth();
    $data     = body();
    $items    = $data['items']    ?? [];
    $address  = trim($data['address']  ?? '');
    $comment  = trim($data['comment']  ?? '');
    $delivery = trim($data['delivery_type'] ?? $data['delivery'] ?? 'pickup');
    $payment  = trim($data['payment_type']  ?? $data['payment']  ?? 'online');

    if (empty($items)) err('Корзина пуста');
    if ($delivery !== 'pickup' && !$address) err('Укажите адрес доставки');

    $total = 0;
    foreach ($items as $item) {
        $total += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
    }

    if ($delivery === 'pickup' && !$address) {
        $address = 'Самовывоз';
    }

    $db = db();
    $db->beginTransaction();
    try {
        $nSt = $db->prepare('SELECT COALESCE(MAX(user_order_number), 0) + 1 FROM orders WHERE user_id=?');
        $nSt->execute([$user['id']]);
        $userOrderNum = (int)$nSt->fetchColumn();

        $st = $db->prepare('INSERT INTO orders (user_id,status,total,address,comment,user_order_number) VALUES (?,\'pending\',?,?,?,?)');
        $st->execute([$user['id'], $total, $address, $comment, $userOrderNum]);
        $orderId = (int)$db->lastInsertId();

        $st = $db->prepare('INSERT INTO order_items (order_id,product_id,name,article,price,quantity) VALUES (?,?,?,?,?,?)');
        foreach ($items as $item) {
            $st->execute([
                $orderId,
                (int)($item['product_id'] ?? 0),
                $item['name']     ?? '',
                $item['article']  ?? '',
                (float)($item['price'] ?? 0),
                (int)($item['quantity'] ?? 1),
            ]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        err('Ошибка оформления заказа: ' . $e->getMessage());
    }

    ok(['order_id' => $orderId]);
}

function cancelOrder(): void {
    $user = requireAuth();
    $id   = (int)(body()['order_id'] ?? 0);
    if (!$id) err('order_id не указан');

    $st = db()->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'");
    $st->execute([$id, $user['id']]);
    if ($st->rowCount() === 0) err('Нельзя отменить этот заказ');
    ok();
}

function uploadAvatar(): void {
    $user = requireAuth();

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        err('Файл не выбран');
    }
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        err('Ошибка загрузки (код ' . $_FILES['avatar']['error'] . ')');
    }
    if ($_FILES['avatar']['size'] > 3 * 1024 * 1024) {
        err('Максимальный размер файла — 3 МБ');
    }

    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        err('Допустимые форматы: JPG, PNG, WebP');
    }
    $mime = mime_content_type($_FILES['avatar']['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        err('Файл не является изображением');
    }

    $dir = __DIR__ . '/../img/avatars/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = 'av_' . $user['id'] . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $filename)) {
        err('Не удалось сохранить файл');
    }

    $path = 'img/avatars/' . $filename;
    db()->prepare('UPDATE users SET avatar=?, updated_at=NOW() WHERE id=?')
        ->execute([$path, $user['id']]);

    ok(['avatar' => $path]);
}

function getProductQuestions(): void {
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) err('product_id не указан');

    $uid = 0;
    if ($user = getAuthUser()) $uid = $user['id'];

    $st = db()->prepare("
        SELECT q.id, q.question, q.answer, q.answered_at, q.created_at,
               u.firstname, u.lastname, u.full_name,
               IF(q.user_id = :uid, 1, 0) AS is_mine
        FROM product_questions q
        JOIN users u ON u.id = q.user_id
        WHERE q.product_id = :pid
        ORDER BY q.created_at DESC
    ");
    $st->execute([':pid' => $pid, ':uid' => $uid]);
    $rows = $st->fetchAll();

    foreach ($rows as &$q) {
        $parts = array_filter([$q['full_name'] ?: trim($q['firstname'].' '.($q['lastname'] ?? ''))]);
        $q['display_name'] = $parts ? reset($parts) : 'Покупатель';
        $q['is_mine'] = (bool)$q['is_mine'];
        unset($q['firstname'], $q['lastname'], $q['full_name']);
    }
    ok(['questions' => $rows]);
}

function addProductQuestion(): void {
    $user = requireAuth();
    $b    = body();
    $pid  = (int)($b['product_id'] ?? $_POST['product_id'] ?? 0);
    if (!$pid) err('product_id не указан');

    $question = mb_substr(strip_tags(trim($b['question'] ?? $_POST['question'] ?? '')), 0, 1000);
    if (mb_strlen($question) < 5) err('Вопрос слишком короткий (минимум 5 символов)');

    $st = db()->prepare("INSERT INTO product_questions (product_id, user_id, question) VALUES (?,?,?)");
    $st->execute([$pid, $user['id'], $question]);

    ok(['message' => 'Вопрос отправлен! Ответим в ближайшее время.']);
}

function sendSupport(): void {
    $user = getAuthUser();
    $b    = body();

    $name    = mb_substr(strip_tags(trim($b['name']    ?? '')), 0, 100);
    $email   = mb_strtolower(mb_substr(strip_tags(trim($b['email'] ?? '')), 0, 150));
    $subject = mb_substr(strip_tags(trim($b['subject'] ?? '')), 0, 120);
    $message = mb_substr(strip_tags(trim($b['message'] ?? '')), 0, 5000);

    // Если залогинен и поля пустые — берём из профиля
    if ($user) {
        if (!$name)  $name  = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
        if (!$email) $email = $user['email'] ?? '';
    }

    if (!$name)    err('Введите имя');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('Некорректный email');
    if (mb_strlen($message) < 10) err('Сообщение слишком короткое (минимум 10 символов)');

    $uid = $user ? $user['id'] : null;
    db()->prepare("INSERT INTO support_messages (user_id, name, email, subject, message) VALUES (?,?,?,?,?)")
        ->execute([$uid, $name, $email, $subject, $message]);

    ok(['message' => 'Сообщение отправлено! Мы свяжемся с вами в ближайшее время.']);
}

function getMySupport(): void {
    $user = requireAuth();
    $st = db()->prepare("
        SELECT id, subject, message, status, reply, replied_at, created_at
        FROM support_messages
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $st->execute([$user['id']]);
    ok(['messages' => $st->fetchAll()]);
}

function getCart(): void {
    $user = requireAuth();
    $st = db()->prepare("SELECT product_id AS id, quantity AS qty, name, price, image, article, available FROM user_cart WHERE user_id = ? ORDER BY updated_at DESC");
    $st->execute([$user['id']]);
    ok(['items' => $st->fetchAll()]);
}

function syncCart(): void {
    $user  = requireAuth();
    $items = body()['items'] ?? [];
    $uid   = $user['id'];

    db()->prepare("DELETE FROM user_cart WHERE user_id = ?")->execute([$uid]);
    if (empty($items)) { ok(); }

    $st = db()->prepare("INSERT INTO user_cart (user_id, product_id, quantity, name, price, image, article, available) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($items as $item) {
        $pid = (int)($item['id'] ?? $item['product_id'] ?? 0);
        $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
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
    ok();
}

function clearCart(): void {
    $user = requireAuth();
    db()->prepare("DELETE FROM user_cart WHERE user_id = ?")->execute([$user['id']]);
    ok();
}

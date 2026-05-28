<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if (!isLoggedIn() || empty($_SESSION['user_is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_product':          handleGet();         break;
    case 'create_product':       handleCreate();      break;
    case 'update_product':       handleUpdate();      break;
    case 'delete_product':       handleDelete();      break;
    case 'delete_product_image': handleDeleteImage(); break;
    case 'get_all_reviews':  adminGetReviews();  break;
    case 'reply_review':     adminReplyReview(); break;
    case 'reject_review':    adminModReview('rejected'); break;
    case 'restore_review':   adminModReview('approved'); break;
    case 'delete_review':    adminDeleteReview(); break;
    case 'get_all_questions': adminGetQuestions();  break;
    case 'answer_question':   adminAnswerQuestion(); break;
    case 'delete_question':   adminDeleteQuestion(); break;
    case 'get_support':      adminGetSupport();   break;
    case 'reply_support':    adminReplySupport(); break;
    case 'mark_read':        adminMarkRead();     break;
    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
}

function adminGetReviews(): void {
    $status  = $_GET['status'] ?? 'all';
    $search  = trim($_GET['search'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where = []; $params = [];
    if ($status !== 'all') { $where[] = 'r.status = ?'; $params[] = $status; }
    if ($search)           { $where[] = '(p.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql = "SELECT r.id, r.product_id, r.rating, r.title, r.body, r.status,
                   r.admin_reply, r.admin_reply_at, r.created_at,
                   p.name AS product_name, p.image AS product_image,
                   TRIM(CONCAT(COALESCE(NULLIF(u.firstname,''),''),' ',COALESCE(NULLIF(u.lastname,''),''))) AS uname,
                   u.full_name AS legacy_name, u.email AS uemail
            FROM product_reviews r
            JOIN products p ON p.id = r.product_id
            JOIN users u ON u.id = r.user_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";

    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['display_name'] = trim($r['uname']) ?: ($r['legacy_name'] ?: $r['uemail']);
        unset($r['uname'], $r['legacy_name']);
    }

    $cntSql = "SELECT COUNT(*) FROM product_reviews r JOIN products p ON p.id=r.product_id JOIN users u ON u.id=r.user_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $total = (int)db()->prepare($cntSql)->execute($params) ? db()->prepare($cntSql) : 0;
    $cntSt = db()->prepare($cntSql);
    $cntSt->execute($params);
    $total = (int)$cntSt->fetchColumn();

    ok(['reviews' => $rows, 'total' => $total, 'pages' => ceil($total / $perPage)]);
}

function adminReplyReview(): void {
    $id    = (int)p('review_id');
    $reply = trim(p('reply'));
    if (!$id || !$reply) err('Не указан ID или текст ответа');
    $st = db()->prepare("UPDATE product_reviews SET admin_reply=?, admin_reply_at=NOW() WHERE id=?");
    $st->execute([$reply, $id]);
    ok(['message' => 'Ответ сохранён']);
}

function adminModReview(string $status): void {
    $id = (int)p('review_id');
    if (!$id) err('Не указан ID');
    db()->prepare("UPDATE product_reviews SET status=? WHERE id=?")->execute([$status, $id]);
    ok(['message' => 'Статус обновлён']);
}

function adminDeleteReview(): void {
    $id = (int)p('review_id');
    if (!$id) err('Не указан ID');
    db()->prepare("DELETE FROM product_reviews WHERE id=?")->execute([$id]);
    ok(['message' => 'Отзыв удалён']);
}

function adminGetQuestions(): void {
    $status  = $_GET['status'] ?? 'all';
    $search  = trim($_GET['search'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where = []; $params = [];
    if ($status !== 'all') { $where[] = 'q.status = ?'; $params[] = $status; }
    if ($search)           { $where[] = '(p.name LIKE ? OR q.question LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

    $sql = "SELECT q.id, q.product_id, q.question, q.answer, q.status, q.answered_at, q.created_at,
                   p.name AS product_name,
                   TRIM(CONCAT(COALESCE(NULLIF(u.firstname,''),''),' ',COALESCE(NULLIF(u.lastname,''),''))) AS uname,
                   u.full_name AS legacy_name, u.email AS uemail
            FROM product_questions q
            JOIN products p ON p.id = q.product_id
            JOIN users u ON u.id = q.user_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY q.created_at DESC LIMIT $perPage OFFSET $offset";

    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$q) {
        $q['display_name'] = trim($q['uname']) ?: ($q['legacy_name'] ?: $q['uemail']);
        unset($q['uname'], $q['legacy_name']);
    }
    ok(['questions' => $rows]);
}

function adminAnswerQuestion(): void {
    $id     = (int)p('question_id');
    $answer = trim(p('answer'));
    if (!$id || !$answer) err('Не указан ID или текст ответа');
    $st = db()->prepare("UPDATE product_questions SET answer=?, answered_at=NOW(), status='answered' WHERE id=?");
    $st->execute([$answer, $id]);
    ok(['message' => 'Ответ сохранён']);
}

function adminDeleteQuestion(): void {
    $id = (int)p('question_id');
    if (!$id) err('Не указан ID');
    db()->prepare("DELETE FROM product_questions WHERE id=?")->execute([$id]);
    ok(['message' => 'Вопрос удалён']);
}

function adminGetSupport(): void {
    $status  = $_GET['status'] ?? 'all';
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where = []; $params = [];
    if ($status !== 'all') { $where[] = 'status = ?'; $params[] = $status; }

    $sql = "SELECT id, user_id, name, email, subject, message, status, reply, replied_at, created_at
            FROM support_messages"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";

    $st = db()->prepare($sql);
    $st->execute($params);
    ok(['messages' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

function adminReplySupport(): void {
    $id    = (int)p('msg_id');
    $reply = trim(p('reply'));
    if (!$id || !$reply) err('Не указан ID или текст ответа');
    $st = db()->prepare("UPDATE support_messages SET reply=?, replied_at=NOW(), status='replied' WHERE id=?");
    $st->execute([$reply, $id]);
    ok(['message' => 'Ответ сохранён']);
}

function adminMarkRead(): void {
    $id = (int)p('msg_id');
    if (!$id) err('Не указан ID');
    db()->prepare("UPDATE support_messages SET status='read' WHERE id=? AND status='new'")->execute([$id]);
    ok();
}

function db(): PDO { return Database::getInstance()->getConnection(); }
function ok(array $d = []): void { echo json_encode(array_merge(['success' => true], $d)); exit; }
function err(string $m, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $m]);
    exit;
}
function p(string $k): string { return trim($_POST[$k] ?? ''); }

function handleGet(): void {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) err('ID не указан');
    $st = db()->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $product = $st->fetch();
    if (!$product) err('Товар не найден');

    $imgSt = db()->prepare('SELECT id, filename, is_primary, sort_order FROM product_images WHERE product_id=? ORDER BY sort_order ASC');
    $imgSt->execute([$id]);
    $images = $imgSt->fetchAll(PDO::FETCH_ASSOC);

    ok(['product' => $product, 'images' => $images]);
}

function handleCreate(): void {
    $data = validate();

    $st = db()->prepare('SELECT id FROM products WHERE article = ?');
    $st->execute([$data['article']]);
    if ($st->fetch()) err('Товар с таким артикулом уже существует');

    $st = db()->prepare('
        INSERT INTO products
            (category_id, type_id, brand_id, model_id, name, description,
             price, quantity, article, car_model, image, in_stock, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,NULL,?,NOW())
    ');
    $st->execute([
        $data['category_id'], $data['type_id'], $data['brand_id'], $data['model_id'],
        $data['name'], $data['description'], $data['price'], $data['quantity'],
        $data['article'], $data['car_model'],
        $data['quantity'] > 0 ? 1 : 0,
    ]);
    $productId = (int)db()->lastInsertId();
    uploadProductImages($productId);

    ok(['id' => $productId]);
}

function handleUpdate(): void {
    $id = (int)p('id');
    if (!$id) err('ID не указан');

    $data = validate();

    // исключаем себя из проверки уникальности артикула
    $st = db()->prepare('SELECT id FROM products WHERE article = ? AND id != ?');
    $st->execute([$data['article'], $id]);
    if ($st->fetch()) err('Товар с таким артикулом уже существует');

    $setParts = [
        'category_id=?', 'type_id=?', 'brand_id=?', 'model_id=?',
        'name=?', 'description=?', 'price=?', 'quantity=?',
        'article=?', 'car_model=?', 'in_stock=?',
    ];
    $params = [
        $data['category_id'], $data['type_id'], $data['brand_id'], $data['model_id'],
        $data['name'], $data['description'], $data['price'], $data['quantity'],
        $data['article'], $data['car_model'], $data['quantity'] > 0 ? 1 : 0,
    ];
    $params[] = $id;

    $st = db()->prepare('UPDATE products SET ' . implode(', ', $setParts) . ' WHERE id=?');
    $st->execute($params);

    if (!empty($_POST['delete_images'])) {
        foreach ((array)$_POST['delete_images'] as $imgId) {
            $imgId = (int)$imgId;
            if (!$imgId) continue;
            $imgSt = db()->prepare('SELECT filename FROM product_images WHERE id=? AND product_id=?');
            $imgSt->execute([$imgId, $id]);
            $row = $imgSt->fetch();
            if ($row) {
                $fp = __DIR__ . '/../' . $row['filename'];
                if (file_exists($fp)) @unlink($fp);
                db()->prepare('DELETE FROM product_images WHERE id=?')->execute([$imgId]);
            }
        }
    }

    uploadProductImages($id);

    ok();
}

function handleDelete(): void {
    $id = (int)p('id');
    if (!$id) err('ID не указан');
    $st = db()->prepare('DELETE FROM products WHERE id=?');
    $st->execute([$id]);
    ok();
}

function validate(): array {
    $name      = p('name');
    $article   = p('article');
    $catId     = (int)p('category_id');
    $typeId    = (int)p('type_id');
    $brandId   = (int)p('brand_id');
    $modelId   = (int)p('model_id');
    $price     = (float)p('price');
    $quantity  = (int)p('quantity');
    $desc      = p('description');
    $carModel  = p('car_model');

    if (!$name)      err('Укажите название товара');
    if (!$article)   err('Укажите артикул');
    if (!$catId)     err('Выберите категорию');
    if (!$typeId)    err('Выберите тип товара');
    if (!$brandId)   err('Выберите марку');
    if (!$modelId)   err('Выберите модель автомобиля');
    if ($price <= 0) err('Укажите корректную цену (больше 0)');
    if ($quantity < 0) err('Количество не может быть отрицательным');

    return [
        'name' => $name, 'article' => $article,
        'category_id' => $catId, 'type_id' => $typeId,
        'brand_id' => $brandId, 'model_id' => $modelId,
        'price' => $price, 'quantity' => $quantity,
        'description' => $desc, 'car_model' => $carModel,
    ];
}

function uploadProductImages(int $productId): void {
    if (empty($_FILES['images']['name'][0])) return;

    $dir = __DIR__ . '/../img/products/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $cntSt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id=?');
    $cntSt->execute([$productId]);
    $existing = (int)$cntSt->fetchColumn();

    $names    = (array)$_FILES['images']['name'];
    $tmpNames = (array)$_FILES['images']['tmp_name'];
    $errors   = (array)$_FILES['images']['error'];
    $sizes    = (array)$_FILES['images']['size'];
    $maxSlots = 4;
    $sortBase = $existing;
    $added    = 0;

    for ($i = 0; $i < count($names) && ($existing + $added) < $maxSlots; $i++) {
        if ($errors[$i] !== UPLOAD_ERR_OK) continue;
        if ($sizes[$i] > 5 * 1024 * 1024) continue;

        $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

        $mime = @mime_content_type($tmpNames[$i]);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) continue;

        $filename = 'p_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $i . '.' . $ext;
        if (!move_uploaded_file($tmpNames[$i], $dir . $filename)) continue;

        $isPrimary = ($sortBase + $added === 0) ? 1 : 0;
        db()->prepare('INSERT INTO product_images (product_id, filename, sort_order, is_primary) VALUES (?,?,?,?)')
             ->execute([$productId, 'img/products/' . $filename, $sortBase + $added, $isPrimary]);
        $added++;
    }

    if ($added > 0) syncPrimaryImage($productId);
}

function syncPrimaryImage(int $productId): void {
    db()->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$productId]);
    $st = db()->prepare('SELECT id, filename FROM product_images WHERE product_id=? ORDER BY sort_order ASC LIMIT 1');
    $st->execute([$productId]);
    $first = $st->fetch(PDO::FETCH_ASSOC);
    if ($first) {
        db()->prepare('UPDATE product_images SET is_primary=1 WHERE id=?')->execute([$first['id']]);
        db()->prepare('UPDATE products SET image=? WHERE id=?')->execute([$first['filename'], $productId]);
    } else {
        db()->prepare('UPDATE products SET image=NULL WHERE id=?')->execute([$productId]);
    }
}

function handleDeleteImage(): void {
    $imgId     = (int)p('image_id');
    $productId = (int)p('product_id');
    if (!$imgId || !$productId) err('ID не указан');

    $st = db()->prepare('SELECT filename FROM product_images WHERE id=? AND product_id=?');
    $st->execute([$imgId, $productId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) err('Изображение не найдено');

    $fp = __DIR__ . '/../' . $row['filename'];
    if (file_exists($fp)) @unlink($fp);
    db()->prepare('DELETE FROM product_images WHERE id=?')->execute([$imgId]);
    syncPrimaryImage($productId);

    ok(['message' => 'Удалено']);
}

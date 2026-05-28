<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Выход — редирект (не JSON)
if ($action === 'logout') {
    logoutUser();
    header('Location: ../index.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'register':        handleRegister();      break;
    case 'login':           handleLogin();         break;
    case 'update_profile':  handleUpdateProfile(); break;
    case 'change_password': handleChangePass();    break;
    case 'get_profile':     handleGetProfile();    break;
    case 'upload_avatar':   handleUploadAvatar();  break;
    case 'add_car':         handleAddCar();        break;
    case 'remove_car':      handleRemoveCar();     break;
    case 'get_cars':        handleGetCars();       break;
    case 'get_favorites':   handleGetFavorites();  break;
    case 'add_favorite':    handleAddFavorite();   break;
    case 'remove_favorite': handleRemoveFavorite();break;
    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
}

function ok(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}
function err(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function db(): PDO {
    return Database::getInstance()->getConnection();
}
/** Безопасно читает строковое поле: trim + strip_tags + ограничение длины */
function p(string $key, int $maxLen = 255): string {
    $val = trim($_POST[$key] ?? '');
    $val = strip_tags($val);
    return mb_substr($val, 0, $maxLen);
}

function handleRegister(): void {
    $firstname = p('firstname', 60);
    $lastname  = p('lastname',  60);
    $email     = mb_strtolower(p('email', 150));
    $phone     = p('phone',     30);
    $pass      = $_POST['password']  ?? '';
    $pass2     = $_POST['password2'] ?? '';

    if (!$firstname || !$email || !$pass) err('Заполните обязательные поля');
    if (mb_strlen($firstname) < 2)  err('Имя слишком короткое');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('Некорректный email');
    if (strlen($pass) < 6)  err('Пароль должен быть не менее 6 символов');
    if (strlen($pass) > 72) err('Пароль слишком длинный (максимум 72 символа)');
    if ($pass !== $pass2)   err('Пароли не совпадают');

    $db = db();
    $st = $db->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) err('Пользователь с таким email уже существует');

    $hash     = password_hash($pass, PASSWORD_DEFAULT);
    $fullName = trim($firstname . ' ' . $lastname); // поддержка существующего поля full_name

    $st = $db->prepare('INSERT INTO users
        (email, password_hash, full_name, firstname, lastname, phone, is_admin, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
    $st->execute([$email, $hash, $fullName, $firstname, $lastname, $phone]);
    $id = (int)$db->lastInsertId();

    $user = ['id' => $id, 'firstname' => $firstname, 'lastname' => $lastname,
             'email' => $email, 'full_name' => $fullName, 'is_admin' => 0];
    loginUser($user);
    ok(['user' => ['id' => $id, 'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email]]);
}

function handleLogin(): void {
    $email = mb_strtolower(p('email', 150));
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) err('Введите email и пароль');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('Некорректный email');
    if (strlen($pass) > 72) err('Неверный email или пароль');

    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        err('Неверный email или пароль');
    }

    loginUser($user);
    ok(['user' => [
        'id'        => $user['id'],
        'firstname' => $_SESSION['user_firstname'],
        'lastname'  => $_SESSION['user_lastname'],
        'email'     => $user['email'],
    ]]);
}

function handleUpdateProfile(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $firstname = p('firstname', 60);
    $lastname  = p('lastname',  60);
    $phone     = p('phone',     30);
    $birthdate = p('birthdate', 10) ?: null;
    $address   = p('address',  200);

    if (!$firstname) err('Имя обязательно');
    if (mb_strlen($firstname) < 2) err('Имя слишком короткое');

    $fullName = trim($firstname . ' ' . $lastname);

    $st = db()->prepare('UPDATE users
        SET firstname=?, lastname=?, full_name=?, phone=?, birthdate=?, address=?, updated_at=NOW()
        WHERE id=?');
    $st->execute([$firstname, $lastname, $fullName, $phone, $birthdate, $address, $_SESSION['user_id']]);

    $_SESSION['user_firstname'] = $firstname;
    $_SESSION['user_lastname']  = $lastname;
    ok();
}

function handleChangePass(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $current = $_POST['current_password'] ?? '';
    $new1    = $_POST['new_password']     ?? '';
    $new2    = $_POST['new_password2']    ?? '';

    if (!$current || !$new1) err('Заполните все поля');
    if (strlen($new1) < 6)   err('Пароль должен быть не менее 6 символов');
    if ($new1 !== $new2)      err('Пароли не совпадают');

    $st = db()->prepare('SELECT password_hash FROM users WHERE id=?');
    $st->execute([$_SESSION['user_id']]);
    $row = $st->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        err('Неверный текущий пароль');
    }

    $st = db()->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?');
    $st->execute([password_hash($new1, PASSWORD_DEFAULT), $_SESSION['user_id']]);
    ok();
}

function handleGetProfile(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $st = db()->prepare('SELECT id,firstname,lastname,full_name,email,phone,birthdate,address,created_at FROM users WHERE id=?');
    $st->execute([$_SESSION['user_id']]);
    ok(['user' => $st->fetch()]);
}

function handleAddCar(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $brand = p('brand');
    $model = p('model');
    $year  = (int)p('year') ?: null;

    if (!$brand || !$model) err('Укажите марку и модель');

    $st = db()->prepare('INSERT INTO user_cars (user_id, brand, model, year) VALUES (?,?,?,?)');
    $st->execute([$_SESSION['user_id'], $brand, $model, $year]);
    $id = (int)db()->lastInsertId();
    ok(['car' => ['id' => $id, 'brand' => $brand, 'model' => $model, 'year' => $year]]);
}

function handleRemoveCar(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $carId = (int)p('car_id');
    $st = db()->prepare('DELETE FROM user_cars WHERE id=? AND user_id=?');
    $st->execute([$carId, $_SESSION['user_id']]);
    ok();
}

function handleUploadAvatar(): void {
    if (!isLoggedIn()) err('Не авторизован');

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

    $filename = 'av_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $filename)) {
        err('Не удалось сохранить файл');
    }

    $path = 'img/avatars/' . $filename;
    db()->prepare('UPDATE users SET avatar=?, updated_at=NOW() WHERE id=?')
        ->execute([$path, $_SESSION['user_id']]);

    ok(['avatar' => $path]);
}

function handleGetFavorites(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $st = db()->prepare('
        SELECT f.product_id, p.name, p.price, p.article, p.image,
               c.name AS category, c.slug AS cat_slug,
               p.quantity, p.available
        FROM user_favorites f
        JOIN products p ON p.id = f.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ');
    $st->execute([$_SESSION['user_id']]);
    ok(['favorites' => $st->fetchAll()]);
}

function handleAddFavorite(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $productId = (int)p('product_id');
    if (!$productId) err('ID товара не указан');

    $st = db()->prepare('SELECT id FROM products WHERE id=?');
    $st->execute([$productId]);
    if (!$st->fetch()) err('Товар не найден');

    // INSERT IGNORE — не ругаться на дубликат
    db()->prepare('INSERT IGNORE INTO user_favorites (user_id, product_id) VALUES (?,?)')
        ->execute([$_SESSION['user_id'], $productId]);

    ok();
}

function handleRemoveFavorite(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $productId = (int)p('product_id');
    db()->prepare('DELETE FROM user_favorites WHERE user_id=? AND product_id=?')
        ->execute([$_SESSION['user_id'], $productId]);

    ok();
}

function handleGetCars(): void {
    if (!isLoggedIn()) err('Не авторизован');

    $st = db()->prepare('SELECT id,brand,model,year FROM user_cars WHERE user_id=? ORDER BY id');
    $st->execute([$_SESSION['user_id']]);
    ok(['cars' => $st->fetchAll()]);
}

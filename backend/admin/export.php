<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if (!isLoggedIn() || empty($_SESSION['user_is_admin'])) {
    header('Location: ../index.php'); exit;
}

$db   = Database::getInstance()->getConnection();
$type = $_GET['type'] ?? 'products';

// UTF-8 BOM нужен Excel'у для корректного чтения кириллицы
function csvOut(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    header('Pragma: no-cache');

    echo "\xEF\xBB\xBF"; // BOM
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($fp, $row, ';');
    }
    fclose($fp);
    exit;
}

if ($type === 'products') {
    $products = $db->query('
        SELECT p.id, p.name, p.article,
               c.name  AS category,
               pt.name AS type,
               b.name  AS brand,
               m.name  AS model,
               p.price, p.quantity,
               COALESCE(p.available, p.quantity - p.reserved) AS available,
               p.car_model,
               p.description,
               CASE WHEN p.quantity > 0 THEN "В наличии" ELSE "Нет в наличии" END AS status,
               DATE_FORMAT(p.created_at, "%d.%m.%Y") AS created
        FROM products p
        LEFT JOIN categories    c  ON c.id  = p.category_id
        LEFT JOIN product_types pt ON pt.id = p.type_id
        LEFT JOIN car_brands    b  ON b.id  = p.brand_id
        LEFT JOIN car_models    m  ON m.id  = p.model_id
        ORDER BY p.id
    ')->fetchAll();

    $rows = array_map(fn($p) => [
        $p['id'], $p['name'], $p['article'],
        $p['category'], $p['type'], $p['brand'], $p['model'],
        number_format($p['price'], 2, '.', ''),
        $p['quantity'], $p['available'],
        $p['car_model'], $p['description'],
        $p['status'], $p['created'],
    ], $products);

    csvOut(
        'driveway_products_' . date('Y-m-d') . '.csv',
        ['ID','Название','Артикул','Категория','Тип','Марка','Модель авто','Цена (₽)','Кол-во','Доступно','Модель (текст)','Описание','Статус','Дата добавления'],
        $rows
    );
}

if ($type === 'users') {
    $users = $db->query('
        SELECT id, email,
               TRIM(CONCAT(COALESCE(NULLIF(firstname,""),""), " ", COALESCE(NULLIF(lastname,""),""))),
               full_name, phone, is_admin,
               DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") AS reg_date
        FROM users ORDER BY id
    ')->fetchAll(PDO::FETCH_NUM);

    $rows = array_map(fn($u) => [
        $u[0],
        $u[1],
        trim($u[2]) ?: $u[3] ?: '—',
        $u[4] ?: '—',
        $u[5] ? 'Администратор' : 'Пользователь',
        $u[6],
    ], $users);

    csvOut(
        'driveway_users_' . date('Y-m-d') . '.csv',
        ['ID','Email','Имя','Телефон','Роль','Дата регистрации'],
        $rows
    );
}

if ($type === 'stats') {
    $stats = $db->query('
        SELECT
            c.name                                         AS category,
            COUNT(p.id)                                    AS products_count,
            COALESCE(SUM(p.quantity), 0)                   AS total_qty,
            COALESCE(SUM(CASE WHEN p.quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
            COALESCE(SUM(CASE WHEN p.quantity > 0 AND p.quantity < 5 THEN 1 ELSE 0 END), 0) AS low_stock,
            COALESCE(SUM(p.price * p.quantity), 0)         AS total_value,
            COALESCE(AVG(p.price), 0)                      AS avg_price,
            COALESCE(MIN(p.price), 0)                      AS min_price,
            COALESCE(MAX(p.price), 0)                      AS max_price
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id, c.name
        ORDER BY products_count DESC
    ')->fetchAll();

    $rows = array_map(fn($s) => [
        $s['category'],
        $s['products_count'],
        $s['total_qty'],
        $s['out_of_stock'],
        $s['low_stock'],
        number_format($s['total_value'], 2, '.', ''),
        number_format($s['avg_price'],   2, '.', ''),
        number_format($s['min_price'],   2, '.', ''),
        number_format($s['max_price'],   2, '.', ''),
    ], $stats);

    csvOut(
        'driveway_stats_' . date('Y-m-d') . '.csv',
        ['Категория','Кол-во товаров','Общий остаток','Нет в наличии','Заканчиваются (<5)','Стоимость склада (₽)','Средняя цена (₽)','Мин. цена (₽)','Макс. цена (₽)'],
        $rows
    );
}

header('Location: index.php');

<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if (!isLoggedIn() || empty($_SESSION['user_is_admin'])) {
    header('Location: ../index.php'); exit;
}

$db = Database::getInstance()->getConnection();

$totalProducts = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalUsers    = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_admin = 0')->fetchColumn();
$outOfStock    = (int)$db->query('SELECT COUNT(*) FROM products WHERE quantity = 0')->fetchColumn();
$lowStock      = (int)$db->query('SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity < 5')->fetchColumn();
$totalValue    = (float)$db->query('SELECT COALESCE(SUM(price * quantity), 0) FROM products')->fetchColumn();
$inStock       = $totalProducts - $outOfStock;

$totalOrders   = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue  = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM orders")->fetchColumn();
$pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

$recentOrdersRaw = $db->query("
    SELECT o.id, o.status, o.total, o.created_at, o.address, o.comment,
           TRIM(CONCAT(COALESCE(NULLIF(u.firstname,''),''),' ',COALESCE(NULLIF(u.lastname,''),''))) AS uname,
           u.full_name, u.email, u.phone, u.avatar
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$recentOrders = []; $orderIds = [];
foreach ($recentOrdersRaw as $o) {
    $o['display_name'] = trim($o['uname']) ?: ($o['full_name'] ?: $o['email']);
    $recentOrders[$o['id']] = $o;
    $recentOrders[$o['id']]['items'] = [];
    $orderIds[] = $o['id'];
}
if ($orderIds) {
    $ph = implode(',', array_fill(0, count($orderIds), '?'));
    $st = $db->prepare("SELECT order_id, name, article, price, quantity FROM order_items WHERE order_id IN ($ph) ORDER BY order_id, id");
    $st->execute($orderIds);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $item) {
        if (isset($recentOrders[$item['order_id']])) {
            $recentOrders[$item['order_id']]['items'][] = $item;
        }
    }
}

function orderStatusInfo(string $s): array {
    return match($s) {
        'pending'   => ['Новый',       '#f59e0b'],
        'confirmed' => ['Подтверждён', '#3b82f6'],
        'shipped'   => ['Отправлен',   '#8b5cf6'],
        'delivered' => ['Доставлен',   '#22c55e'],
        'cancelled' => ['Отменён',     '#ef4444'],
        default     => [$s,            '#6b7280'],
    };
}

$byCategory = $db->query('
    SELECT c.name, COUNT(p.id) as cnt, COALESCE(SUM(p.price * p.quantity), 0) as val
    FROM categories c LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name ORDER BY cnt DESC
')->fetchAll();
$maxCnt = max(array_column($byCategory, 'cnt') ?: [1]) ?: 1;

$lowStockList = $db->query('
    SELECT p.name, p.article, p.quantity, c.name as cat
    FROM products p JOIN categories c ON c.id = p.category_id
    WHERE p.quantity > 0 AND p.quantity < 5
    ORDER BY p.quantity ASC LIMIT 8
')->fetchAll();

$recentUsers = $db->query('
    SELECT id, email,
           TRIM(CONCAT(COALESCE(NULLIF(firstname,""),""), " ", COALESCE(NULLIF(lastname,""),""))),
           full_name, created_at, avatar
    FROM users WHERE is_admin = 0
    ORDER BY created_at DESC LIMIT 6
')->fetchAll(PDO::FETCH_NUM);

$recentUsersFmt = [];
foreach ($recentUsers as $u) {
    $name = trim($u[2]) ?: $u[3] ?: '—';
    $recentUsersFmt[] = ['email' => $u[1], 'name' => $name, 'created_at' => $u[4], 'avatar' => $u[5]];
}

function fmtMoney(float $v): string { return number_format($v, 0, '.', ' ') . ' ₽'; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$adminName = trim($_SESSION['user_firstname'] . ' ' . $_SESSION['user_lastname']) ?: 'Администратор';
$adminInit = strtoupper(substr($_SESSION['user_firstname'] ?: 'A', 0, 1));
$adminAvatarRow = $db->prepare("SELECT avatar FROM users WHERE id = ?");
$adminAvatarRow->execute([$_SESSION['user_id']]);
$adminAvatar = $adminAvatarRow->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>DrivewayMarket - admin</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23ef4444' d='M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.92c.04-.33.07-.67.07-1s-.03-.68-.07-1l2.16-1.68c.19-.15.24-.42.12-.64l-2.05-3.55c-.13-.22-.38-.3-.61-.22l-2.55 1.03c-.52-.4-1.08-.73-1.69-.98l-.38-2.71C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.71c-.61.25-1.17.58-1.69.98L4.89 5.08c-.23-.09-.48 0-.61.22L2.23 8.85c-.12.22-.07.49.12.64L4.51 11.17c-.04.33-.07.67-.07 1s.03.68.07 1L2.35 14.85c-.19.15-.24.42-.12.64l2.05 3.55c.13.22.38.3.61.22l2.55-1.03c.52.4 1.08.73 1.69.98l.38 2.71c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.71c.61-.25 1.17-.58 1.69-.98l2.55 1.03c.23.09.48 0 .61-.22l2.05-3.55c.12-.22.07-.49-.12-.64l-2.16-1.68z'/%3E%3C/svg%3E">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
<div class="admin-wrap">

<!-- Сайдбар -->
<aside class="admin-sb">
    <div class="sb-logo">
        <div class="sb-logo-icon" style="background:transparent;padding:0;display:flex;align-items:center;justify-content:center;">
            <img src="../img/smLogo1.png" width="38" height="38" style="object-fit:contain;">
        </div>
        <div>
            <div class="sb-logo-name">DrivewayMarket</div>
            <div class="sb-logo-sub">Панель управления</div>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-section">Главное</div>
        <a href="index.php" class="sb-item active"><i class="fas fa-chart-line"></i> Дашборд</a>
        <div class="sb-section">Каталог</div>
        <a href="products.php" class="sb-item"><i class="fas fa-boxes"></i> Товары</a>
        <div class="sb-section">Контент</div>
        <a href="reviews.php" class="sb-item"><i class="fas fa-star"></i> Отзывы и вопросы</a>
        <div class="sb-section">Экспорт</div>
        <a href="export.php?type=products" class="sb-item"><i class="fas fa-file-excel"></i> Экспорт товаров</a>
        <a href="export.php?type=users" class="sb-item"><i class="fas fa-file-csv"></i> Экспорт пользователей</a>
        <a href="export.php?type=stats" class="sb-item"><i class="fas fa-chart-bar"></i> Экспорт статистики</a>
        <div class="sb-section">Прочее</div>
<a href="../index.php" class="sb-item"><i class="fas fa-external-link-alt"></i> Перейти на сайт</a>
    </nav>
    <div class="sb-footer">
        <div class="sb-user">
            <?php if (!empty($adminAvatar)): ?>
            <img src="../<?= h($adminAvatar) ?>" class="sb-avatar" style="object-fit:cover;padding:0;">
            <?php else: ?>
            <div class="sb-avatar"><?= $adminInit ?></div>
            <?php endif; ?>
            <div>
                <div class="sb-uname"><?= h($adminName) ?></div>
                <div class="sb-urole">Администратор</div>
            </div>
        </div>
        <a href="../api/auth.php?action=logout" class="sb-logout">
            <i class="fas fa-sign-out-alt"></i> Выйти
        </a>
    </div>
</aside>

<!-- Основной контент -->
<div class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="tb-title">Дашборд</div>
            <div class="tb-sub"><?= date('d F Y') ?></div>
        </div>
        <div class="tb-right">
            <a href="products.php?modal=add" class="btn btn-primary"><i class="fas fa-plus"></i> Добавить товар</a>
        </div>
    </header>

    <main class="admin-content">

        <!-- Сводные показатели магазина -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                <div>
                    <div class="stat-val"><?= $totalProducts ?></div>
                    <div class="stat-label">Всего товаров</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-val"><?= $totalUsers ?></div>
                    <div class="stat-label">Пользователей</div>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $outOfStock ?></div>
                    <div class="stat-label">Нет в наличии</div>
                </div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-val"><?= $lowStock ?></div>
                    <div class="stat-label">Заканчиваются (&lt; 5 шт.)</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color:#8b5cf6;">
                <div class="stat-icon" style="background:rgba(139,92,246,.1);color:#8b5cf6;"><i class="fas fa-shopping-bag"></i></div>
                <div>
                    <div class="stat-val"><?= $totalOrders ?></div>
                    <div class="stat-label">Всего заказов</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color:#06b6d4;">
                <div class="stat-icon" style="background:rgba(6,182,212,.1);color:#06b6d4;"><i class="fas fa-ruble-sign"></i></div>
                <div>
                    <div class="stat-val"><?= fmtMoney($totalRevenue) ?></div>
                    <div class="stat-label">Общая выручка</div>
                </div>
            </div>
        </div>

        <div class="dash-grid">

            <div class="admin-card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Товары по категориям</div>
                    <a href="export.php?type=stats" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Скачать</a>
                </div>
                <div class="card-body">
                    <div class="cat-bars">
                        <?php foreach ($byCategory as $row): ?>
                        <div class="cat-bar-row">
                            <div class="cat-bar-label">
                                <?= h($row['name']) ?>
                                <span><?= $row['cnt'] ?> позиций · <?= fmtMoney($row['val']) ?></span>
                            </div>
                            <div class="cat-bar-track">
                                <div class="cat-bar-fill" style="width:<?= $maxCnt > 0 ? round($row['cnt']/$maxCnt*100) : 0 ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-ruble-sign"></i> Склад</div>
                </div>
                <div class="card-body" style="display:flex;align-items:center;justify-content:center;padding:36px 24px;">
                    <div style="text-align:center;width:100%;">
                        <div style="font-size:38px;font-weight:900;color:var(--primary);margin-bottom:6px;"><?= fmtMoney($totalValue) ?></div>
                        <div style="font-size:13px;color:var(--text-2);">Суммарная стоимость остатков</div>
                        <div style="display:flex;gap:32px;justify-content:center;margin-top:28px;">
                            <div style="text-align:center;">
                                <div style="font-size:26px;font-weight:800;color:var(--success);"><?= $inStock ?></div>
                                <div style="font-size:12px;color:var(--text-2);">Позиций в наличии</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:26px;font-weight:800;color:var(--danger);"><?= $outOfStock ?></div>
                                <div style="font-size:12px;color:var(--text-2);">Нет в наличии</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:26px;font-weight:800;color:var(--warning);"><?= $lowStock ?></div>
                                <div style="font-size:12px;color:var(--text-2);">Заканчиваются</div>
                            </div>
                        </div>
                        <div style="margin-top:24px;">
                            <a href="export.php?type=products" class="btn btn-primary" style="margin-right:8px;"><i class="fas fa-download"></i> Экспорт товаров</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-exclamation-triangle"></i> Заканчивается</div>
                    <a href="products.php" class="btn btn-secondary btn-sm">Все товары</a>
                </div>
                <?php if (empty($lowStockList)): ?>
                <div class="empty-state"><i class="fas fa-check-circle" style="color:var(--success);opacity:1;"></i><p>Все товары в достаточном количестве</p></div>
                <?php else: ?>
                <div class="tbl-wrap">
                    <table class="admin-tbl">
                        <thead><tr><th>Товар</th><th>Артикул</th><th>Кол-во</th></tr></thead>
                        <tbody>
                            <?php foreach ($lowStockList as $r): ?>
                            <tr>
                                <td><?= h($r['name']) ?><br><small style="color:var(--text-2)"><?= h($r['cat']) ?></small></td>
                                <td><code style="font-size:11px;"><?= h($r['article']) ?></code></td>
                                <td><span class="badge badge-danger"><?= $r['quantity'] ?> шт.</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="admin-card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-user-plus"></i> Новые пользователи</div>
                    <a href="export.php?type=users" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Экспорт</a>
                </div>
                <?php if (empty($recentUsersFmt)): ?>
                <div class="empty-state"><i class="fas fa-users"></i><p>Нет зарегистрированных пользователей</p></div>
                <?php else: ?>
                <div class="tbl-wrap">
                    <table class="admin-tbl">
                        <thead><tr><th>Пользователь</th><th>Email</th><th>Дата</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentUsersFmt as $u): ?>
                            <tr>
                                <td>
                                    <div class="u-row">
                                        <?php if (!empty($u['avatar'])): ?>
                                        <img src="../<?= h($u['avatar']) ?>" class="u-avatar" style="object-fit:cover;padding:0;">
                                        <?php else: ?>
                                        <div class="u-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                        <?= h($u['name']) ?>
                                    </div>
                                </td>
                                <td style="font-size:12px;"><?= h($u['email']) ?></td>
                                <td style="font-size:12px;white-space:nowrap;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="admin-card" style="margin-top:24px;">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-shopping-bag"></i> Заказы пользователей</div>
                <a href="export.php?type=users" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Экспорт</a>
            </div>
            <?php if (empty($recentOrders)): ?>
            <div class="empty-state"><i class="fas fa-shopping-bag"></i><p>Заказов пока нет</p></div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="admin-tbl" id="ordersTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Покупатель</th>
                            <th>Сумма</th>
                            <th>Дата</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order):
                        $initials = strtoupper(substr($order['display_name'], 0, 1) ?: '?');
                    ?>
                        <tr class="order-main-row" onclick="toggleOrder(<?= $order['id'] ?>)" style="cursor:pointer;" title="Нажмите чтобы посмотреть состав заказа">
                            <td style="color:var(--text-2);font-size:12px;">#<?= $order['id'] ?></td>
                            <td>
                                <div class="u-row">
                                    <?php if (!empty($order['avatar'])): ?>
                                    <img src="../<?= h($order['avatar']) ?>" class="u-avatar" style="object-fit:cover;padding:0;">
                                    <?php else: ?>
                                    <div class="u-avatar" style="background:var(--bg-2);border:2px solid var(--border);color:var(--text-2);"><i class="fas fa-user"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600;"><?= h($order['display_name']) ?></div>
                                        <div style="font-size:11px;color:var(--text-2);"><?= h($order['email']) ?><?= $order['phone'] ? ' · ' . h($order['phone']) : '' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:700;"><?= fmtMoney((float)$order['total']) ?></td>
                            <td style="font-size:12px;color:var(--text-2);white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                            <td style="text-align:center;">
                                <span id="arrow-<?= $order['id'] ?>" style="font-size:12px;color:var(--text-2);transition:transform .2s;display:inline-block;">▼</span>
                            </td>
                        </tr>
                        <tr id="order-detail-<?= $order['id'] ?>" style="display:none;">
                            <td colspan="6" style="padding:0;">
                                <div style="background:var(--bg-2,#f8f9fa);border-top:1px solid var(--border);padding:16px 24px;">

                                    <div style="display:flex;gap:32px;flex-wrap:wrap;margin-bottom:14px;font-size:13px;">
                                        <div><span style="color:var(--text-2);">Email:</span> <strong><?= h($order['email']) ?></strong></div>
                                        <?php if ($order['phone']): ?>
                                        <div><span style="color:var(--text-2);">Телефон:</span> <strong><?= h($order['phone']) ?></strong></div>
                                        <?php endif; ?>
                                        <?php if ($order['address']): ?>
                                        <div><span style="color:var(--text-2);">Адрес доставки:</span> <strong><?= h($order['address']) ?></strong></div>
                                        <?php endif; ?>
                                        <?php if ($order['comment']): ?>
                                        <div><span style="color:var(--text-2);">Комментарий:</span> <em><?= h($order['comment']) ?></em></div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (empty($order['items'])): ?>
                                    <div style="color:var(--text-2);font-size:13px;">Позиции заказа не найдены</div>
                                    <?php else: ?>
                                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <th style="text-align:left;padding:6px 8px;color:var(--text-2);font-weight:600;">Товар</th>
                                                <th style="text-align:left;padding:6px 8px;color:var(--text-2);font-weight:600;">Артикул</th>
                                                <th style="text-align:center;padding:6px 8px;color:var(--text-2);font-weight:600;">Кол-во</th>
                                                <th style="text-align:right;padding:6px 8px;color:var(--text-2);font-weight:600;">Цена</th>
                                                <th style="text-align:right;padding:6px 8px;color:var(--text-2);font-weight:600;">Итого</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <td style="padding:7px 8px;"><?= h($item['name']) ?></td>
                                                <td style="padding:7px 8px;"><code style="font-size:11px;"><?= h($item['article']) ?></code></td>
                                                <td style="padding:7px 8px;text-align:center;"><?= (int)$item['quantity'] ?> шт.</td>
                                                <td style="padding:7px 8px;text-align:right;"><?= fmtMoney((float)$item['price']) ?></td>
                                                <td style="padding:7px 8px;text-align:right;font-weight:700;"><?= fmtMoney((float)$item['price'] * (int)$item['quantity']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" style="padding:8px 8px;text-align:right;font-weight:700;color:var(--text-2);">Итого по заказу:</td>
                                                <td style="padding:8px 8px;text-align:right;font-weight:900;font-size:15px;"><?= fmtMoney((float)$order['total']) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>
</div>
<script>
function toggleOrder(id) {
    const row   = document.getElementById('order-detail-' + id);
    const arrow = document.getElementById('arrow-' + id);
    const open  = row.style.display === 'table-row';
    row.style.display   = open ? 'none' : 'table-row';
    arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}
</script>
</body>
</html>

<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if (!isLoggedIn() || empty($_SESSION['user_is_admin'])) {
    header('Location: ../index.php'); exit;
}

$db = Database::getInstance()->getConnection();

$perPage    = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$search     = trim($_GET['q'] ?? '');
$catFilter  = (int)($_GET['cat'] ?? 0);
$brandFilter= (int)($_GET['brand'] ?? 0);

$where = []; $params = [];
if ($search) {
    $where[] = '(p.name LIKE ? OR p.article LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($catFilter)   { $where[] = 'p.category_id = ?'; $params[] = $catFilter; }
if ($brandFilter) { $where[] = 'p.brand_id = ?';    $params[] = $brandFilter; }
$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total    = (int)$db->prepare("SELECT COUNT(*) FROM products p $ws")->execute($params) ?: 0;
$stCount  = $db->prepare("SELECT COUNT(*) FROM products p $ws");
$stCount->execute($params);
$total    = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);

$stProd = $db->prepare("
    SELECT p.*, c.name cat_name, b.name brand_name, m.name model_name, pt.name type_name
    FROM products p
    LEFT JOIN categories c   ON c.id  = p.category_id
    LEFT JOIN car_brands b   ON b.id  = p.brand_id
    LEFT JOIN car_models m   ON m.id  = p.model_id
    LEFT JOIN product_types pt ON pt.id = p.type_id
    $ws ORDER BY p.id DESC LIMIT $perPage OFFSET $offset
");
$stProd->execute($params);
$products = $stProd->fetchAll();

$categories = $db->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$allBrands  = $db->query('SELECT id, name FROM car_brands ORDER BY name')->fetchAll();
$allTypes   = $db->query('SELECT id, category_id, name FROM product_types ORDER BY name')->fetchAll();
$allModels  = $db->query('SELECT id, brand_id, name FROM car_models ORDER BY name')->fetchAll();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function qstr(array $extra = []): string {
    $base = ['q' => $_GET['q'] ?? '', 'cat' => $_GET['cat'] ?? '', 'brand' => $_GET['brand'] ?? ''];
    return http_build_query(array_merge($base, $extra));
}

$adminName = trim($_SESSION['user_firstname'] . ' ' . $_SESSION['user_lastname']) ?: 'Администратор';
$adminInit = strtoupper(substr($_SESSION['user_firstname'] ?: 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Товары — Driveway Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
<div class="admin-wrap">

<aside class="admin-sb">
    <div class="sb-logo">
        <div class="sb-logo-icon"><i class="fas fa-car-side"></i></div>
        <div>
            <div class="sb-logo-name">Driveway</div>
            <div class="sb-logo-sub">Панель управления</div>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-section">Главное</div>
        <a href="index.php" class="sb-item"><i class="fas fa-chart-line"></i> Дашборд</a>
        <div class="sb-section">Каталог</div>
        <a href="products.php" class="sb-item active"><i class="fas fa-boxes"></i> Товары</a>
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
            <div class="sb-avatar"><?= $adminInit ?></div>
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

<div class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="tb-title">Товары</div>
            <div class="tb-sub">Всего: <?= $total ?> позиций</div>
        </div>
        <div class="tb-right">
            <a href="export.php?type=products" class="btn btn-secondary"><i class="fas fa-download"></i> Экспорт</a>
            <button class="btn btn-primary" onclick="PM.open('add')"><i class="fas fa-plus"></i> Добавить товар</button>
        </div>
    </header>

    <main class="admin-content">

        <form method="GET" class="filters-bar">
            <input type="text" name="q" class="f-input" placeholder="🔍  Поиск по названию или артикулу" value="<?= h($search) ?>">
            <select name="cat" class="f-select">
                <option value="">Все категории</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="brand" class="f-select">
                <option value="">Все марки</option>
                <?php foreach ($allBrands as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $brandFilter == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Применить</button>
            <?php if ($search || $catFilter || $brandFilter): ?>
            <a href="products.php" class="btn btn-outline">Сбросить</a>
            <?php endif; ?>
        </form>

        <div class="admin-card">
            <?php if (empty($products)): ?>
            <div class="empty-state"><i class="fas fa-box-open"></i><p>Товары не найдены</p></div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="admin-tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Товар</th>
                            <th>Категория / Тип</th>
                            <th>Марка / Модель</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="color:var(--text-2);font-size:12px;">#<?= $p['id'] ?></td>
                        <td>
                            <div class="prod-info">
                                <div class="prod-thumb">
                                    <?php if ($p['image'] && file_exists('../' . $p['image'])): ?>
                                    <img src="../<?= h($p['image']) ?>" alt="">
                                    <?php else: ?>
                                    <i class="fas fa-cog"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="prod-name"><?= h($p['name']) ?></div>
                                    <div class="prod-art"><?= h($p['article']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px;"><?= h($p['cat_name'] ?? '—') ?></div>
                            <div style="font-size:11px;color:var(--text-2);"><?= h($p['type_name'] ?? '—') ?></div>
                        </td>
                        <td>
                            <div style="font-size:13px;"><?= h($p['brand_name'] ?? '—') ?></div>
                            <div style="font-size:11px;color:var(--text-2);"><?= h($p['model_name'] ?? '—') ?></div>
                        </td>
                        <td style="font-weight:700;white-space:nowrap;"><?= number_format($p['price'], 0, '.', ' ') ?> ₽</td>
                        <td>
                            <?php if ($p['quantity'] == 0): ?>
                            <span class="badge badge-danger"><?= $p['quantity'] ?></span>
                            <?php elseif ($p['quantity'] < 5): ?>
                            <span class="badge badge-warning"><?= $p['quantity'] ?></span>
                            <?php else: ?>
                            <span class="badge badge-success"><?= $p['quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['quantity'] > 0): ?>
                            <span class="badge badge-success">В наличии</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Нет</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="tbl-actions">
                                <button class="btn-icon edit" onclick="PM.open('edit', <?= $p['id'] ?>)" title="Редактировать">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn-icon delete" onclick="DC.open(<?= $p['id'] ?>, '<?= h(addslashes($p['name'])) ?>')" title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div class="pg-info">Страница <?= $page ?> из <?= $totalPages ?> (<?= $total ?> товаров)</div>
                <div class="pg-pages">
                    <a href="?<?= qstr(['page' => $page - 1]) ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>" <?= $page <= 1 ? 'tabindex="-1"' : '' ?>>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1) echo '<span class="pg-btn" style="cursor:default;">…</span>';
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?= qstr(['page' => $i]) ?>" class="pg-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $totalPages) echo '<span class="pg-btn" style="cursor:default;">…</span>'; ?>
                    <a href="?<?= qstr(['page' => $page + 1]) ?>" class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" <?= $page >= $totalPages ? 'tabindex="-1"' : '' ?>>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</div>

<div id="productModal" class="a-overlay" style="display:none;">
    <div class="a-modal">
        <div class="modal-head">
            <div class="modal-title" id="pm-title">Добавить товар</div>
            <button class="modal-close" onclick="PM.close()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="pm-form" enctype="multipart/form-data">

                <div id="pm-error" class="a-alert error" style="display:none;"></div>

                <div class="fg2">
                    <div class="fg">
                        <label>Название <span style="color:var(--primary)">*</span></label>
                        <input type="text" id="pm-name" name="name" class="fc" placeholder="Масляный фильтр Premium">
                    </div>
                    <div class="fg">
                        <label>Артикул <span style="color:var(--primary)">*</span></label>
                        <input type="text" id="pm-article" name="article" class="fc" placeholder="100100100001">
                    </div>
                </div>

                <div class="fg2">
                    <div class="fg">
                        <label>Категория <span style="color:var(--primary)">*</span></label>
                        <select id="pm-category" name="category_id" class="fc">
                            <option value="">— Выберите —</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Тип товара <span style="color:var(--primary)">*</span></label>
                        <select id="pm-type" name="type_id" class="fc">
                            <option value="">— Сначала выберите категорию —</option>
                        </select>
                    </div>
                </div>

                <div class="fg2">
                    <div class="fg">
                        <label>Марка <span style="color:var(--primary)">*</span></label>
                        <select id="pm-brand" name="brand_id" class="fc">
                            <option value="">— Выберите —</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Модель автомобиля <span style="color:var(--primary)">*</span></label>
                        <select id="pm-model" name="model_id" class="fc">
                            <option value="">— Сначала выберите марку —</option>
                        </select>
                    </div>
                </div>

                <div class="fg3">
                    <div class="fg">
                        <label>Цена (₽) <span style="color:var(--primary)">*</span></label>
                        <input type="number" id="pm-price" name="price" class="fc" min="0" step="0.01" placeholder="1500">
                    </div>
                    <div class="fg">
                        <label>Количество <span style="color:var(--primary)">*</span></label>
                        <input type="number" id="pm-quantity" name="quantity" class="fc" min="0" placeholder="10">
                    </div>
                    <div class="fg">
                        <label>Модель авто (текст)</label>
                        <input type="text" id="pm-car-model" name="car_model" class="fc" placeholder="LADA">
                    </div>
                </div>

                <div class="fg">
                    <label>Описание</label>
                    <textarea id="pm-description" name="description" class="fc ta" placeholder="Краткое описание товара..."></textarea>
                </div>

                <div class="fg">
                    <label>Фотографии <span style="font-weight:400;color:var(--text-2);">до 4 шт. · первое — главное</span></label>
                    <div class="img-slots" id="pm-img-slots"></div>
                    <input type="file" id="pm-img-pick" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
                    <div class="hint" style="margin-top:6px;">JPG, PNG, WebP · макс. 5 МБ каждое</div>
                </div>

            </form>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" onclick="PM.close()">Отмена</button>
            <button class="btn btn-primary" id="pm-save-btn" onclick="PM.save()">
                <i class="fas fa-save"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<div id="confirmModal" class="a-overlay" style="display:none;">
    <div class="a-modal narrow">
        <div class="modal-head">
            <div class="modal-title">Удаление товара</div>
            <button class="modal-close" onclick="DC.close()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body confirm-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h3>Удалить товар?</h3>
            <p id="dc-name" style="font-weight:600;margin-top:8px;color:var(--text);"></p>
            <p style="margin-top:6px;">Это действие нельзя отменить.</p>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" onclick="DC.close()">Отмена</button>
            <button class="btn btn-danger" id="dc-confirm-btn" onclick="DC.confirm()">
                <i class="fas fa-trash"></i> Удалить
            </button>
        </div>
    </div>
</div>

<script>
const PHP_CATS   = <?= json_encode(array_values($categories)) ?>;
const PHP_BRANDS = <?= json_encode(array_values($allBrands)) ?>;
const PHP_TYPES  = <?= json_encode(array_values($allTypes)) ?>;
const PHP_MODELS = <?= json_encode(array_values($allModels)) ?>;

const PM = (() => {
    let mode = 'add', editId = null;
    const $ = id => document.getElementById(id);

    function open(m, id = null) {
        mode = m; editId = id;
        resetForm();
        $('pm-title').textContent = m === 'add' ? 'Добавить товар' : 'Редактировать товар';
        $('productModal').style.display = 'flex';
        if (m === 'edit' && id) loadProduct(id);
    }

    function close() {
        $('productModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    let imgExisting = [];
    let imgNewFiles  = [];
    let imgToDelete  = [];

    function renderImgSlots() {
        const wrap = $('pm-img-slots');
        if (!wrap) return;
        wrap.innerHTML = '';
        const total = imgExisting.length + imgNewFiles.length;

        imgExisting.forEach((img, i) => {
            const slot = document.createElement('div');
            slot.className = 'img-slot filled';
            const src = `../${img.filename}`;
            slot.innerHTML = `
                <img src="${src}" alt="">
                ${i === 0 ? '<span class="img-slot-primary">★</span>' : ''}
                <button type="button" class="img-slot-del" data-type="existing" data-idx="${i}" title="Удалить">×</button>`;
            wrap.appendChild(slot);
        });

        imgNewFiles.forEach((file, i) => {
            const slot = document.createElement('div');
            slot.className = 'img-slot filled';
            const globalIdx = imgExisting.length + i;
            const reader = new FileReader();
            reader.onload = e => slot.querySelector('img').src = e.target.result;
            reader.readAsDataURL(file);
            slot.innerHTML = `
                <img src="" alt="">
                ${globalIdx === 0 ? '<span class="img-slot-primary">★</span>' : ''}
                <button type="button" class="img-slot-del" data-type="new" data-idx="${i}" title="Удалить">×</button>`;
            wrap.appendChild(slot);
        });

        if (total < 4) {
            const add = document.createElement('div');
            add.className = 'img-slot empty';
            add.title = 'Добавить фото';
            add.innerHTML = '<i class="fas fa-plus"></i>';
            add.addEventListener('click', () => $('pm-img-pick').click());
            wrap.appendChild(add);
        }

        wrap.querySelectorAll('.img-slot-del').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const type = btn.dataset.type;
                const idx  = parseInt(btn.dataset.idx);
                if (type === 'existing') {
                    imgToDelete.push(imgExisting[idx].id);
                    imgExisting.splice(idx, 1);
                } else {
                    imgNewFiles.splice(idx, 1);
                }
                renderImgSlots();
            });
        });
    }

    function resetForm() {
        $('pm-form').reset();
        imgExisting = []; imgNewFiles = []; imgToDelete = [];
        renderImgSlots();
        $('pm-error').style.display = 'none';
        refreshTypes(0);
        refreshModels(0);
        fillSelect('pm-category', PHP_CATS,   'id', 'name');
        fillSelect('pm-brand',    PHP_BRANDS, 'id', 'name');
    }

    function fillSelect(selId, items, vk, lk, selected = null) {
        const sel = $(selId);
        sel.innerHTML = '<option value="">— Выберите —</option>';
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = item[vk]; o.textContent = item[lk];
            if (selected != null && item[vk] == selected) o.selected = true;
            sel.appendChild(o);
        });
    }

    function refreshTypes(catId, selected = null) {
        const types = PHP_TYPES.filter(t => t.category_id == catId);
        const sel = $('pm-type');
        sel.innerHTML = types.length
            ? '<option value="">— Выберите —</option>'
            : '<option value="">— Сначала выберите категорию —</option>';
        types.forEach(t => {
            const o = document.createElement('option');
            o.value = t.id; o.textContent = t.name;
            if (selected != null && t.id == selected) o.selected = true;
            sel.appendChild(o);
        });
    }

    function refreshModels(brandId, selected = null) {
        const models = PHP_MODELS.filter(m => m.brand_id == brandId);
        const sel = $('pm-model');
        sel.innerHTML = models.length
            ? '<option value="">— Выберите —</option>'
            : '<option value="">— Сначала выберите марку —</option>';
        models.forEach(m => {
            const o = document.createElement('option');
            o.value = m.id; o.textContent = m.name;
            if (selected != null && m.id == selected) o.selected = true;
            sel.appendChild(o);
        });
    }

    async function loadProduct(id) {
        try {
            const res = await fetch(`api.php?action=get_product&id=${id}`).then(r => r.json());
            if (!res.success) { showError('Не удалось загрузить данные товара'); return; }
            const p = res.product;
            $('pm-name').value        = p.name        || '';
            $('pm-article').value     = p.article     || '';
            $('pm-price').value       = p.price       || '';
            $('pm-quantity').value    = p.quantity     ?? '';
            $('pm-description').value = p.description || '';
            $('pm-car-model').value   = p.car_model   || '';
            fillSelect('pm-category', PHP_CATS, 'id', 'name', p.category_id);
            refreshTypes(p.category_id, p.type_id);
            fillSelect('pm-brand', PHP_BRANDS, 'id', 'name', p.brand_id);
            refreshModels(p.brand_id, p.model_id);
            imgExisting = (res.images || []);
            imgNewFiles  = []; imgToDelete = [];
            renderImgSlots();
        } catch(e) {
            showError('Ошибка загрузки данных товара');
        }
    }

    async function save() {
        const fd = new FormData($('pm-form'));
        fd.append('action', mode === 'add' ? 'create_product' : 'update_product');
        if (mode === 'edit') fd.append('id', editId);

        imgNewFiles.forEach(f => fd.append('images[]', f));
        imgToDelete.forEach(id => fd.append('delete_images[]', id));

        const btn = $('pm-save-btn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
        $('pm-error').style.display = 'none';

        try {
            const res = await fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) { close(); location.reload(); }
            else showError(res.message || 'Ошибка сохранения');
        } catch(e) {
            showError('Ошибка сети, попробуйте ещё раз');
        }
        btn.disabled = false; btn.innerHTML = orig;
    }

    function showError(msg) {
        const el = $('pm-error');
        el.textContent = msg; el.style.display = 'flex';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('pm-category').addEventListener('change', e => refreshTypes(e.target.value));
        $('pm-brand').addEventListener('change', e => refreshModels(e.target.value));

        $('pm-img-pick').addEventListener('change', function() {
            const remaining = 4 - imgExisting.length - imgNewFiles.length;
            const toAdd = Array.from(this.files).slice(0, remaining);
            imgNewFiles = [...imgNewFiles, ...toAdd];
            this.value = '';
            renderImgSlots();
        });
        renderImgSlots();

        $('productModal').addEventListener('click', e => { if (e.target.id === 'productModal') close(); });
        $('confirmModal').addEventListener('click', e => { if (e.target.id === 'confirmModal') DC.close(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { close(); DC.close(); } });

        if (new URLSearchParams(location.search).get('modal') === 'add') open('add');
    });

    return { open, close, save };
})();

const DC = {
    id: null,
    open(id, name) {
        this.id = id;
        document.getElementById('dc-name').textContent = name;
        document.getElementById('confirmModal').style.display = 'flex';
    },
    close() {
        document.getElementById('confirmModal').style.display = 'none';
        this.id = null;
    },
    async confirm() {
        const btn = document.getElementById('dc-confirm-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Удаление...';
        const fd = new FormData();
        fd.append('action', 'delete_product');
        fd.append('id', this.id);
        try {
            const res = await fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) { this.close(); location.reload(); }
            else alert(res.message || 'Ошибка удаления');
        } catch(e) { alert('Ошибка сети'); }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Удалить';
    }
};
</script>
</body>
</html>

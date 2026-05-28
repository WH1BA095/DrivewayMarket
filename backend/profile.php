<?php
require_once 'config/auth.php';

if (!isLoggedIn()) {
    requireLogin('index.php');
}

require_once 'config/db.php';
$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) { logoutUser(); header('Location: index.php?auth=login'); exit; }

// обратная совместимость: full_name → firstname + lastname
if (empty($user['firstname']) && !empty($user['full_name'])) {
    $parts             = explode(' ', trim($user['full_name']), 2);
    $user['firstname'] = $parts[0] ?? '';
    $user['lastname']  = $parts[1] ?? '';
}

$memberYear = date('Y', strtotime($user['created_at']));
$fullName   = trim($user['firstname'] . ' ' . $user['lastname']) ?: 'Пользователь';
$avatar     = $user['avatar'] ?? null;

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<?php require_once 'includes/header.php'; ?>

<main class="main-content">
    <div class="page-container">

        <div class="breadcrumbs">
            <a href="index.php">Главная</a>
            <span class="breadcrumb-separator">›</span>
            <span class="current">Личный кабинет</span>
        </div>

        <h1 class="page-title"><i class="fas fa-user-circle"></i> Личный кабинет</h1>

        <div class="profile-layout">

            <aside class="profile-sidebar">
                <div class="cart-card profile-avatar-card">

                    <div class="avatar-upload-wrap">
                        <label for="avatar-file-input" class="avatar-upload-label" title="Нажмите, чтобы изменить фото">
                            <?php if ($avatar && file_exists($avatar)): ?>
                            <img src="<?= h($avatar) ?>" class="profile-avatar-img" id="avatar-preview-img" alt="">
                            <?php else: ?>
                            <i class="fas fa-user-circle profile-avatar-icon" id="avatar-preview-icon"></i>
                            <?php endif; ?>
                            <div class="avatar-upload-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Изменить</span>
                            </div>
                        </label>
                        <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <div id="avatar-upload-msg" class="avatar-upload-msg" style="display:none;"></div>
                    </div>

                    <div class="profile-name"><?= h($fullName) ?></div>
                    <div class="profile-email"><?= h($user['email']) ?></div>
                    <div class="profile-member-since">Клиент с <?= h($memberYear) ?> года</div>
                </div>

                <div class="cart-card profile-nav-card">
                    <nav class="profile-nav">
                        <a href="#tab-info"      class="profile-nav-item active" data-tab="tab-info">
                            <i class="fas fa-id-card"></i> Мои данные
                        </a>
                        <a href="#tab-favorites" class="profile-nav-item" data-tab="tab-favorites">
                            <i class="fas fa-heart"></i> Избранное
                            <span id="fav-nav-badge" class="profile-cart-badge" style="display:none;"></span>
                        </a>
                        <a href="#tab-orders"    class="profile-nav-item" data-tab="tab-orders">
                            <i class="fas fa-box"></i> Мои заказы
                        </a>
                        <a href="#tab-cars"      class="profile-nav-item" data-tab="tab-cars">
                            <i class="fas fa-car"></i> Мои автомобили
                        </a>
                        <a href="#tab-reviews"   class="profile-nav-item" data-tab="tab-reviews">
                            <i class="fas fa-star"></i> Мои отзывы
                            <span id="rv-nav-badge" class="profile-cart-badge" style="display:none;"></span>
                        </a>
                        <a href="#tab-pass"      class="profile-nav-item" data-tab="tab-pass">
                            <i class="fas fa-lock"></i> Безопасность
                        </a>
                        <a href="#tab-support"   class="profile-nav-item" data-tab="tab-support">
                            <i class="fas fa-comment-dots"></i> Обращения
                            <span id="sup-nav-badge" class="profile-cart-badge" style="display:none;"></span>
                        </a>
                        <a href="api/auth.php?action=logout" class="profile-nav-item" style="color:#e74c3c;">
                            <i class="fas fa-sign-out-alt"></i> Выйти
                        </a>
                    </nav>
                </div>
            </aside>

            <div class="profile-main">

                <div id="tab-info" class="profile-tab active">
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-id-card"></i> Личные данные</h2>
                        <div class="profile-form">
                            <div class="profile-form-row">
                                <div class="profile-form-group">
                                    <label>Имя</label>
                                    <input type="text" id="pf-firstname" value="<?= h($user['firstname']) ?>" class="profile-input">
                                </div>
                                <div class="profile-form-group">
                                    <label>Фамилия</label>
                                    <input type="text" id="pf-lastname" value="<?= h($user['lastname']) ?>" class="profile-input">
                                </div>
                            </div>
                            <div class="profile-form-row">
                                <div class="profile-form-group">
                                    <label>Email</label>
                                    <input type="email" id="pf-email" value="<?= h($user['email']) ?>" class="profile-input" readonly style="opacity:.7;cursor:default;">
                                </div>
                                <div class="profile-form-group">
                                    <label>Телефон</label>
                                    <input type="tel" id="pf-phone" value="<?= h($user['phone']) ?>" placeholder="+7 (___) ___-__-__" class="profile-input">
                                </div>
                            </div>
                            <div class="profile-form-group">
                                <label>Дата рождения</label>
                                <input type="date" id="pf-birthdate" value="<?= h($user['birthdate'] ?? '') ?>" class="profile-input" style="max-width:220px;">
                            </div>
                            <button class="btn-primary-lg" id="save-info-btn">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                            <div id="save-info-msg" class="profile-save-msg" style="display:none;">
                                <i class="fas fa-check-circle"></i> Данные сохранены
                            </div>
                        </div>
                    </div>

                    <div class="cart-card" style="margin-top:20px;">
                        <h2 class="profile-section-title"><i class="fas fa-map-marker-alt"></i> Адреса доставки</h2>
                        <div class="profile-form-group">
                            <label>Основной адрес</label>
                            <input type="text" id="pf-address" value="<?= h($user['address'] ?? '') ?>" placeholder="г. Москва, ул. Примерная, д. 1, кв. 10" class="profile-input">
                        </div>
                        <button class="btn-outline-lg" id="save-address-btn" style="margin-top:15px;">
                            <i class="fas fa-save"></i> Сохранить адрес
                        </button>
                        <div id="save-addr-msg" class="profile-save-msg" style="display:none;">
                            <i class="fas fa-check-circle"></i> Адрес сохранён
                        </div>
                    </div>
                </div>

                <div id="tab-favorites" class="profile-tab">
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-heart"></i> Избранные товары</h2>
                        <div id="favorites-list"><div class="profile-empty"><i class="fas fa-spinner fa-spin"></i><p>Загрузка...</p></div></div>
                    </div>
                </div>

                <div id="tab-orders" class="profile-tab">
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-box"></i> История заказов</h2>
                        <div id="orders-list"></div>
                    </div>
                </div>

                <div id="tab-cars" class="profile-tab">
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-car"></i> Мои автомобили</h2>
                        <div id="cars-list"></div>
                        <div class="profile-add-car">
                            <div class="profile-form-row">
                                <div class="profile-form-group">
                                    <label>Марка</label>
                                    <input type="text" id="car-brand" placeholder="Toyota" class="profile-input">
                                </div>
                                <div class="profile-form-group">
                                    <label>Модель</label>
                                    <input type="text" id="car-model" placeholder="Camry" class="profile-input">
                                </div>
                                <div class="profile-form-group">
                                    <label>Год</label>
                                    <input type="number" id="car-year" placeholder="2020" min="1990" max="2030" class="profile-input">
                                </div>
                            </div>
                            <button class="btn-primary-lg" id="add-car-btn">
                                <i class="fas fa-plus"></i> Добавить автомобиль
                            </button>
                        </div>
                    </div>
                </div>

                <div id="tab-reviews" class="profile-tab">
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-star"></i> Мои отзывы</h2>
                        <div id="profile-reviews-list">
                            <div class="profile-empty"><i class="fas fa-spinner fa-spin"></i><p>Загрузка...</p></div>
                        </div>
                    </div>
                    <div class="cart-card" style="margin-top:20px;">
                        <h2 class="profile-section-title"><i class="fas fa-question-circle"></i> Мои вопросы</h2>
                        <div id="profile-questions-list">
                            <div class="profile-empty"><i class="fas fa-spinner fa-spin"></i><p>Загрузка...</p></div>
                        </div>
                    </div>
                </div>

                <div id="tab-pass" class="profile-tab">
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-lock"></i> Смена пароля</h2>
                        <div class="profile-form" style="max-width:400px;">
                            <div class="profile-form-group">
                                <label>Текущий пароль</label>
                                <input type="password" id="cur-pass" placeholder="••••••••" class="profile-input">
                            </div>
                            <div class="profile-form-group">
                                <label>Новый пароль</label>
                                <input type="password" id="new-pass" placeholder="••••••••" class="profile-input">
                            </div>
                            <div class="profile-form-group">
                                <label>Повторите новый пароль</label>
                                <input type="password" id="new-pass2" placeholder="••••••••" class="profile-input">
                            </div>
                            <button class="btn-primary-lg" id="save-pass-btn">
                                <i class="fas fa-lock"></i> Изменить пароль
                            </button>
                            <div id="pass-msg" class="profile-save-msg" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="cart-card" style="margin-top:20px;">
                        <h2 class="profile-section-title"><i class="fas fa-bell"></i> Уведомления</h2>
                        <div class="profile-toggles">
                            <label class="profile-toggle-row">
                                <span>Email-уведомления о заказах</span>
                                <input type="checkbox" class="profile-toggle-cb" checked>
                            </label>
                            <label class="profile-toggle-row">
                                <span>SMS об изменении статуса</span>
                                <input type="checkbox" class="profile-toggle-cb" checked>
                            </label>
                            <label class="profile-toggle-row">
                                <span>Акции и специальные предложения</span>
                                <input type="checkbox" class="profile-toggle-cb">
                            </label>
                        </div>
                    </div>
                </div>

                <div id="tab-support" class="profile-tab">
                    <div class="cart-card" style="margin-bottom:20px;">
                        <h2 class="profile-section-title"><i class="fas fa-paper-plane"></i> Новое обращение</h2>
                        <div class="profile-form" style="max-width:540px;">
                            <div class="profile-form-group">
                                <label>Тема</label>
                                <input type="text" id="new-sup-subject" class="profile-input" placeholder="Вопрос по заказу, возврат, другое...">
                            </div>
                            <div class="profile-form-group">
                                <label>Сообщение <span class="req">*</span></label>
                                <textarea id="new-sup-message" class="profile-input" rows="5" placeholder="Опишите ваш вопрос..." style="resize:vertical;font-family:inherit;"></textarea>
                            </div>
                            <div id="new-sup-error" class="auth-error" style="display:none;"></div>
                            <div id="new-sup-ok" style="display:none;color:#22c55e;font-weight:600;padding:10px 0;"><i class="fas fa-check-circle"></i> Обращение отправлено!</div>
                            <button class="btn-primary-lg" id="new-sup-btn" onclick="sendNewSupport()">
                                <i class="fas fa-paper-plane"></i> Отправить
                            </button>
                        </div>
                    </div>
                    <div class="cart-card">
                        <h2 class="profile-section-title"><i class="fas fa-history"></i> История обращений</h2>
                        <div id="profile-support-list">
                            <div class="profile-empty"><i class="fas fa-spinner fa-spin"></i><p>Загрузка...</p></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>

<script src="js/cart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Cart.updateBadge();

    const API = 'api/auth.php';

    const tabs = document.querySelectorAll('.profile-nav-item[data-tab]');
    tabs.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const tabId = link.dataset.tab;
            document.querySelectorAll('.profile-nav-item').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            link.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            if (tabId === 'tab-favorites') loadFavorites();
            if (tabId === 'tab-reviews')   loadMyReviews();
            if (tabId === 'tab-support')   loadMySupport();
        });
    });

    const hash = location.hash.replace('#', '');
    if (hash) {
        const link = document.querySelector(`.profile-nav-item[data-tab="${hash}"]`);
        if (link) link.click();
    }

    document.getElementById('avatar-file-input').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        const msg = document.getElementById('avatar-upload-msg');
        msg.className = 'avatar-upload-msg';
        msg.textContent = 'Загрузка...';
        msg.style.display = 'block';

        const fd = new FormData();
        fd.append('action', 'upload_avatar');
        fd.append('avatar', file);

        try {
            const res = await fetch(API, { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                const label = document.querySelector('.avatar-upload-label');
                const icon  = document.getElementById('avatar-preview-icon');
                let img = document.getElementById('avatar-preview-img');
                if (icon) icon.remove();
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'avatar-preview-img';
                    img.className = 'profile-avatar-img';
                    img.alt = '';
                    label.prepend(img);
                }
                img.src = res.avatar + '?t=' + Date.now();
                msg.className = 'avatar-upload-msg success';
                msg.textContent = 'Фото обновлено!';
            } else {
                msg.className = 'avatar-upload-msg error';
                msg.textContent = res.message || 'Ошибка загрузки';
            }
        } catch(e) {
            msg.className = 'avatar-upload-msg error';
            msg.textContent = 'Ошибка сети';
        }
        setTimeout(() => msg.style.display = 'none', 3000);
    });

    async function saveProfile(extraField = null) {
        const fd = new FormData();
        fd.append('action',    'update_profile');
        fd.append('firstname', document.getElementById('pf-firstname').value);
        fd.append('lastname',  document.getElementById('pf-lastname').value);
        fd.append('phone',     document.getElementById('pf-phone').value);
        fd.append('birthdate', document.getElementById('pf-birthdate').value);
        fd.append('address',   document.getElementById('pf-address').value);
        return fetch(API, { method: 'POST', body: fd }).then(r => r.json());
    }

    document.getElementById('save-info-btn').addEventListener('click', async () => {
        const res = await saveProfile();
        flashMsg('save-info-msg', res.success, res.message || 'Ошибка сохранения');
    });
    document.getElementById('save-address-btn').addEventListener('click', async () => {
        const res = await saveProfile();
        flashMsg('save-addr-msg', res.success, res.message || 'Ошибка сохранения');
    });

    function flashMsg(id, success, errText) {
        const el = document.getElementById(id);
        if (success) {
            el.className = 'profile-save-msg';
            el.innerHTML = '<i class="fas fa-check-circle"></i> ' + (id === 'save-addr-msg' ? 'Адрес сохранён' : 'Данные сохранены');
        } else {
            el.className = 'profile-save-msg error';
            el.innerHTML = '<i class="fas fa-times-circle"></i> ' + errText;
        }
        el.style.display = 'flex';
        setTimeout(() => el.style.display = 'none', 3000);
    }

    document.getElementById('save-pass-btn').addEventListener('click', async () => {
        const msg = document.getElementById('pass-msg');
        const fd  = new FormData();
        fd.append('action',           'change_password');
        fd.append('current_password', document.getElementById('cur-pass').value);
        fd.append('new_password',     document.getElementById('new-pass').value);
        fd.append('new_password2',    document.getElementById('new-pass2').value);
        const res = await fetch(API, { method: 'POST', body: fd }).then(r => r.json());
        msg.style.display = 'flex';
        if (res.success) {
            msg.className = 'profile-save-msg';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> Пароль изменён';
            ['cur-pass','new-pass','new-pass2'].forEach(id => document.getElementById(id).value = '');
        } else {
            msg.className = 'profile-save-msg error';
            msg.innerHTML = '<i class="fas fa-times-circle"></i> ' + res.message;
        }
        setTimeout(() => msg.style.display = 'none', 3500);
    });

    const ordersList = document.getElementById('orders-list');

    // синхронизируем localStorage → БД, потом удаляем из LS
    const lsOrdersRaw = JSON.parse(localStorage.getItem('driveway_orders') || '[]');
    if (lsOrdersRaw.length > 0) {
        fetch('api/sync_orders.php', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify({ orders: lsOrdersRaw }),
        })
        .then(() => {
            localStorage.removeItem('driveway_orders');
        })
        .catch(() => {});
    }

    fetch('api/get_orders.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const orders = (res.orders || []).map(o => ({
                    id:              o.id,
                    displayNum:      o.user_order_number ?? o.id,
                    date:     new Date(o.created_at).toLocaleDateString('ru-RU'),
                    datetime: o.created_at,
                    status:   o.status,
                    total:    o.total,
                    address:  o.address,
                    comment:  o.comment,
                    items:    (o.items || []).map(i => ({
                        name:    i.name,
                        article: i.article,
                        price:   i.price,
                        qty:     i.qty ?? i.quantity,
                    })),
                    source: 'db',
                }));
                renderOrders(orders);
            } else {
                renderOrders([]);
            }
        })
        .catch(() => renderOrders([]));

    function renderOrders(orders) {

    const DELIVERY_ICONS = { pickup:'fa-store', courier:'fa-truck', post:'fa-box' };
    const PAYMENT_ICONS  = { online_sbp:'fa-mobile-alt', online_card:'fa-credit-card', at_pickup:'fa-store', cash:'fa-money-bill-wave' };

    function fmtMoney(n) { return Number(n).toLocaleString('ru-RU'); }
    function escHtml(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function renderDeliveryAddress(d) {
        if (!d) return '';
        if (d.type === 'pickup') return d.address ? `<br><small>${escHtml(d.address)}</small>` : '';
        if (d.type === 'courier') {
            let a = escHtml(d.address || '');
            if (d.apt) a += ', кв. ' + escHtml(d.apt);
            return a ? `<br><small>${a}</small>` : '';
        }
        if (d.type === 'post') {
            let a = escHtml(d.address || '');
            if (d.index) a += ', ' + escHtml(d.index);
            return a ? `<br><small>${a}</small>` : '';
        }
        return '';
    }

    if (!orders.length) {
        ordersList.innerHTML = `
            <div class="profile-empty">
                <i class="fas fa-box-open"></i>
                <p>У вас пока нет заказов</p>
                <a href="catalog.php" class="btn-primary-lg"><i class="fas fa-search"></i> Перейти в каталог</a>
            </div>`;
    } else {
        ordersList.innerHTML = orders.map(o => {
            const delIcon  = DELIVERY_ICONS[(o.delivery && o.delivery.type) || 'courier'] || 'fa-truck';
            const payIcon  = PAYMENT_ICONS[(o.payment && o.payment.type)   || 'online_card'] || 'fa-credit-card';
            const delLabel = (o.delivery && o.delivery.label) || (o.delivery && o.delivery.type) || 'Доставка';
            const payLabel = (o.payment && o.payment.label)   || (o.payment && o.payment.type)   || 'Оплата';
            const delAddr  = renderDeliveryAddress(o.delivery);
            const contactName  = o.contact ? escHtml(o.contact.name)  : escHtml(o.name  || '');
            const contactPhone = o.contact ? escHtml(o.contact.phone) : escHtml(o.phone || '');

            const itemsHtml = (o.items || []).map(i => `
                <tr>
                    <td class="receipt-item-name">${escHtml(i.name)}</td>
                    <td class="receipt-item-qty">× ${i.qty}</td>
                    <td class="receipt-item-price">${fmtMoney(i.price * i.qty)} ₽</td>
                </tr>`).join('');

            return `
            <div class="order-receipt" id="order-${o.id}">
                <div class="receipt-head">
                    <div class="receipt-head-left">
                        <span class="receipt-number">Заказ&nbsp;#${escHtml(o.displayNum)}</span>
                        <span class="receipt-date">${escHtml(o.date)}</span>
                    </div>
                </div>

                <table class="receipt-items">
                    <tbody>${itemsHtml}</tbody>
                    <tfoot>
                        <tr class="receipt-total-row">
                            <td colspan="2">Итого</td>
                            <td><strong>${fmtMoney(o.total)} ₽</strong></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="receipt-meta">
                    <div class="receipt-meta-item">
                        <i class="fas ${delIcon}"></i>
                        <div>
                            <span class="rmeta-label">Доставка</span>
                            <span class="rmeta-val">${escHtml(delLabel)}${delAddr}</span>
                        </div>
                    </div>
                    <div class="receipt-meta-item">
                        <i class="fas ${payIcon}"></i>
                        <div>
                            <span class="rmeta-label">Оплата</span>
                            <span class="rmeta-val">${escHtml(payLabel)}</span>
                        </div>
                    </div>
                    <div class="receipt-meta-item">
                        <i class="fas fa-user"></i>
                        <div>
                            <span class="rmeta-label">Получатель</span>
                            <span class="rmeta-val">${contactName}${contactPhone ? '<br><small>'+contactPhone+'</small>' : ''}</span>
                        </div>
                    </div>
                </div>

                ${o.comment ? `<div class="receipt-comment"><i class="fas fa-comment-alt"></i> ${escHtml(o.comment)}</div>` : ''}
            </div>`;
        }).join('');
    }
    }

    let cars = [];
    async function loadCars() {
        const res = await fetch(API + '?action=get_cars').then(r => r.json());
        cars = res.success ? res.cars : [];
        renderCars();
    }
    document.getElementById('add-car-btn').addEventListener('click', async () => {
        const brand = document.getElementById('car-brand').value.trim();
        const model = document.getElementById('car-model').value.trim();
        const year  = document.getElementById('car-year').value.trim();
        if (!brand || !model) return;
        const fd = new FormData();
        fd.append('action','add_car'); fd.append('brand',brand); fd.append('model',model); fd.append('year',year);
        const res = await fetch(API, { method:'POST', body:fd }).then(r => r.json());
        if (res.success) {
            document.getElementById('car-brand').value = '';
            document.getElementById('car-model').value = '';
            document.getElementById('car-year').value  = '';
            await loadCars();
        }
    });
    function renderCars() {
        const list = document.getElementById('cars-list');
        if (!cars.length) {
            list.innerHTML = `<div class="profile-empty"><i class="fas fa-car"></i><p>Добавьте свой автомобиль для удобного подбора запчастей</p></div>`;
            return;
        }
        list.innerHTML = cars.map(car => `
            <div class="car-row">
                <i class="fas fa-car car-icon"></i>
                <div class="car-row-info">
                    <span class="car-name">${esc(car.brand)} ${esc(car.model)}</span>
                    ${car.year ? `<span class="car-year">${esc(car.year)} г.</span>` : ''}
                </div>
                <button class="car-remove-btn" onclick="removeCar(${car.id})"><i class="fas fa-times"></i></button>
            </div>`).join('');
    }
    window.removeCar = async id => {
        const fd = new FormData(); fd.append('action','remove_car'); fd.append('car_id',id);
        await fetch(API, { method:'POST', body:fd });
        await loadCars();
    };
    loadCars();

    let favLoaded = false;

    async function loadFavorites() {
        if (favLoaded) return;
        favLoaded = true;
        const res = await fetch(API + '?action=get_favorites').then(r => r.json());
        renderFavorites(res.success ? res.favorites : []);
    }

    function renderFavorites(favs) {
        const list = document.getElementById('favorites-list');
        const badge = document.getElementById('fav-nav-badge');

        if (favs.length > 0) {
            badge.textContent = favs.length;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }

        if (!favs.length) {
            list.innerHTML = `<div class="profile-empty"><i class="fas fa-heart"></i><p>Вы ещё ничего не добавили в избранное</p><a href="catalog.php" class="btn-primary-lg"><i class="fas fa-search"></i> Перейти в каталог</a></div>`;
            return;
        }

        list.innerHTML = `<div class="fav-grid">${favs.map(p => `
            <div class="fav-card" id="fav-${p.product_id}">
                <a href="product.php?id=${p.product_id}" class="fav-card-img">
                    ${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.name)}">` : `<i class="fas fa-cog"></i>`}
                </a>
                <div class="fav-card-body">
                    <a href="product.php?id=${p.product_id}" class="fav-card-name">${esc(p.name)}</a>
                    <div class="fav-card-cat">${esc(p.category || '')}</div>
                    <div class="fav-card-price">${Number(p.price).toLocaleString('ru-RU')} ₽</div>
                    <div class="fav-card-stock ${(p.available || p.quantity) > 0 ? 'in' : 'out'}">
                        <i class="fas fa-${(p.available || p.quantity) > 0 ? 'check' : 'times'}-circle"></i>
                        ${(p.available || p.quantity) > 0 ? 'В наличии' : 'Нет в наличии'}
                    </div>
                </div>
                <div class="fav-card-footer">
                    <a href="product.php?id=${p.product_id}" class="btn-primary-lg fav-btn-go"><i class="fas fa-eye"></i> Открыть</a>
                    <button class="fav-remove-btn" onclick="removeFav(${p.product_id})" title="Убрать из избранного">
                        <i class="fas fa-heart-broken"></i>
                    </button>
                </div>
            </div>`).join('')}</div>`;
    }

    window.removeFav = async productId => {
        const fd = new FormData();
        fd.append('action',     'remove_favorite');
        fd.append('product_id', productId);
        const res = await fetch(API, { method: 'POST', body: fd }).then(r => r.json());
        if (res.success) {
            const card = document.getElementById('fav-' + productId);
            if (card) {
                card.style.animation = 'fadeFavOut .3s ease forwards';
                setTimeout(() => {
                    card.remove();
                    const remaining = document.querySelectorAll('.fav-card').length;
                    const badge = document.getElementById('fav-nav-badge');
                    if (remaining === 0) {
                        favLoaded = false;
                        loadFavorites();
                    } else {
                        badge.textContent = remaining;
                    }
                }, 300);
            }
        }
    };

    if (location.hash === '#tab-favorites') loadFavorites();
    if (location.hash === '#tab-reviews')   loadMyReviews();
    if (location.hash === '#tab-support')   loadMySupport();

    let myRvLoaded = false;
    const RV_API = 'api/reviews.php';

    async function loadMyReviews() {
        if (myRvLoaded) return;
        myRvLoaded = true;

        const [rvRes, qRes] = await Promise.all([
            fetch(RV_API + '?action=get_user_reviews').then(r => r.json()),
            fetch(RV_API + '?action=get_user_questions').then(r => r.json())
        ]);

        const totalRv = (rvRes.success ? rvRes.reviews.length : 0)
                      + (qRes.success  ? qRes.questions.length : 0);
        const badge = document.getElementById('rv-nav-badge');
        if (totalRv > 0) { badge.textContent = totalRv; badge.style.display = 'flex'; }

        renderMyReviews(rvRes.success ? rvRes.reviews : []);
        renderMyQuestions(qRes.success ? qRes.questions : []);
    }

    function renderMyReviews(reviews) {
        const el = document.getElementById('profile-reviews-list');
        if (!reviews.length) {
            el.innerHTML = `<div class="profile-empty"><i class="fas fa-star"></i><p>Вы ещё не оставляли отзывов</p><a href="catalog.php" class="btn-primary-lg"><i class="fas fa-search"></i> В каталог</a></div>`;
            return;
        }
        const STATUS_LABELS = { approved: 'Опубликован', rejected: 'Скрыт' };
        el.innerHTML = reviews.map(r => `
            <div class="profile-rv-card" id="prv-${r.id}">
                <div class="prv-head">
                    <a href="product.php?id=${r.product_id}" class="prv-product">
                        ${r.product_image ? `<img src="${esc(r.product_image)}" alt="">` : '<i class="fas fa-cog"></i>'}
                        <span>${esc(r.product_name)}</span>
                    </a>
                    <div class="prv-meta">
                        <span class="prv-stars">${renderStarsHtml(r.rating)}</span>
                        <span class="prv-date">${new Date(r.created_at).toLocaleDateString('ru-RU')}</span>
                        <span class="rv-status-badge rv-badge-${r.status}">${STATUS_LABELS[r.status] || r.status}</span>
                    </div>
                </div>
                ${r.title ? `<div class="prv-title">${esc(r.title)}</div>` : ''}
                <div class="prv-body">${esc(r.body)}</div>
                ${r.admin_reply ? `
                    <div class="prv-admin-reply">
                        <div class="prv-admin-label"><i class="fas fa-store"></i> Ответ магазина</div>
                        <div>${esc(r.admin_reply)}</div>
                    </div>` : ''}
                <div class="prv-actions">
                    <button class="profile-btn-sm profile-btn-danger" onclick="deleteMyProfileReview(${r.id})">
                        <i class="fas fa-trash"></i> Удалить отзыв
                    </button>
                </div>
            </div>`).join('');
    }

    function renderMyQuestions(questions) {
        const el = document.getElementById('profile-questions-list');
        if (!questions.length) {
            el.innerHTML = `<div class="profile-empty"><i class="fas fa-question-circle"></i><p>У вас нет заданных вопросов</p></div>`;
            return;
        }
        el.innerHTML = questions.map(q => `
            <div class="profile-rv-card" id="pqv-${q.id}">
                <div class="prv-head">
                    <a href="product.php?id=${q.product_id}" class="prv-product">
                        ${q.product_image ? `<img src="${esc(q.product_image)}" alt="">` : '<i class="fas fa-cog"></i>'}
                        <span>${esc(q.product_name)}</span>
                    </a>
                    <div class="prv-meta">
                        <span class="prv-date">${new Date(q.created_at).toLocaleDateString('ru-RU')}</span>
                        <span class="rv-status-badge rv-badge-${q.status}">${q.status === 'answered' ? 'Отвечен' : 'Ожидает ответа'}</span>
                    </div>
                </div>
                <div class="prv-body"><i class="fas fa-question-circle" style="color:#f59e0b;margin-right:6px"></i>${esc(q.question)}</div>
                ${q.answer ? `
                    <div class="prv-admin-reply">
                        <div class="prv-admin-label"><i class="fas fa-store"></i> Ответ магазина</div>
                        <div>${esc(q.answer)}</div>
                    </div>` : '<div class="prv-pending">Ждём ответа администратора...</div>'}
                <div class="prv-actions">
                    <button class="profile-btn-sm profile-btn-danger" onclick="deleteMyProfileQuestion(${q.id})">
                        <i class="fas fa-trash"></i> Удалить вопрос
                    </button>
                </div>
            </div>`).join('');
    }

    window.deleteMyProfileReview = async function(id) {
        if (!confirm('Удалить отзыв?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_review');
        fd.append('review_id', id);
        const res = await fetch(RV_API, { method:'POST', body:fd }).then(r => r.json());
        if (res.success) { myRvLoaded = false; loadMyReviews(); }
    };
    window.deleteMyProfileQuestion = async function(id) {
        if (!confirm('Удалить вопрос?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_question');
        fd.append('question_id', id);
        const res = await fetch(RV_API, { method:'POST', body:fd }).then(r => r.json());
        if (res.success) { myRvLoaded = false; loadMyReviews(); }
    };

    function renderStarsHtml(n) {
        let s = '';
        for (let i=1; i<=5; i++) s += `<i class="${i<=n?'fas':'far'} fa-star"></i>`;
        return `<span class="stars-row">${s}</span>`;
    }

    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    let supLoaded = false;

    async function loadMySupport() {
        if (supLoaded) return;
        supLoaded = true;
        const res = await fetch(RV_API + '?action=get_user_support').then(r => r.json());
        const msgs = res.success ? res.messages : [];
        const newCnt = msgs.filter(m => m.status === 'new').length;
        const badge = document.getElementById('sup-nav-badge');
        if (newCnt > 0) { badge.textContent = newCnt; badge.style.display = 'flex'; }
        renderMySupport(msgs);
    }

    function renderMySupport(msgs) {
        const el = document.getElementById('profile-support-list');
        if (!msgs.length) {
            el.innerHTML = '<div class="profile-empty"><i class="fas fa-comment-dots"></i><p>Обращений ещё нет</p></div>';
            return;
        }
        const STATUS = { new: 'Новое', read: 'Прочитано', replied: 'Отвечено' };
        el.innerHTML = msgs.map(m => `
            <div class="profile-rv-card" style="margin-bottom:14px;">
                <div class="prv-head" style="align-items:flex-start;">
                    <div style="flex:1;">
                        ${m.subject ? `<div class="prv-title" style="margin-bottom:4px;">${esc(m.subject)}</div>` : ''}
                        <div style="font-size:12px;color:var(--text-secondary);">${new Date(m.created_at).toLocaleDateString('ru-RU',{day:'2-digit',month:'long',year:'numeric'})}</div>
                    </div>
                    <span class="rv-status-badge rv-badge-${m.status}">${STATUS[m.status]||m.status}</span>
                </div>
                <div class="prv-body" style="margin-top:8px;">${esc(m.message)}</div>
                ${m.reply ? `
                    <div class="prv-admin-reply" style="margin-top:10px;">
                        <div class="prv-admin-label"><i class="fas fa-store"></i> Ответ магазина <span style="font-weight:400;color:var(--text-secondary);font-size:11px;">${new Date(m.replied_at).toLocaleDateString('ru-RU')}</span></div>
                        <div>${esc(m.reply)}</div>
                    </div>` : ''}
            </div>`).join('');
    }

    window.sendNewSupport = async function() {
        const subject = document.getElementById('new-sup-subject').value.trim();
        const message = document.getElementById('new-sup-message').value.trim();
        const errEl   = document.getElementById('new-sup-error');
        const okEl    = document.getElementById('new-sup-ok');
        errEl.style.display = 'none';
        okEl.style.display  = 'none';
        if (!message) { errEl.textContent = 'Введите сообщение'; errEl.style.display = 'block'; return; }

        const btn = document.getElementById('new-sup-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';

        const fd = new FormData();
        fd.append('action',  'send_support');
        fd.append('name',    '<?= addslashes(trim($user['firstname'].' '.$user['lastname'])) ?>');
        fd.append('email',   '<?= addslashes($user['email']) ?>');
        fd.append('subject', subject);
        fd.append('message', message);
        const res = await fetch(RV_API, { method:'POST', body:fd }).then(r => r.json());
        if (res.success) {
            okEl.style.display = 'block';
            document.getElementById('new-sup-message').value = '';
            document.getElementById('new-sup-subject').value = '';
            supLoaded = false;
            loadMySupport();
        } else {
            errEl.textContent = res.message || 'Ошибка. Попробуйте ещё раз.';
            errEl.style.display = 'block';
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Отправить';
    };
});
</script>

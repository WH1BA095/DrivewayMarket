<?php
require_once 'config/auth.php';
require_once 'config/db.php';
requireLogin('index.php');

$db = Database::getInstance()->getConnection();
$st = $db->prepare("SELECT firstname, lastname, phone, email, address FROM users WHERE id = ?");
$st->execute([$_SESSION['user_id']]);
$userData = $st->fetch(PDO::FETCH_ASSOC);

$uName    = trim(($userData['firstname'] ?? '') . ' ' . ($userData['lastname'] ?? ''));
$uPhone   = $userData['phone']   ?? '';
$uEmail   = $userData['email']   ?? '';
$uAddress = $userData['address'] ?? '';
?>
<?php require_once 'includes/header.php'; ?>

<main class="main-content">
    <div class="page-container">

        <div class="breadcrumbs">
            <a href="index.php">Главная</a>
            <span class="breadcrumb-separator">›</span>
            <a href="cart.php">Корзина</a>
            <span class="breadcrumb-separator">›</span>
            <span class="current">Оформление заказа</span>
        </div>

        <h1 class="page-title"><i class="fas fa-credit-card"></i> Оформление заказа</h1>

        <div id="checkout-empty" style="display:none;" class="cart-empty">
            <div class="cart-empty-icon"><i class="fas fa-shopping-cart"></i></div>
            <h2>Корзина пуста</h2>
            <p>Нельзя оформить пустой заказ</p>
            <a href="catalog.php" class="btn-primary-lg"><i class="fas fa-arrow-left"></i> В каталог</a>
        </div>

        <div id="checkout-success" style="display:none;" class="checkout-success-wrap">
            <div class="checkout-success-card">
                <div class="success-circle">
                    <i class="fas fa-check"></i>
                </div>
                <h2 class="success-title">Заказ оформлен!</h2>
                <div class="success-order-num">
                    Номер заказа: <strong id="order-number-display"></strong>
                </div>
                <p class="success-hint">Мы свяжемся с вами в ближайшее время для подтверждения</p>
                <div class="success-actions">
                    <a href="profile.php#tab-orders" class="btn-primary-lg">
                        <i class="fas fa-box"></i> Мои заказы
                    </a>
                    <a href="catalog.php" class="btn-outline-lg">
                        <i class="fas fa-arrow-left"></i> Продолжить покупки
                    </a>
                </div>
            </div>
        </div>

        <div id="checkout-form" class="checkout-layout">

            <div class="checkout-left">

                <div class="cart-card checkout-step">
                    <div class="checkout-step-header">
                        <div class="checkout-step-num">1</div>
                        <h2>Контактные данные</h2>
                    </div>
                    <div class="profile-form">
                        <div class="profile-form-row">
                            <div class="profile-form-group">
                                <label>Имя <span class="req">*</span></label>
                                <input type="text" id="co-name" placeholder="Иван Иванов" class="profile-input" required
                                       value="<?= htmlspecialchars($uName) ?>">
                            </div>
                            <div class="profile-form-group">
                                <label>Телефон <span class="req">*</span></label>
                                <input type="tel" id="co-phone" placeholder="+7 (___) ___-__-__" class="profile-input" required
                                       value="<?= htmlspecialchars($uPhone) ?>">
                            </div>
                        </div>
                        <div class="profile-form-group">
                            <label>Email</label>
                            <input type="email" id="co-email" placeholder="ivan@example.com" class="profile-input"
                                   value="<?= htmlspecialchars($uEmail) ?>">
                        </div>
                    </div>
                </div>

                <div class="cart-card checkout-step">
                    <div class="checkout-step-header">
                        <div class="checkout-step-num">2</div>
                        <h2>Способ получения</h2>
                    </div>

                    <div class="delivery-options" id="delivery-group">
                        <label class="dopt active" data-value="pickup">
                            <input type="radio" name="delivery" value="pickup" checked>
                            <div class="dopt-inner">
                                <div class="dopt-icon"><i class="fas fa-store"></i></div>
                                <div class="dopt-text">
                                    <strong>Самовывоз со склада</strong>
                                    <span>Готов сегодня · Бесплатно</span>
                                </div>
                            </div>
                        </label>
                        <label class="dopt" data-value="courier">
                            <input type="radio" name="delivery" value="courier">
                            <div class="dopt-inner">
                                <div class="dopt-icon"><i class="fas fa-truck"></i></div>
                                <div class="dopt-text">
                                    <strong>Курьер по городу</strong>
                                    <span>1–2 дня · Бесплатно</span>
                                </div>
                            </div>
                        </label>
                        <label class="dopt" data-value="post">
                            <input type="radio" name="delivery" value="post">
                            <div class="dopt-inner">
                                <div class="dopt-icon"><i class="fas fa-box"></i></div>
                                <div class="dopt-text">
                                    <strong>Почта России</strong>
                                    <span>3–7 дней · Бесплатно</span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div id="block-pickup" class="delivery-extra-block" style="margin-top:16px;">
                        <div class="pickup-points">
                            <label class="pickup-point active-pickup">
                                <input type="radio" name="pickup_point" value="1" checked>
                                <span><strong>Склад Центральный</strong><br>ул. Ленина, д. 1 · Пн-Сб 9:00–20:00</span>
                            </label>
                            <label class="pickup-point">
                                <input type="radio" name="pickup_point" value="2">
                                <span><strong>Склад Северный</strong><br>пр. Победы, д. 55 · Ежедневно 10:00–22:00</span>
                            </label>
                        </div>
                    </div>

                    <div id="block-courier" class="delivery-extra-block profile-form" style="display:none; margin-top:16px;">
                        <div class="profile-form-group">
                            <label>Адрес доставки <span class="req">*</span></label>
                            <input type="text" id="co-address" placeholder="г. Москва, ул. Примерная, д. 1" class="profile-input"
                                       value="<?= htmlspecialchars($uAddress) ?>">
                        </div>
                        <div class="profile-form-row">
                            <div class="profile-form-group">
                                <label>Квартира / офис</label>
                                <input type="text" id="co-apt" placeholder="10" class="profile-input" style="max-width:120px;">
                            </div>
                            <div class="profile-form-group">
                                <label>Комментарий к адресу</label>
                                <input type="text" id="co-comment-addr" placeholder="Домофон 134" class="profile-input">
                            </div>
                        </div>
                    </div>

                    <div id="block-post" class="delivery-extra-block profile-form" style="display:none; margin-top:16px;">
                        <div class="profile-form-group">
                            <label>Адрес для отправки <span class="req">*</span></label>
                            <input type="text" id="co-post-address" placeholder="г. Москва, ул. Примерная, д. 1" class="profile-input"
                                       value="<?= htmlspecialchars($uAddress) ?>">
                        </div>
                        <div class="profile-form-row">
                            <div class="profile-form-group">
                                <label>Индекс <span class="req">*</span></label>
                                <input type="text" id="co-post-index" placeholder="123456" class="profile-input" style="max-width:130px;" maxlength="6">
                            </div>
                            <div class="profile-form-group">
                                <label>Получатель</label>
                                <input type="text" id="co-post-recipient" placeholder="Иванов Иван Иванович" class="profile-input"
                                       value="<?= htmlspecialchars($uName) ?>">
                            </div>
                        </div>
                        <div class="post-info-note">
                            <i class="fas fa-info-circle"></i> Трек-номер будет отправлен на e-mail после отправки
                        </div>
                    </div>
                </div>

                <div class="cart-card checkout-step">
                    <div class="checkout-step-header">
                        <div class="checkout-step-num">3</div>
                        <h2>Способ оплаты</h2>
                    </div>

                    <div id="payment-group" class="delivery-options"></div>


                    <div id="online-pay-panel" style="display:none; margin-top:18px;">
                        <div class="pay-method-tabs">
                            <button class="pay-method-tab active" id="pmtab-sbp" onclick="onlineTab('sbp')">
                                <i class="fas fa-mobile-alt"></i> СБП
                            </button>
                            <button class="pay-method-tab" id="pmtab-card" onclick="onlineTab('card')">
                                <i class="fas fa-credit-card"></i> Банковская карта
                            </button>
                        </div>

                        <div id="opanel-sbp" class="opanel">
                            <p class="opanel-hint">После подтверждения заказа откроется окно с QR‑кодом для оплаты через банковское приложение</p>
                            <div class="sbp-logos">
                                <span class="sbp-badge"><i class="fas fa-mobile-alt"></i> СберБанк</span>
                                <span class="sbp-badge"><i class="fas fa-mobile-alt"></i> Тинькофф</span>
                                <span class="sbp-badge"><i class="fas fa-mobile-alt"></i> ВТБ</span>
                                <span class="sbp-badge">+ ещё 200 банков</span>
                            </div>
                        </div>

                        <div id="opanel-card" class="opanel" style="display:none;">
                            <div class="card-secure-note">
                                <i class="fas fa-lock"></i>
                                <span>Сайт <strong>не сохраняет</strong> данные карт. Платёж проводится через защищённый шлюз.</span>
                            </div>
                            <div class="card-form">
                                <div class="card-visual" id="card-visual">
                                    <div class="card-visual-top">
                                        <span class="card-visual-chip"><i class="fas fa-microchip"></i></span>
                                        <span class="card-visual-type" id="cv-type"></span>
                                    </div>
                                    <div class="card-visual-number" id="cv-number"><span>••••</span><span>••••</span><span>••••</span><span>••••</span></div>
                                    <div class="card-visual-bottom">
                                        <div><span class="card-label">Держатель</span><span id="cv-name">ИМЯ ФАМИЛИЯ</span></div>
                                        <div><span class="card-label">Срок</span><span id="cv-expiry">ММ/ГГ</span></div>
                                    </div>
                                </div>
                                <div class="profile-form" style="margin-top:16px;">
                                    <div class="profile-form-group">
                                        <label>Номер карты</label>
                                        <input type="text" id="card-number" class="profile-input" placeholder="0000 0000 0000 0000"
                                               maxlength="19" oninput="fmtCard(this)" autocomplete="cc-number">
                                    </div>
                                    <div class="profile-form-row">
                                        <div class="profile-form-group">
                                            <label>Срок действия</label>
                                            <input type="text" id="card-expiry" class="profile-input" placeholder="ММ/ГГ"
                                                   maxlength="5" oninput="fmtExpiry(this)" autocomplete="cc-exp">
                                        </div>
                                        <div class="profile-form-group">
                                            <label>CVV / CVC</label>
                                            <input type="password" id="card-cvv" class="profile-input" placeholder="•••"
                                                   maxlength="3" inputmode="numeric" autocomplete="cc-csc">
                                        </div>
                                    </div>
                                    <div class="profile-form-group">
                                        <label>Имя на карте</label>
                                        <input type="text" id="card-name" class="profile-input" placeholder="IVAN IVANOV"
                                               oninput="document.getElementById('cv-name').textContent = this.value.toUpperCase() || 'ИМЯ ФАМИЛИЯ'"
                                               style="text-transform:uppercase;" autocomplete="cc-name">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cart-card checkout-step">
                    <div class="checkout-step-header">
                        <div class="checkout-step-num">4</div>
                        <h2>Комментарий к заказу</h2>
                    </div>
                    <textarea id="co-comment" class="profile-input"
                              style="width:100%;min-height:80px;resize:vertical;"
                              placeholder="Пожелания к заказу или доставке..."></textarea>
                </div>

            </div>

            <div class="cart-summary-col">
                <div class="cart-card cart-summary-card" style="position:sticky;top:90px;">
                    <h3 class="cart-summary-title">Ваш заказ</h3>
                    <div id="co-items-list" class="co-items"></div>
                    <div class="cart-summary-rows" style="margin-top:15px;">
                        <div class="cart-summary-row">
                            <span>Товары (<span id="co-count">0</span> шт.)</span>
                            <span id="co-price">0 ₽</span>
                        </div>
                        <div class="cart-summary-row">
                            <span>Доставка</span>
                            <span class="text-green">Бесплатно</span>
                        </div>
                    </div>
                    <div class="cart-summary-total">
                        <span>Итого</span>
                        <span id="co-total">0 ₽</span>
                    </div>

                    <div id="checkout-error" class="checkout-error" style="display:none;"></div>

                    <button class="btn-primary-lg btn-block" id="place-order-btn">
                        <i class="fas fa-check"></i> Подтвердить заказ
                    </button>
                    <a href="cart.php" class="btn-outline-lg btn-block">
                        <i class="fas fa-arrow-left"></i> Вернуться в корзину
                    </a>
                    <div class="cart-payment-icons" style="margin-top:15px;">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-mir"></i>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<div id="sbp-modal" class="sbp-modal-overlay" style="display:none;" onclick="if(event.target===this)closeSbpModal()">
    <div class="sbp-modal">
        <button class="sbp-modal-close" onclick="closeSbpModal()"><i class="fas fa-times"></i></button>

        <div class="sbp-modal-header">
            <div class="sbp-modal-logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="#21A038"/>
                    <path d="M8 16L13 21L24 11" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>СБП — Быстрые платежи</span>
            </div>
            <p class="sbp-modal-sub">Отсканируйте QR-код в приложении банка</p>
        </div>

        <div class="sbp-modal-qr-area">
            <div class="sbp-modal-qr-wrap">
                <div id="sbp-qr-container"></div>
            </div>
            <div class="sbp-modal-amount">
                К оплате: <strong id="sbp-amount-display">0 ₽</strong>
            </div>
        </div>

        <div class="sbp-modal-banks">
            <span>СберБанк</span>
            <span>Т-Банк</span>
            <span>ВТБ</span>
            <span>Альфа-Банк</span>
            <span>Газпромбанк</span>
            <span>+ 200 банков</span>
        </div>

        <div class="sbp-modal-timer">
            <i class="fas fa-clock"></i> Код действителен: <span id="sbp-timer">10:00</span>
        </div>

        <div class="sbp-modal-note">
            <i class="fas fa-shield-alt"></i>
            Операция защищена шифрованием. Никому не сообщайте SMS-коды.
        </div>

        <button class="btn-outline-lg btn-block" onclick="closeSbpModal()" style="margin-top:4px;">
            Отмена
        </button>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="js/cart.js"></script>
<script>
const PAY_CONFIGS = {
    pickup: [
        { value: 'online',     icon: 'fas fa-globe',          title: 'Оплатить онлайн',       sub: 'СБП или банковская карта' },
        { value: 'at_pickup',  icon: 'fas fa-store',          title: 'Оплата на складе',       sub: 'Наличными или картой при получении' }
    ],
    courier: [
        { value: 'online',     icon: 'fas fa-globe',          title: 'Оплатить онлайн',        sub: 'СБП или банковская карта' },
        { value: 'cash',       icon: 'fas fa-money-bill-wave', title: 'Наличными курьеру',      sub: 'При получении заказа' }
    ],
    post: [
        { value: 'online',     icon: 'fas fa-globe',          title: 'Оплатить онлайн',        sub: 'СБП или банковская карта · Единственный вариант', only: true }
    ]
};

const DELIVERY_LABELS = {
    pickup:  'Самовывоз со склада',
    courier: 'Курьер по городу',
    post:    'Почта России'
};
const PAYMENT_LABELS = {
    online_sbp:  'СБП — быстрые платежи',
    online_card: 'Банковская карта онлайн',
    at_pickup:   'Оплата на складе',
    cash:        'Наличными курьеру'
};

let currentDelivery = 'pickup';
let currentPayment  = 'online';
let currentOnlineTab = 'sbp';
let sbpTimerInterval = null;
let orderTotal = 0;

document.addEventListener('DOMContentLoaded', () => {
    Cart.updateBadge();

    const items = Cart.get();
    if (items.length === 0) {
        document.getElementById('checkout-empty').style.display = 'flex';
        document.getElementById('checkout-form').style.display  = 'none';
        return;
    }

    const profile = JSON.parse(localStorage.getItem('driveway_profile') || '{}');
    if (profile.firstname || profile.lastname)
        document.getElementById('co-name').value = [profile.firstname, profile.lastname].filter(Boolean).join(' ');
    if (profile.phone)   document.getElementById('co-phone').value   = profile.phone;
    if (profile.email)   document.getElementById('co-email').value   = profile.email;
    if (profile.address) document.getElementById('co-address').value = profile.address;

    orderTotal = Cart.total();
    document.getElementById('co-items-list').innerHTML = items.map(i => `
        <div class="co-item">
            <span class="co-item-name">${esc(i.name.length > 35 ? i.name.slice(0,35)+'…' : i.name)}</span>
            <span class="co-item-qty">× ${i.qty}</span>
            <span class="co-item-price">${fmt(i.price * i.qty)} ₽</span>
        </div>`).join('');
    document.getElementById('co-count').textContent = Cart.count();
    document.getElementById('co-price').textContent = fmt(orderTotal) + ' ₽';
    document.getElementById('co-total').textContent = fmt(orderTotal) + ' ₽';

    renderPaymentOptions('pickup');

    document.querySelectorAll('#delivery-group input[name="delivery"]').forEach(radio => {
        radio.addEventListener('change', () => {
            currentDelivery = radio.value;
            document.querySelectorAll('#delivery-group .dopt').forEach(el => el.classList.remove('active'));
            radio.closest('.dopt').classList.add('active');

            // Блоки адресов
            document.getElementById('block-pickup').style.display  = currentDelivery === 'pickup'  ? 'block' : 'none';
            document.getElementById('block-courier').style.display = currentDelivery === 'courier' ? 'block' : 'none';
            document.getElementById('block-post').style.display    = currentDelivery === 'post'    ? 'block' : 'none';

            renderPaymentOptions(currentDelivery);
        });
    });

    document.querySelectorAll('input[name="pickup_point"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.pickup-point').forEach(el => el.classList.remove('active-pickup'));
            radio.closest('.pickup-point').classList.add('active-pickup');
        });
    });
    const defaultPickup = document.querySelector('input[name="pickup_point"]:checked');
    if (defaultPickup) {
        document.querySelectorAll('.pickup-point').forEach(el => el.classList.remove('active-pickup'));
        defaultPickup.closest('.pickup-point').classList.add('active-pickup');
    }

    document.getElementById('place-order-btn').addEventListener('click', placeOrder);
});

function renderPaymentOptions(delivery) {
    const group = document.getElementById('payment-group');
    const opts  = PAY_CONFIGS[delivery];

    group.innerHTML = opts.map((o, i) => `
        <label class="dopt${i === 0 ? ' active' : ''}" data-value="${o.value}" id="popt-${o.value}">
            <input type="radio" name="payment" value="${o.value}"${i === 0 ? ' checked' : ''}>
            <div class="dopt-inner">
                <div class="dopt-icon"><i class="${o.icon}"></i></div>
                <div class="dopt-text">
                    <strong>${o.title}</strong>
                    <span>${o.sub}</span>
                </div>
                ${o.only ? '<span class="pay-only-badge">Единственный вариант</span>' : ''}
            </div>
        </label>`).join('');

    group.querySelectorAll('input[name="payment"]').forEach(radio => {
        radio.addEventListener('change', () => {
            currentPayment = radio.value;
            group.querySelectorAll('.dopt').forEach(el => el.classList.remove('active'));
            radio.closest('.dopt').classList.add('active');
            document.getElementById('online-pay-panel').style.display =
                currentPayment === 'online' ? 'block' : 'none';
        });
    });

    currentPayment = opts[0].value;
    document.getElementById('online-pay-panel').style.display =
        currentPayment === 'online' ? 'block' : 'none';
}

window.onlineTab = function(tab) {
    currentOnlineTab = tab;
    document.getElementById('opanel-sbp').style.display  = tab === 'sbp'  ? 'block' : 'none';
    document.getElementById('opanel-card').style.display = tab === 'card' ? 'block' : 'none';
    document.getElementById('pmtab-sbp').classList.toggle('active',  tab === 'sbp');
    document.getElementById('pmtab-card').classList.toggle('active', tab === 'card');
};

window.fmtCard = function(el) {
    let v = el.value.replace(/\D/g, '').slice(0, 16);
    el.value = v.replace(/(.{4})/g, '$1 ').trim();

    // показываем 4 группы по 4 символа в отдельных span
    const padded = v.padEnd(16, '•');
    const groups = [padded.slice(0,4), padded.slice(4,8), padded.slice(8,12), padded.slice(12,16)];
    document.getElementById('cv-number').innerHTML = groups.map(g => `<span>${g}</span>`).join('');

    const typeEl = document.getElementById('cv-type');
    const first = v[0];
    if      (first === '4') typeEl.innerHTML = '<i class="fab fa-cc-visa"></i>';
    else if (first === '5') typeEl.innerHTML = '<i class="fab fa-cc-mastercard"></i>';
    else if (first === '2') typeEl.innerHTML = '<i class="fab fa-cc-mir"></i>';
    else                    typeEl.innerHTML = '';
};
window.fmtExpiry = function(el) {
    let v = el.value.replace(/\D/g, '').slice(0, 4);
    if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
    el.value = v;
    document.getElementById('cv-expiry').textContent = v || 'ММ/ГГ';
};

function openSbpModal(amount) {
    const modal = document.getElementById('sbp-modal');
    document.getElementById('sbp-amount-display').textContent = fmt(amount) + ' ₽';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // qrcodejs API: генерируем QR с фиктивным URL НСПК
    const qrData  = `https://qr.nspk.ru/AS1${Date.now()}?amount=${amount * 100}&currency=643&payee=Driveway`;
    const container = document.getElementById('sbp-qr-container');
    container.innerHTML = '';
    if (window.QRCode) {
        new QRCode(container, {
            text: qrData,
            width: 220,
            height: 220,
            colorDark:  '#21A038',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    // таймер 10 минут
    let seconds = 600;
    clearInterval(sbpTimerInterval);
    sbpTimerInterval = setInterval(() => {
        seconds--;
        if (seconds <= 0) { clearInterval(sbpTimerInterval); closeSbpModal(); return; }
        const m = String(Math.floor(seconds / 60)).padStart(2, '0');
        const s = String(seconds % 60).padStart(2, '0');
        document.getElementById('sbp-timer').textContent = `${m}:${s}`;
    }, 1000);
}

window.closeSbpModal = function() {
    document.getElementById('sbp-modal').style.display = 'none';
    document.body.style.overflow = '';
    clearInterval(sbpTimerInterval);
};

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSbpModal();
});

function placeOrder() {
    const name  = document.getElementById('co-name').value.trim();
    const phone = document.getElementById('co-phone').value.trim();
    const errEl = document.getElementById('checkout-error');
    errEl.style.display = 'none';

    const email = document.getElementById('co-email').value.trim();

    if (!name)  { showError('Введите имя'); return; }
    if (!phone) { showError('Введите номер телефона'); return; }
    const phoneDigits = phone.replace(/\D/g, '');
    if (phoneDigits.length < 10) { showError('Введите корректный номер телефона'); return; }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('Введите корректный email'); return; }

    const delivery = document.querySelector('input[name="delivery"]:checked').value;

    if (delivery === 'courier') {
        if (!document.getElementById('co-address').value.trim()) {
            showError('Введите адрес доставки'); return;
        }
    }
    if (delivery === 'post') {
        if (!document.getElementById('co-post-address').value.trim()) {
            showError('Введите адрес для отправки'); return;
        }
        if (!document.getElementById('co-post-index').value.trim()) {
            showError('Введите почтовый индекс'); return;
        }
    }

    const paymentVal = document.querySelector('input[name="payment"]:checked')?.value || 'online';

    if (paymentVal === 'online' && currentOnlineTab === 'card') {
        const num = document.getElementById('card-number').value.replace(/\s/g,'');
        if (num.length < 16) { showError('Введите корректный номер карты'); return; }
        if (!document.getElementById('card-expiry').value.includes('/')) { showError('Введите срок действия карты'); return; }
        if (document.getElementById('card-cvv').value.length < 3) { showError('Введите CVV / CVC'); return; }
    }

    let paymentType, paymentLabel;
    if (paymentVal === 'online') {
        paymentType  = currentOnlineTab === 'sbp' ? 'online_sbp' : 'online_card';
        paymentLabel = PAYMENT_LABELS[paymentType];
    } else {
        paymentType  = paymentVal;
        paymentLabel = PAYMENT_LABELS[paymentVal] || paymentVal;
    }

    const deliveryObj = { type: delivery, label: DELIVERY_LABELS[delivery] };
    if (delivery === 'courier') {
        deliveryObj.address = document.getElementById('co-address').value.trim();
        deliveryObj.apt     = document.getElementById('co-apt').value.trim();
    }
    if (delivery === 'post') {
        deliveryObj.address   = document.getElementById('co-post-address').value.trim();
        deliveryObj.index     = document.getElementById('co-post-index').value.trim();
        deliveryObj.recipient = document.getElementById('co-post-recipient').value.trim();
    }
    if (delivery === 'pickup') {
        const pp = document.querySelector('input[name="pickup_point"]:checked');
        deliveryObj.point = pp ? pp.value : '1';
        deliveryObj.address = pp && pp.value === '2' ? 'пр. Победы, д. 55' : 'ул. Ленина, д. 1';
    }

    const items   = Cart.get();
    const total   = Cart.total();
    const orderId = Date.now().toString().slice(-8);

    const order = {
        id:       orderId,
        date:     new Date().toLocaleDateString('ru-RU'),
        datetime: new Date().toISOString(),
        items,
        total,
        status:   'pending',
        delivery: deliveryObj,
        payment:  { type: paymentType, label: paymentLabel },
        contact:  {
            name, phone,
            email: document.getElementById('co-email').value.trim()
        },
        comment:  document.getElementById('co-comment').value.trim()
    };

    // СБП: показываем QR, заказ сохраняем сразу (имитация инициации платежа)
    if (paymentType === 'online_sbp') {
        openSbpModal(total);

        saveOrderAndClear(order);

        const existingBtn = document.getElementById('sbp-paid-btn');
        if (!existingBtn) {
            const btn = document.createElement('button');
            btn.id = 'sbp-paid-btn';
            btn.className = 'btn-primary-lg btn-block';
            btn.style.marginTop = '8px';
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Я оплатил(а)';
            btn.onclick = () => {
                closeSbpModal();
                showSuccess(orderId);
            };
            document.querySelector('.sbp-modal').appendChild(btn);
        }
        return;
    }

    saveOrderAndClear(order);
    showSuccess(orderId);
}

function saveOrderAndClear(order) {
    // двойное сохранение: localStorage (для гостей) + БД (user_id из сессии)
    const orders = JSON.parse(localStorage.getItem('driveway_orders') || '[]');
    orders.unshift(order);
    localStorage.setItem('driveway_orders', JSON.stringify(orders));
    Cart.clear();
    Cart.updateBadge();

    const payload = {
        items:   order.items,
        address: (order.delivery && order.delivery.address) ? order.delivery.address : '',
        comment: order.comment || '',
        email:   (order.contact && order.contact.email) ? order.contact.email : '',
    };
    fetch('api/save_order.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(payload),
    }).catch(e => console.warn('save_order failed:', e));
}

function showSuccess(orderId) {
    document.getElementById('checkout-form').style.display   = 'none';
    document.getElementById('order-number-display').textContent = '#' + orderId;
    document.getElementById('checkout-success').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showError(msg) {
    const el = document.getElementById('checkout-error');
    el.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + msg;
    el.style.display = 'flex';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function fmt(n) { return Math.round(n).toLocaleString('ru-RU'); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>

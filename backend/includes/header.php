<?php
require_once __DIR__ . '/../config/auth.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DrivewayMarket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">

    <!-- Favicon -->
    <link rel="icon" id="favicon" href="img/smLogo1.png" type="image/png">
</head>

<body class="theme-light">

<header class="header">
    <div class="header-container">
        <div class="header-content">

            <!-- Логотип -->
            <div class="logo-area">
                <a href="index.php" class="logo">
                    <img src="img/bigLogo11.png" class="logo-light" alt="Driveway">
                    <img src="img/bigLogo22.png" class="logo-dark" alt="Driveway">
                </a>
                <a href="#" class="find-part-link">
                    <span>подобрать деталь</span>
                </a>
            </div>

            <!-- Каталог -->
            <div class="catalog-container">
                <button class="catalog-button" id="catalogButton">
                    <i class="fas fa-bars"></i>
                    <span>КАТАЛОГИ</span>
                    <i class="fas fa-chevron-down catalog-arrow"></i>
                </button>

                <!-- Выпадающее меню каталога -->
                <div class="catalog-dropdown" id="catalogDropdown">
                    <div class="catalog-dropdown-content">
                        <div class="catalog-grid">
                            <!-- Двигатель -->
                            <a href="catalog.php?category=engine" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/dvig.png" alt="Двигатель" class="catalog-icon">
                                    <span class="catalog-name">Двигатель</span>
                                </div>
                            </a>

                            <!-- Трансмиссия -->
                            <a href="catalog.php?category=transmission" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/transm.png" alt="Трансмиссия" class="catalog-icon">
                                    <span class="catalog-name">Трансмиссия</span>
                                </div>
                            </a>

                            <!-- Подвеска -->
                            <a href="catalog.php?category=suspension" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/podveska.png" alt="Подвеска" class="catalog-icon">
                                    <span class="catalog-name">Подвеска</span>
                                </div>
                            </a>

                            <!-- Тормозная система -->
                            <a href="catalog.php?category=brakes" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/tormoza.png" alt="Тормозная система" class="catalog-icon">
                                    <span class="catalog-name">Тормозная система</span>
                                </div>
                            </a>

                            <!-- Рулевое управление -->
                            <a href="catalog.php?category=steering" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/ryl.png" alt="Рулевое управление" class="catalog-icon">
                                    <span class="catalog-name">Рулевое управление</span>
                                </div>
                            </a>

                            <!-- Электрика -->
                            <a href="catalog.php?category=electrics" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/electron.png" alt="Электрика" class="catalog-icon">
                                    <span class="catalog-name">Электрика</span>
                                </div>
                            </a>

                            <!-- Шины и диски -->
                            <a href="catalog.php?category=wheels" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/kolesa.png" alt="Шины и диски" class="catalog-icon">
                                    <span class="catalog-name">Шины и диски</span>
                                </div>
                            </a>

                            <!-- Масла и жидкости -->
                            <a href="catalog.php?category=oils" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/maslo.png" alt="Масла и жидкости" class="catalog-icon">
                                    <span class="catalog-name">Масла и жидкости</span>
                                </div>
                            </a>

                            <!-- Аксессуары -->
                            <a href="catalog.php?category=accessories" class="catalog-item">
                                <div class="catalog-item-inner">
                                    <img src="img/akses.png" alt="Аксессуары" class="catalog-icon">
                                    <span class="catalog-name">Аксессуары</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Поиск -->
            <div class="search-block">
                <div class="search-container">
                    <input type="text" id="headerSearchInput" placeholder="Поиск запчастей"
                        onkeydown="if(event.key==='Enter') headerSearch()">
                    <button class="search-btn-inside" onclick="headerSearch()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <script>
            function headerSearch() {
                const q = document.getElementById('headerSearchInput').value.trim();
                if (q) window.location.href = 'catalog.php?search=' + encodeURIComponent(q);
            }
            </script>

            <!-- Правая часть -->
            <div class="header-right-area">

                <!-- Телефон -->
                <div class="phone-wrapper">
                    <a href="tel:88007779980" class="phone-link">
                        <i class="fas fa-phone"></i>
                        <div class="phone-text">
                            <span class="phone-number">8 (800) 777-99-80</span>
                            <span class="phone-subtext">Бесплатный звонок</span>
                        </div>
                    </a>
                </div>

                <!-- Корзина -->
                <a href="cart.php" class="icon-button cart-button">
                    <i class="fas fa-shopping-cart"></i>
                </a>

                <!-- Связаться с нами -->
                <button class="icon-button" onclick="SupportModal.open()" title="Связаться с нами">
                    <i class="fas fa-comment-dots"></i>
                </button>

                <!-- Админ-панель (только для администраторов) -->
                <?php if (isAdmin()): ?>
                <a href="admin/index.php" class="icon-button admin-panel-btn" title="Панель управления">
                    <i class="fas fa-cog"></i>
                </a>
                <?php endif; ?>

                <!-- Профиль / Вход -->
                <?php if ($currentUser): ?>
                <div class="user-dropdown-wrap">
                    <button class="icon-button profile-button" id="userDropdownBtn" title="<?= htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']) ?>">
                        <i class="fas fa-user"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-name">
                            <?= htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']) ?>
                        </div>
                        <div class="user-dropdown-email"><?= htmlspecialchars($currentUser['email']) ?></div>
                        <a href="profile.php" class="user-dropdown-item">
                            <i class="fas fa-id-card"></i> Личный кабинет
                        </a>
                        <a href="#" onclick="AuthModal.logout(); return false;" class="user-dropdown-item user-dropdown-logout">
                            <i class="fas fa-sign-out-alt"></i> Выйти
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <button class="icon-button profile-button" onclick="AuthModal.open('login')" title="Войти">
                    <i class="fas fa-user"></i>
                </button>
                <?php endif; ?>

                <!-- Кнопка темы -->
                <button class="theme-btn" id="themeBtn" title="Переключить тему">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>

            </div>

        </div>
    </div>
</header>

<!-- ===== МОДАЛЬНОЕ ОКНО АВТОРИЗАЦИИ ===== -->
<div id="authModal" class="auth-modal-overlay" style="display:none;">
    <div class="auth-modal">
        <button class="auth-modal-close" onclick="AuthModal.close()" aria-label="Закрыть">
            <i class="fas fa-times"></i>
        </button>

        <div class="auth-logo"><i class="fas fa-car-side"></i> Driveway</div>

        <!-- Вкладки -->
        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="login"    onclick="AuthModal.switchTab('login')">Войти</button>
            <button class="auth-tab"        data-tab="register" onclick="AuthModal.switchTab('register')">Регистрация</button>
        </div>

        <!-- Форма входа -->
        <div id="authTabLogin" class="auth-tab-content active">
            <div class="auth-form-group">
                <label>Email</label>
                <input type="email" id="login-email" class="profile-input" placeholder="ivan@example.com" autocomplete="email">
            </div>
            <div class="auth-form-group">
                <label>Пароль</label>
                <input type="password" id="login-password" class="profile-input" placeholder="••••••••" autocomplete="current-password">
            </div>
            <div id="login-error" class="auth-error" style="display:none;"></div>
            <button class="btn-primary-lg auth-submit-btn" id="login-submit" onclick="AuthModal.login()">
                <i class="fas fa-sign-in-alt"></i> Войти
            </button>
            <p class="auth-switch-hint">Нет аккаунта? <a href="#" onclick="AuthModal.switchTab('register'); return false;">Зарегистрируйтесь</a></p>
        </div>

        <!-- Форма регистрации -->
        <div id="authTabRegister" class="auth-tab-content">
            <div class="auth-form-row">
                <div class="auth-form-group">
                    <label>Имя <span class="req">*</span></label>
                    <input type="text" id="reg-firstname" class="profile-input" placeholder="Иван" autocomplete="given-name">
                </div>
                <div class="auth-form-group">
                    <label>Фамилия</label>
                    <input type="text" id="reg-lastname" class="profile-input" placeholder="Иванов" autocomplete="family-name">
                </div>
            </div>
            <div class="auth-form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" id="reg-email" class="profile-input" placeholder="ivan@example.com" autocomplete="email">
            </div>
            <div class="auth-form-group">
                <label>Телефон</label>
                <input type="tel" id="reg-phone" class="profile-input" placeholder="+7 (___) ___-__-__" autocomplete="tel">
            </div>
            <div class="auth-form-row">
                <div class="auth-form-group">
                    <label>Пароль <span class="req">*</span></label>
                    <input type="password" id="reg-password" class="profile-input" placeholder="••••••••" autocomplete="new-password">
                </div>
                <div class="auth-form-group">
                    <label>Повторите пароль <span class="req">*</span></label>
                    <input type="password" id="reg-password2" class="profile-input" placeholder="••••••••" autocomplete="new-password">
                </div>
            </div>
            <div id="reg-error" class="auth-error" style="display:none;"></div>
            <button class="btn-primary-lg auth-submit-btn" id="reg-submit" onclick="AuthModal.register()">
                <i class="fas fa-user-plus"></i> Зарегистрироваться
            </button>
            <p class="auth-switch-hint">Уже есть аккаунт? <a href="#" onclick="AuthModal.switchTab('login'); return false;">Войдите</a></p>
        </div>
    </div>
</div>

<!-- ===== МОДАЛЬНОЕ ОКНО ОБРАЩЕНИЯ ===== -->
<div id="supportModal" class="auth-modal-overlay" style="display:none;" onclick="if(event.target===this)SupportModal.close()">
    <div class="auth-modal" style="max-width:520px;width:90%;">
        <button class="auth-modal-close" onclick="SupportModal.close()" aria-label="Закрыть">
            <i class="fas fa-times"></i>
        </button>
        <div class="auth-logo"><i class="fas fa-comment-dots"></i> Связаться с нами</div>
        <p style="text-align:center;color:var(--text-secondary);font-size:14px;margin:-8px 0 18px;">Онлайн-поддержка — ответим в ближайшее время</p>
        <div class="auth-form-group">
            <label>Ваше имя <span class="req">*</span></label>
            <input type="text" id="sup-name" class="profile-input" placeholder="Иван Иванов"
                value="<?= $currentUser ? htmlspecialchars(trim($currentUser['firstname'].' '.$currentUser['lastname'])) : '' ?>">
        </div>
        <div class="auth-form-group">
            <label>Email <span class="req">*</span></label>
            <input type="email" id="sup-email" class="profile-input" placeholder="ivan@example.com"
                value="<?= $currentUser ? htmlspecialchars($currentUser['email']) : '' ?>">
        </div>
        <div class="auth-form-group">
            <label>Тема обращения</label>
            <input type="text" id="sup-subject" class="profile-input" placeholder="Вопрос по заказу, возврат, другое...">
        </div>
        <div class="auth-form-group">
            <label>Сообщение <span class="req">*</span></label>
            <textarea id="sup-message" class="profile-input" rows="5" placeholder="Опишите ваш вопрос подробнее..." style="resize:vertical;font-family:inherit;"></textarea>
        </div>
        <div id="sup-error" class="auth-error" style="display:none;"></div>
        <div id="sup-success" style="display:none;text-align:center;padding:16px;color:#22c55e;font-weight:600;"><i class="fas fa-check-circle"></i> Сообщение отправлено! Ожидайте ответа.</div>
        <button class="btn-primary-lg auth-submit-btn" id="sup-submit" onclick="SupportModal.send()">
            <i class="fas fa-paper-plane"></i> Отправить
        </button>
    </div>
</div>

<script>window.CURRENT_USER_ID = <?= intval($currentUser['id'] ?? 0) ?>;</script>
<script src="js/theme.js?v=<?= filemtime(__DIR__ . '/../js/theme.js') ?>"></script>
<script src="js/catalog.js?v=<?= filemtime(__DIR__ . '/../js/catalog.js') ?>"></script>
<script src="js/cart.js?v=<?= filemtime(__DIR__ . '/../js/cart.js') ?>"></script>
<script src="js/auth.js?v=<?= filemtime(__DIR__ . '/../js/auth.js') ?>"></script>
<script>
const SupportModal = {
    open() {
        document.getElementById('sup-error').style.display   = 'none';
        document.getElementById('sup-success').style.display = 'none';
        document.getElementById('sup-submit').style.display  = '';
        document.getElementById('supportModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    },
    close() {
        document.getElementById('supportModal').style.display = 'none';
        document.body.style.overflow = '';
    },
    async send() {
        const name    = document.getElementById('sup-name').value.trim();
        const email   = document.getElementById('sup-email').value.trim();
        const subject = document.getElementById('sup-subject').value.trim();
        const message = document.getElementById('sup-message').value.trim();
        const errEl   = document.getElementById('sup-error');

        errEl.style.display = 'none';
        if (!name)    { errEl.textContent = 'Введите имя';      errEl.style.display = 'block'; return; }
        if (!email)   { errEl.textContent = 'Введите email';    errEl.style.display = 'block'; return; }
        if (!message) { errEl.textContent = 'Введите сообщение'; errEl.style.display = 'block'; return; }

        const btn = document.getElementById('sup-submit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';

        try {
            const fd = new FormData();
            fd.append('action',  'send_support');
            fd.append('name',    name);
            fd.append('email',   email);
            fd.append('subject', subject);
            fd.append('message', message);
            const res = await fetch('api/reviews.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                document.getElementById('sup-success').style.display = 'block';
                btn.style.display = 'none';
                document.getElementById('sup-message').value = '';
                document.getElementById('sup-subject').value = '';
            } else {
                errEl.textContent = res.message || 'Ошибка. Попробуйте ещё раз.';
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Отправить';
            }
        } catch {
            errEl.textContent = 'Ошибка сети. Проверьте подключение.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Отправить';
        }
    }
};
</script>

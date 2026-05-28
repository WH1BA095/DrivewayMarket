<?php require_once 'includes/header.php'; ?>

<main class="main-content">
    <div class="page-container">

        <div class="breadcrumbs">
            <a href="index.php">Главная</a>
            <span class="breadcrumb-separator">›</span>
            <span class="current">Корзина</span>
        </div>

        <h1 class="page-title"><i class="fas fa-shopping-cart"></i> Корзина</h1>

        <div id="cart-empty" class="cart-empty" style="display:none;">
            <div class="cart-empty-icon"><i class="fas fa-shopping-cart"></i></div>
            <h2>Корзина пуста</h2>
            <p>Добавьте товары из каталога, чтобы оформить заказ</p>
            <a href="catalog.php" class="btn-primary-lg">
                <i class="fas fa-arrow-left"></i> Перейти в каталог
            </a>
        </div>

        <div id="cart-content" class="cart-layout" style="display:none;">

            <div class="cart-items-col">
                <div class="cart-card">
                    <div class="cart-card-header">
                        <span id="cart-items-count">0 товаров</span>
                        <button id="cart-clear-btn" class="cart-clear-btn">
                            <i class="fas fa-trash"></i> Очистить корзину
                        </button>
                    </div>
                    <div id="cart-items-list"></div>
                </div>
            </div>

            <div class="cart-summary-col">
                <div class="cart-card cart-summary-card">
                    <h3 class="cart-summary-title">Итого</h3>

                    <div class="cart-summary-rows">
                        <div class="cart-summary-row">
                            <span>Товары (<span id="sum-count">0</span> шт.)</span>
                            <span id="sum-price">0 ₽</span>
                        </div>
                        <div class="cart-summary-row">
                            <span>Доставка</span>
                            <span class="text-green">Бесплатно</span>
                        </div>
                    </div>

                    <div class="cart-summary-total">
                        <span>К оплате</span>
                        <span id="sum-total">0 ₽</span>
                    </div>

                    <a href="checkout.php" class="btn-primary-lg btn-block">
                        <i class="fas fa-credit-card"></i> Оформить заказ
                    </a>
                    <a href="catalog.php" class="btn-outline-lg btn-block">
                        <i class="fas fa-arrow-left"></i> Продолжить покупки
                    </a>

                    <div class="cart-payment-icons">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-mir"></i>
                    </div>
                </div>

                <div class="cart-card cart-perks">
                    <div class="cart-perk"><i class="fas fa-truck"></i><span>Бесплатная доставка от 3 000 ₽</span></div>
                    <div class="cart-perk"><i class="fas fa-shield-alt"></i><span>Гарантия качества</span></div>
                    <div class="cart-perk"><i class="fas fa-undo"></i><span>Возврат в течение 14 дней</span></div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>

<script src="js/cart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Рендерим сразу из localStorage, затем обновляем остатки в фоне
    renderCart();
    Cart.refreshAvailability(() => renderCart());

    document.getElementById('cart-clear-btn').addEventListener('click', () => {
        if (confirm('Очистить корзину?')) {
            Cart.clear();
            renderCart();
        }
    });

    function renderCart() {
        const items = Cart.get();
        const empty = document.getElementById('cart-empty');
        const content = document.getElementById('cart-content');

        if (items.length === 0) {
            empty.style.display = 'flex';
            content.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        content.style.display = 'grid';

        const list = document.getElementById('cart-items-list');
        list.innerHTML = items.map(item => `
            <div class="cart-item" id="item-${item.id}">
                <div class="cart-item-img">
                    ${item.image
                        ? `<img src="${item.image}" alt="${esc(item.name)}">`
                        : `<div class="cart-item-no-img"><i class="fas fa-car"></i></div>`}
                </div>
                <div class="cart-item-info">
                    <div class="cart-item-name">${esc(item.name)}</div>
                    ${item.article ? `<div class="cart-item-article">Арт: ${esc(item.article)}</div>` : ''}
                    <div class="cart-item-price-unit">${fmt(item.price)} ₽ / шт.</div>
                </div>
                <div class="cart-item-controls">
                    <div class="qty-control">
                        <button class="qty-btn" onclick="changeQty(${item.id}, ${item.qty - 1})">−</button>
                        <span class="qty-val">${item.qty}</span>
                        <button class="qty-btn${item.qty >= (item.available || 99) ? ' qty-btn-disabled' : ''}" ${item.qty >= (item.available || 99) ? 'disabled title="Макс. ' + (item.available || 99) + ' шт."' : ''} onclick="changeQty(${item.id}, ${item.qty + 1})">+</button>
                    </div>
                    ${item.available && item.qty >= item.available ? `<div class="qty-max-note">Макс. ${item.available} шт.</div>` : ''}
                    <div class="cart-item-total">${fmt(item.price * item.qty)} ₽</div>
                    <button class="cart-item-remove" onclick="removeItem(${item.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `).join('');

        const totalQty = Cart.count();
        const totalSum = Cart.total();
        document.getElementById('cart-items-count').textContent = declItems(items.length);
        document.getElementById('sum-count').textContent = totalQty;
        document.getElementById('sum-price').textContent = fmt(totalSum) + ' ₽';
        document.getElementById('sum-total').textContent = fmt(totalSum) + ' ₽';
    }

    window.changeQty = (id, qty) => { Cart.setQty(id, qty); renderCart(); };
    window.removeItem = (id) => {
        const el = document.getElementById('item-' + id);
        if (el) { el.style.opacity = '0'; el.style.transform = 'translateX(20px)'; }
        setTimeout(() => { Cart.remove(id); renderCart(); }, 250);
    };

    function fmt(n) { return Math.round(n).toLocaleString('ru-RU'); }
    function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function declItems(n) {
        const v = n % 100, v1 = n % 10;
        if (v >= 11 && v <= 19) return n + ' товаров';
        if (v1 === 1) return n + ' товар';
        if (v1 >= 2 && v1 <= 4) return n + ' товара';
        return n + ' товаров';
    }
});
</script>

<?php
require_once 'includes/header.php';
require_once 'includes/catalog_functions.php';

$isFavorite = false;
if (isLoggedIn()) {
    require_once 'config/db.php';
    $productIdCheck = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($productIdCheck) {
        $favSt = Database::getInstance()->getConnection()
            ->prepare('SELECT id FROM user_favorites WHERE user_id=? AND product_id=?');
        $favSt->execute([$_SESSION['user_id'], $productIdCheck]);
        $isFavorite = (bool)$favSt->fetch();
    }
}

$catalog = new Catalog();
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = $catalog->getProductById($product_id);

if (!$product) {
    header('Location: catalog.php');
    exit;
}

$similar_products = $catalog->getRecommendedProducts($product_id, 4);

require_once 'config/db.php';
$galleryDb = Database::getInstance()->getConnection();
$gallerySt = $galleryDb->prepare('SELECT id, filename FROM product_images WHERE product_id=? ORDER BY sort_order ASC');
$gallerySt->execute([$product_id]);
$galleryImages = $gallerySt->fetchAll(PDO::FETCH_ASSOC);
// Если галереи нет — используем поле image как fallback
if (empty($galleryImages) && $product['image']) {
    $galleryImages = [['id'=>0, 'filename'=>$product['image']]];
}
?>

<main class="main-content">
    <div class="product-page-container">
        
        <div class="breadcrumbs">
            <a href="index.php">Главная</a>
            <span class="breadcrumb-separator">›</span>
            <a href="catalog.php">Каталог</a>
            <span class="breadcrumb-separator">›</span>
            <a href="catalog.php?category=<?= $product['category_slug'] ?>">
                <?= htmlspecialchars($product['category_name']) ?>
            </a>
            <span class="breadcrumb-separator">›</span>
            <span class="current"><?= htmlspecialchars(mb_substr($product['name'], 0, 50)) ?>...</span>
        </div>

        <div class="product-card-detailed">
            <div class="product-gallery">
                <div class="product-main-image" id="product-main-image">
                    <?php if (!empty($galleryImages)): ?>
                        <img src="<?= htmlspecialchars($galleryImages[0]['filename']) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             id="gallery-main-img">
                    <?php else: ?>
                        <div class="product-no-image-large">
                            <i class="fas fa-car"></i>
                            <span>Нет изображения</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-badges-detailed">
                        <?php if ($product['available'] > 0): ?>
                            <?php if ($product['available'] < 5): ?>
                                <span class="badge-detailed badge-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Осталось мало
                                </span>
                            <?php endif; ?>
                            <span class="badge-detailed badge-success">
                                <i class="fas fa-check-circle"></i> В наличии
                            </span>
                        <?php elseif ($product['quantity'] > 0): ?>
                            <span class="badge-detailed badge-info">
                                <i class="fas fa-clock"></i> Скоро поступит
                            </span>
                        <?php else: ?>
                            <span class="badge-detailed badge-danger">
                                <i class="fas fa-times-circle"></i> Нет в наличии
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($galleryImages) > 1): ?>
                <div class="gallery-thumbs" id="gallery-thumbs">
                    <?php foreach ($galleryImages as $i => $gimg): ?>
                    <button type="button"
                            class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
                            data-src="<?= htmlspecialchars($gimg['filename']) ?>"
                            onclick="gallerySwitch(this)">
                        <img src="<?= htmlspecialchars($gimg['filename']) ?>"
                             alt="Фото <?= $i+1 ?>">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="product-info-detailed">
                <div class="product-header">
                    <h1 class="product-title-large"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="product-meta">
                        <div class="product-meta-item">
                            <span class="meta-label">Артикул:</span>
                            <span class="meta-value article-value"><?= htmlspecialchars($product['article']) ?></span>
                        </div>
                        <div class="product-meta-item">
                            <span class="meta-label">Бренд:</span>
                            <span class="meta-value brand-value"><?= htmlspecialchars($product['brand_name']) ?></span>
                        </div>
                        <div class="product-meta-item">
                            <span class="meta-label">Модель:</span>
                            <span class="meta-value"><?= htmlspecialchars($product['model_name']) ?></span>
                        </div>
                        <div class="product-meta-item">
                            <span class="meta-label">Категория:</span>
                            <span class="meta-value"><?= htmlspecialchars($product['category_name']) ?></span>
                        </div>
                        <div class="product-meta-item">
                            <span class="meta-label">Тип:</span>
                            <span class="meta-value"><?= htmlspecialchars($product['type_name']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="product-price-section">
                    <div class="product-price-large">
                        <?= number_format($product['price'], 0, '', ' ') ?> ₽
                    </div>
                    
                    <div class="product-stock-info">
                        <?php if ($product['available'] > 0): ?>
                            <div class="stock-status in-stock">
                                <i class="fas fa-check-circle"></i>
                                В наличии: 
                                <span class="stock-quantity">
                                    <?php if ($product['available'] > 20): ?>
                                        более 20 шт.
                                    <?php else: ?>
                                        <?= $product['available'] ?> шт.
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="delivery-info">
                                <i class="fas fa-truck"></i>
                                <?php if ($product['available'] >= 5): ?>
                                    Доставка сегодня-завтра
                                <?php else: ?>
                                    Доставка 1-2 дня
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($product['available'] < 5): ?>
                                <div class="stock-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Осталось всего <?= $product['available'] ?> шт. — торопитесь!
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($product['quantity'] > 0): ?>
                            <div class="stock-status coming-soon">
                                <i class="fas fa-clock"></i>
                                Скоро поступит
                            </div>
                            <div class="delivery-info">
                                <i class="fas fa-calendar-alt"></i>
                                Ожидаемая поставка: 3-5 дней
                            </div>
                            <div class="stock-preorder">
                                <i class="fas fa-box"></i>
                                Доступен предзаказ
                            </div>
                        <?php else: ?>
                            <div class="stock-status out-of-stock">
                                <i class="fas fa-times-circle"></i>
                                Нет в наличии
                            </div>
                            <div class="delivery-info">
                                <i class="fas fa-calendar-alt"></i>
                                Срок поставки уточняйте
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="product-actions">
                    <?php if ($product['available'] > 0): ?>
                        <div class="quantity-selector">
                            <button class="quantity-btn minus" onclick="decreaseQuantity()">−</button>
                            <input type="number" class="quantity-input" id="quantity" value="1" 
                                   min="1" max="<?= min($product['available'], 99) ?>">
                            <button class="quantity-btn plus" onclick="increaseQuantity()">+</button>
                        </div>
                        
                        <button class="add-to-cart-large"
                            data-id="<?= $product['id'] ?>"
                            data-name="<?= htmlspecialchars($product['name']) ?>"
                            data-price="<?= $product['price'] ?>"
                            data-article="<?= htmlspecialchars($product['article']) ?>"
                            data-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                            data-available="<?= $product['available'] ?>">
                            <i class="fas fa-shopping-cart"></i>
                            Добавить в корзину
                        </button>
                    <?php elseif ($product['quantity'] > 0): ?>
                        <button class="add-to-cart-large preorder-btn"
                            data-id="<?= $product['id'] ?>"
                            data-name="<?= htmlspecialchars($product['name']) ?>"
                            data-price="<?= $product['price'] ?>"
                            data-article="<?= htmlspecialchars($product['article']) ?>"
                            data-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                            data-available="<?= $product['available'] ?>">
                            <i class="fas fa-clock"></i>
                            Оформить предзаказ
                        </button>
                    <?php else: ?>
                        <button class="add-to-cart-large disabled" disabled>
                            <i class="fas fa-times-circle"></i>
                            Нет в наличии
                        </button>
                    <?php endif; ?>
                    
                    <button class="favorite-btn <?= $isFavorite ? 'active' : '' ?>"
                            id="fav-btn-main"
                            onclick="toggleFavorite(<?= $product['id'] ?>)"
                            title="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>">
                        <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-heart"></i>
                    </button>
                </div>

                <div class="product-payment-info">
                    <div class="payment-method">
                        <i class="fas fa-credit-card"></i>
                        <span>Онлайн оплата</span>
                    </div>
                    <div class="payment-method">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Наличные</span>
                    </div>
                    <div class="payment-method">
                        <i class="fas fa-truck"></i>
                        <span>Бесплатно от 5000 ₽</span>
                    </div>
                    <div class="payment-method">
                        <i class="fas fa-shield-alt"></i>
                        <span>Гарантия 2 года</span>
                    </div>
                </div>
                
                <div class="product-short-specs">
                    <div class="short-spec-item">
                        <i class="fas fa-cube"></i>
                        <span>Оригинальный номер: <?= htmlspecialchars($product['article']) ?></span>
                    </div>
                    <div class="short-spec-item">
                        <i class="fas fa-calendar"></i>
                        <span>Год выпуска: 2024</span>
                    </div>
                    <div class="short-spec-item">
                        <i class="fas fa-flag"></i>
                        <span>Страна: <?= htmlspecialchars($product['brand_name'] == 'BMW' || $product['brand_name'] == 'Mercedes' ? 'Германия' : ($product['brand_name'] == 'Toyota' ? 'Япония' : 'Россия')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="product-tabs">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="description">Описание</button>
                <button class="tab-btn" data-tab="specs">Характеристики</button>
                <button class="tab-btn" data-tab="compatibility">Совместимость</button>
                <button class="tab-btn" data-tab="reviews" id="tab-btn-reviews">Отзывы <span id="reviews-tab-count" class="tab-count"></span></button>
                <button class="tab-btn" data-tab="qa" id="tab-btn-qa">Вопросы <span id="qa-tab-count" class="tab-count"></span></button>
                <button class="tab-btn" data-tab="delivery">Доставка</button>
            </div>
            
            <div class="tabs-content">
                <div class="tab-pane active" id="tab-description">
                    <div class="product-description">
                        <?php if ($product['description']): ?>
                            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        <?php else: ?>
                            <p>Подробное описание товара временно отсутствует. Вы можете уточнить характеристики у наших менеджеров по телефону 8 (800) 777-99-80.</p>
                        <?php endif; ?>
                        
                        <div class="description-features">
                            <h3>Особенности:</h3>
                            <ul>
                                <li><i class="fas fa-check"></i> Оригинальное качество</li>
                                <li><i class="fas fa-check"></i> Полная совместимость</li>
                                <li><i class="fas fa-check"></i> Гарантия производителя</li>
                                <li><i class="fas fa-check"></i> Быстрая доставка</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane" id="tab-specs">
                    <div class="product-specs">
                        <div class="specs-table">
                            <div class="spec-row">
                                <div class="spec-name">Артикул</div>
                                <div class="spec-value"><?= htmlspecialchars($product['article']) ?></div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Производитель</div>
                                <div class="spec-value"><?= htmlspecialchars($product['brand_name']) ?></div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Модель авто</div>
                                <div class="spec-value"><?= htmlspecialchars($product['brand_name']) ?> <?= htmlspecialchars($product['model_name']) ?></div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Категория</div>
                                <div class="spec-value"><?= htmlspecialchars($product['category_name']) ?></div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Тип</div>
                                <div class="spec-value"><?= htmlspecialchars($product['type_name']) ?></div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Состояние</div>
                                <div class="spec-value">Новое</div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Гарантия</div>
                                <div class="spec-value">2 года</div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Наличие на складе</div>
                                <div class="spec-value">
                                    <?php if ($product['quantity'] > 0): ?>
                                        <?= $product['quantity'] ?> шт.
                                    <?php else: ?>
                                        Нет
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="spec-row">
                                <div class="spec-name">Доступно сейчас</div>
                                <div class="spec-value">
                                    <?php if ($product['available'] > 0): ?>
                                        <span class="spec-value-success"><?= $product['available'] ?> шт.</span>
                                    <?php else: ?>
                                        <span class="spec-value-danger">0 шт.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane" id="tab-compatibility">
                    <div class="product-compatibility">
                        <h3>Подходит для следующих автомобилей:</h3>
                        <div class="compatibility-list">
                            <div class="compatibility-item">
                                <span class="compatibility-brand"><?= htmlspecialchars($product['brand_name']) ?></span>
                                <span class="compatibility-model"><?= htmlspecialchars($product['model_name']) ?></span>
                                <span class="compatibility-year">(все года)</span>
                            </div>
                            <?php
                            $other_models = $catalog->getModelsByBrand($product['brand_id']);
                            foreach ($other_models as $model):
                                if ($model['id'] != $product['model_id']):
                            ?>
                                <div class="compatibility-item">
                                    <span class="compatibility-brand"><?= htmlspecialchars($product['brand_name']) ?></span>
                                    <span class="compatibility-model"><?= htmlspecialchars($model['name']) ?></span>
                                    <span class="compatibility-year">(уточняйте)</span>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        
                        <div class="compatibility-note">
                            <i class="fas fa-info-circle"></i>
                            <p>* Для точной проверки совместимости используйте VIN-код или свяжитесь с нашими менеджерами</p>
                        </div>
                        
                        <div class="vin-check">
                            <h4>Проверить по VIN:</h4>
                            <div class="vin-input-group">
                                <input type="text" class="vin-input" placeholder="Введите VIN-код автомобиля">
                                <button class="vin-check-btn">Проверить</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane" id="tab-reviews">
                    <div class="product-reviews">
                        <div class="reviews-summary" id="reviews-summary" style="display:none;">
                            <div class="reviews-avg-num" id="avg-num">0</div>
                            <div class="reviews-avg-stars" id="avg-stars"></div>
                            <div class="reviews-avg-cnt" id="avg-cnt"></div>
                        </div>

                        <?php if (isLoggedIn()): ?>
                        <div class="review-form-wrap" id="review-form-wrap">
                            <h4 class="review-form-title"><i class="fas fa-pen"></i> Ваш отзыв</h4>
                            <div class="star-input-row">
                                <span>Оценка:</span>
                                <div class="star-input" id="star-input">
                                    <?php for ($i=1; $i<=5; $i++): ?>
                                    <button type="button" class="si-star" data-v="<?= $i ?>"><i class="far fa-star"></i></button>
                                    <?php endfor; ?>
                                </div>
                                <span class="star-hint" id="star-hint">Выберите оценку</span>
                            </div>
                            <div class="rv-form-fields">
                                <input type="text" id="rv-title" class="profile-input" placeholder="Заголовок (необязательно)">
                                <textarea id="rv-body" class="profile-input" style="min-height:90px;resize:vertical;" placeholder="Поделитесь впечатлениями о товаре..."></textarea>
                            </div>
                            <div class="review-form-actions">
                                <button class="btn-primary-lg" onclick="submitReview()">
                                    <i class="fas fa-paper-plane"></i> Опубликовать отзыв
                                </button>
                            </div>
                            <div id="rv-msg" class="rv-inline-msg" style="display:none;"></div>
                        </div>
                        <?php else: ?>
                        <div class="review-login-prompt">
                            <i class="fas fa-lock"></i>
                            <p>Чтобы оставить отзыв, <a href="#" onclick="AuthModal.open('login');return false;">войдите в аккаунт</a></p>
                        </div>
                        <?php endif; ?>

                        <div id="reviews-list"><div class="rv-spinner"><i class="fas fa-spinner fa-spin"></i></div></div>
                    </div>
                </div>

                <div class="tab-pane" id="tab-qa">
                    <div class="product-qa">
                        <?php if (isLoggedIn()): ?>
                        <div class="qa-form-wrap">
                            <h4 class="review-form-title"><i class="fas fa-question-circle"></i> Задать вопрос</h4>
                            <div class="rv-form-fields">
                                <textarea id="qa-question" class="profile-input" style="min-height:70px;resize:vertical;" placeholder="Напишите вопрос о товаре..."></textarea>
                            </div>
                            <div class="review-form-actions">
                                <button class="btn-primary-lg" onclick="submitQuestion()">
                                    <i class="fas fa-paper-plane"></i> Отправить вопрос
                                </button>
                            </div>
                            <div id="qa-msg" class="rv-inline-msg" style="display:none;"></div>
                        </div>
                        <?php else: ?>
                        <div class="review-login-prompt">
                            <i class="fas fa-lock"></i>
                            <p>Чтобы задать вопрос, <a href="#" onclick="AuthModal.open('login');return false;">войдите в аккаунт</a></p>
                        </div>
                        <?php endif; ?>

                        <div id="qa-list"><div class="rv-spinner"><i class="fas fa-spinner fa-spin"></i></div></div>
                    </div>
                </div>
                
                <div class="tab-pane" id="tab-delivery">
                    <div class="delivery-info-tab">
                        <div class="delivery-methods">
                            <h3>Способы доставки</h3>
                            
                            <div class="delivery-method-item">
                                <i class="fas fa-truck"></i>
                                <div class="delivery-method-content">
                                    <h4>Курьером по Москве</h4>
                                    <p>Доставка завтра — 300 ₽ (бесплатно при заказе от 5000 ₽)</p>
                                </div>
                            </div>
                            
                            <div class="delivery-method-item">
                                <i class="fas fa-box"></i>
                                <div class="delivery-method-content">
                                    <h4>Самовывоз</h4>
                                    <p>Сегодня — бесплатно, 15 пунктов выдачи</p>
                                </div>
                            </div>
                            
                            <div class="delivery-method-item">
                                <i class="fas fa-plane"></i>
                                <div class="delivery-method-content">
                                    <h4>Доставка по России</h4>
                                    <p>СДЭК, Почта России — от 3 дней, от 350 ₽</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-methods-tab">
                            <h3>Способы оплаты</h3>
                            
                            <div class="payment-method-item">
                                <i class="fas fa-credit-card"></i>
                                <span>Банковской картой онлайн</span>
                            </div>
                            
                            <div class="payment-method-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Наличными при получении</span>
                            </div>
                            
                            <div class="payment-method-item">
                                <i class="fas fa-mobile-alt"></i>
                                <span>СБП (Система быстрых платежей)</span>
                            </div>
                            
                            <div class="payment-method-item">
                                <i class="fas fa-building"></i>
                                <span>Безналичный расчет для юрлиц</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($similar_products)): ?>
        <div class="similar-products">
            <h2 class="section-title">Похожие товары</h2>
            <div class="similar-products-grid">
                <?php foreach ($similar_products as $similar): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($similar['image']): ?>
                                <img src="<?= htmlspecialchars($similar['image']) ?>" alt="<?= htmlspecialchars($similar['name']) ?>">
                            <?php else: ?>
                                <div class="product-no-image">
                                    <i class="fas fa-car"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title">
                                <a href="product.php?id=<?= $similar['id'] ?>">
                                    <?= htmlspecialchars(mb_substr($similar['name'], 0, 50)) ?>...
                                </a>
                            </h3>
                            <div class="product-article">Арт: <?= htmlspecialchars($similar['article']) ?></div>
                            <div class="product-price">
                                <?= number_format($similar['price'], 0, '', ' ') ?> ₽
                            </div>
                            <div class="product-stock <?= $similar['available'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                <?php if ($similar['available'] > 0): ?>
                                    <i class="fas fa-check-circle"></i> В наличии
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i> Нет
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn"
                                data-id="<?= $similar['id'] ?>"
                                data-name="<?= htmlspecialchars($similar['name']) ?>"
                                data-price="<?= $similar['price'] ?>"
                                data-article="<?= htmlspecialchars($similar['article'] ?? '') ?>"
                                data-image="<?= htmlspecialchars($similar['image'] ?? '') ?>"
                                data-available="<?= $similar['available'] ?? 99 ?>">
                                <i class="fas fa-shopping-cart"></i> В корзину
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function decreaseQuantity() {
    let input = document.getElementById('quantity');
    let value = parseInt(input.value);
    if (value > 1) {
        input.value = value - 1;
    }
}

function increaseQuantity() {
    let input = document.getElementById('quantity');
    let value = parseInt(input.value);
    let max = parseInt(input.max);
    if (value < max) {
        input.value = value + 1;
    }
}

const USER_LOGGED_IN = <?= isLoggedIn() ? 'true' : 'false' ?>;

async function toggleFavorite(productId) {
    if (!USER_LOGGED_IN) {
        AuthModal.open('login');
        return;
    }

    const btn  = document.getElementById('fav-btn-main');
    const icon = btn.querySelector('i');
    const isActive = btn.classList.contains('active');

    // оптимистичное обновление UI
    btn.classList.toggle('active');
    icon.classList.toggle('fas', !isActive);
    icon.classList.toggle('far', isActive);
    btn.title = isActive ? 'Добавить в избранное' : 'Убрать из избранного';

    const fd = new FormData();
    fd.append('action',     isActive ? 'remove_favorite' : 'add_favorite');
    fd.append('product_id', productId);

    try {
        const res = await fetch('api/auth.php', { method: 'POST', body: fd }).then(r => r.json());
        if (!res.success) {
            // откат если ошибка
            btn.classList.toggle('active');
            icon.classList.toggle('fas', isActive);
            icon.classList.toggle('far', !isActive);
        }
    } catch(e) {
        // откат при сетевой ошибке
        btn.classList.toggle('active');
        icon.classList.toggle('fas', isActive);
        icon.classList.toggle('far', !isActive);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`tab-${tabId}`).classList.add('active');
        });
    });

    const productId = <?= $product['id'] ?>;
    
    function checkActualQuantity() {
        fetch(`api/check-stock.php?id=${productId}`)
            .then(response => response.json())
            .then(data => {
                const quantityInput = document.getElementById('quantity');
                if (quantityInput) {
                    quantityInput.max = Math.min(data.available, 99);
                    if (parseInt(quantityInput.value) > data.available) {
                        quantityInput.value = data.available;
                    }
                }
                const stockQuantityEl = document.querySelector('.stock-quantity');
                if (stockQuantityEl && data.available > 0) {
                    if (data.available > 20) {
                        stockQuantityEl.textContent = 'более 20 шт.';
                    } else {
                        stockQuantityEl.textContent = data.available + ' шт.';
                    }
                }
            });
    }

    // проверяем каждые 30 секунд
    if (document.getElementById('quantity')) {
        setInterval(checkActualQuantity, 30000);
    }
});


document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.querySelector('.add-to-cart-large:not(.disabled)');
    if (!addBtn) return;

    addBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const qtyInput  = document.getElementById('quantity');
        const qty       = qtyInput ? Math.max(1, parseInt(qtyInput.value) || 1) : 1;
        const available = parseInt(addBtn.dataset.available) || 99;

        const product = {
            id:        parseInt(addBtn.dataset.id),
            name:      addBtn.dataset.name,
            price:     parseFloat(addBtn.dataset.price),
            article:   addBtn.dataset.article || '',
            image:     addBtn.dataset.image  || '',
            available: available,
        };
        if (!product.id || !product.name || !product.price) return;

        const items    = Cart.get();
        const existing = items.find(i => i.id === product.id);
        const maxQty   = available;

        if (existing) {
            if (existing.qty >= maxQty) {
                Cart.showToast(product.name, true);
                return;
            }
            existing.qty = Math.min(existing.qty + qty, maxQty);
            Cart.save(items);
        } else {
            product.qty = Math.min(qty, maxQty);
            items.push(product);
            Cart.save(items);
        }
        Cart.showToast(product.name, false);

        const orig = addBtn.innerHTML;
        addBtn.innerHTML = '<i class="fas fa-check"></i> Добавлено в корзину';
        addBtn.style.background = '#2ecc71';
        setTimeout(() => {
            addBtn.innerHTML = orig;
            addBtn.style.background = '';
        }, 1800);
    });
});

const PROD_ID  = <?= (int)$product['id'] ?>;
const API_RV   = 'api/reviews.php';
const LOGGED   = <?= isLoggedIn() ? 'true' : 'false' ?>;

let selectedRating = 0;
let reviewsLoaded  = false;
let qaLoaded       = false;

const STAR_LABELS = ['','Ужасно','Плохо','Нормально','Хорошо','Отлично'];

document.addEventListener('DOMContentLoaded', () => {
    const stars = document.querySelectorAll('.si-star');
    stars.forEach(btn => {
        btn.addEventListener('click', () => {
            selectedRating = parseInt(btn.dataset.v);
            stars.forEach((s, i) => {
                s.querySelector('i').className = i < selectedRating ? 'fas fa-star' : 'far fa-star';
            });
            document.getElementById('star-hint').textContent = STAR_LABELS[selectedRating];
        });
        btn.addEventListener('mouseenter', () => {
            const v = parseInt(btn.dataset.v);
            stars.forEach((s, i) => {
                s.querySelector('i').className = i < v ? 'fas fa-star' : 'far fa-star';
            });
        });
        btn.addEventListener('mouseleave', () => {
            stars.forEach((s, i) => {
                s.querySelector('i').className = i < selectedRating ? 'fas fa-star' : 'far fa-star';
            });
        });
    });

    // загружаем сразу — нужно для счётчиков на табах
    loadReviews();
    loadQuestions();

    document.getElementById('tab-btn-reviews')?.addEventListener('click', () => {
        if (!reviewsLoaded) loadReviews();
    });
    document.getElementById('tab-btn-qa')?.addEventListener('click', () => {
        if (!qaLoaded) loadQuestions();
    });
});

async function loadReviews() {
    const res = await fetch(`${API_RV}?action=get_reviews&product_id=${PROD_ID}`).then(r => r.json()).catch(() => null);
    if (!res || !res.success) return;
    reviewsLoaded = true;

    const cnt = res.count || 0;
    const tcEl = document.getElementById('reviews-tab-count');
    if (tcEl) tcEl.textContent = cnt > 0 ? `(${cnt})` : '';

    const sumEl = document.getElementById('reviews-summary');
    if (cnt > 0 && sumEl) {
        document.getElementById('avg-num').textContent  = res.avg.toFixed(1);
        document.getElementById('avg-stars').innerHTML  = renderStars(res.avg);
        document.getElementById('avg-cnt').textContent  = cnt + ' ' + declReviews(cnt);
        sumEl.style.display = 'flex';
    }

    // если есть свой отзыв — подставить в форму
    if (res.my_review && LOGGED) {
        selectedRating = res.my_review.rating;
        document.querySelectorAll('.si-star').forEach((s, i) => {
            s.querySelector('i').className = i < selectedRating ? 'fas fa-star' : 'far fa-star';
        });
        const hintEl = document.getElementById('star-hint');
        if (hintEl) hintEl.textContent = STAR_LABELS[selectedRating];
        const titleEl = document.getElementById('rv-title');
        if (titleEl) titleEl.value = res.my_review.title || '';
        const bodyEl = document.getElementById('rv-body');
        if (bodyEl)  bodyEl.value  = res.my_review.body  || '';
    }

    renderReviews(res.reviews || []);
}

function renderReviews(reviews) {
    const el = document.getElementById('reviews-list');
    if (!el) return;
    if (!reviews.length) {
        el.innerHTML = '<div class="rv-empty-block"><i class="far fa-comment-dots"></i><p>Пока нет отзывов — будьте первым!</p></div>';
        return;
    }
    el.innerHTML = reviews.map(r => `
        <div class="rv-item" id="rvi-${r.id}">
            <div class="rv-item-head">
                <div class="rvi-avatar">${(r.display_name||'?')[0].toUpperCase()}</div>
                <div class="rvi-meta">
                    <span class="rvi-name">${esc(r.display_name)}</span>
                    <span class="rvi-date">${fmtDate(r.created_at)}</span>
                </div>
                <div class="rvi-right">
                    ${renderStars(r.rating)}
                    ${r.is_mine ? `<button class="rvi-del-btn" onclick="deleteMyReview(${r.id})" title="Удалить"><i class="fas fa-times"></i></button>` : ''}
                </div>
            </div>
            ${r.title ? `<div class="rvi-title">${esc(r.title)}</div>` : ''}
            <div class="rvi-body">${esc(r.body)}</div>
            ${r.admin_reply ? `
                <div class="rvi-admin-reply">
                    <div class="rvi-admin-label"><i class="fas fa-store"></i> Ответ Driveway</div>
                    <div>${esc(r.admin_reply)}</div>
                </div>` : ''}
        </div>`).join('');
}

async function submitReview() {
    const body = document.getElementById('rv-body')?.value.trim();
    const title = document.getElementById('rv-title')?.value.trim();
    const msgEl = document.getElementById('rv-msg');

    if (!selectedRating) { showRvMsg('Выберите оценку', false); return; }
    if (!body || body.length < 5) { showRvMsg('Напишите отзыв (минимум 5 символов)', false); return; }

    const fd = new FormData();
    fd.append('action', 'add_review');
    fd.append('product_id', PROD_ID);
    fd.append('rating', selectedRating);
    fd.append('title',  title);
    fd.append('body',   body);

    const res = await fetch(API_RV, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) {
        showRvMsg(res.message, true);
        reviewsLoaded = false;
        loadReviews();
    } else {
        showRvMsg(res.message, false);
    }
}

async function deleteMyReview(id) {
    if (!confirm('Удалить ваш отзыв?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_review');
    fd.append('review_id', id);
    const res = await fetch(API_RV, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) { reviewsLoaded = false; loadReviews(); }
}

function showRvMsg(msg, ok) {
    const el = document.getElementById('rv-msg');
    if (!el) return;
    el.innerHTML = `<i class="fas fa-${ok ? 'check-circle' : 'exclamation-circle'}"></i> ${esc(msg)}`;
    el.className = 'rv-inline-msg' + (ok ? ' ok' : ' err');
    el.style.display = 'flex';
    if (ok) setTimeout(() => el.style.display = 'none', 3500);
}

async function loadQuestions() {
    const res = await fetch(`${API_RV}?action=get_questions&product_id=${PROD_ID}`).then(r => r.json()).catch(() => null);
    if (!res || !res.success) return;
    qaLoaded = true;

    const cnt = (res.questions || []).length;
    const tcEl = document.getElementById('qa-tab-count');
    if (tcEl) tcEl.textContent = cnt > 0 ? `(${cnt})` : '';

    renderQuestions(res.questions || []);
}

function renderQuestions(questions) {
    const el = document.getElementById('qa-list');
    if (!el) return;
    if (!questions.length) {
        el.innerHTML = '<div class="rv-empty-block"><i class="far fa-question-circle"></i><p>Вопросов пока нет. Задайте первый!</p></div>';
        return;
    }
    el.innerHTML = questions.map(q => `
        <div class="qa-item">
            <div class="qa-question">
                <div class="qa-q-icon"><i class="fas fa-question"></i></div>
                <div class="qa-q-body">
                    <div class="qa-q-text">${esc(q.question)}</div>
                    <div class="qa-q-meta">${esc(q.display_name)} · ${fmtDate(q.created_at)}
                        ${q.is_mine ? `<button class="rvi-del-btn" onclick="deleteMyQuestion(${q.id})" style="margin-left:8px"><i class="fas fa-times"></i></button>` : ''}
                    </div>
                </div>
            </div>
            ${q.answer ? `
                <div class="qa-answer">
                    <div class="qa-a-icon"><i class="fas fa-store"></i></div>
                    <div class="qa-a-body">
                        <div class="qa-a-label">Ответ Driveway</div>
                        <div>${esc(q.answer)}</div>
                        <div class="qa-q-meta">${fmtDate(q.answered_at)}</div>
                    </div>
                </div>` : '<div class="qa-pending">Ожидает ответа</div>'}
        </div>`).join('');
}

async function submitQuestion() {
    const question = document.getElementById('qa-question')?.value.trim();
    if (!question || question.length < 5) { showQaMsg('Вопрос слишком короткий', false); return; }

    const fd = new FormData();
    fd.append('action', 'add_question');
    fd.append('product_id', PROD_ID);
    fd.append('question', question);

    const res = await fetch(API_RV, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) {
        document.getElementById('qa-question').value = '';
        showQaMsg(res.message, true);
        qaLoaded = false;
        loadQuestions();
    } else {
        showQaMsg(res.message, false);
    }
}

async function deleteMyQuestion(id) {
    if (!confirm('Удалить вопрос?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_question');
    fd.append('question_id', id);
    const res = await fetch(API_RV, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) { qaLoaded = false; loadQuestions(); }
}

function showQaMsg(msg, ok) {
    const el = document.getElementById('qa-msg');
    if (!el) return;
    el.innerHTML = `<i class="fas fa-${ok ? 'check-circle' : 'exclamation-circle'}"></i> ${esc(msg)}`;
    el.className = 'rv-inline-msg' + (ok ? ' ok' : ' err');
    el.style.display = 'flex';
    if (ok) setTimeout(() => el.style.display = 'none', 4000);
}

function renderStars(rating) {
    let s = '';
    for (let i = 1; i <= 5; i++) {
        if (rating >= i)       s += '<i class="fas fa-star"></i>';
        else if (rating > i-1) s += '<i class="fas fa-star-half-alt"></i>';
        else                   s += '<i class="far fa-star"></i>';
    }
    return `<span class="stars-row">${s}</span>`;
}
function fmtDate(s) {
    if (!s) return '';
    return new Date(s).toLocaleDateString('ru-RU', { day:'2-digit', month:'long', year:'numeric' });
}
function declReviews(n) {
    const v=n%100, v1=n%10;
    if (v>=11&&v<=19) return 'отзывов';
    if (v1===1) return 'отзыв';
    if (v1>=2&&v1<=4) return 'отзыва';
    return 'отзывов';
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function gallerySwitch(btn) {
    const mainImg = document.getElementById('gallery-main-img');
    if (!mainImg) return;
    mainImg.src = btn.dataset.src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
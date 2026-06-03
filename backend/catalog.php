<?php
require_once 'includes/header.php';
require_once 'includes/catalog_functions.php';

$catalog = new Catalog();

$category_slug = $_GET['category'] ?? '';
$type_id = $_GET['type'] ?? '';
$brand_id = $_GET['brand'] ?? '';
$model_id = $_GET['model'] ?? '';
$in_stock = $_GET['in_stock'] ?? '';
$sort = $_GET['sort'] ?? 'default';
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = $search ? 100 : 12;
$offset = $search ? 0 : ($page - 1) * $limit;

$category_id = null;
$current_category = null;
if ($category_slug) {
    $current_category = $catalog->getCategoryBySlug($category_slug);
    $category_id = $current_category['id'] ?? null;
}

$filters = [
    'category_id' => $category_id,
    'type_id' => $type_id,
    'brand_id' => $brand_id,
    'model_id' => $model_id,
    'in_stock' => $in_stock,
    'sort' => $sort,
    'search' => $search,
];

$products = $catalog->getProducts($filters, $limit, $offset);
$total_products = $catalog->getTotalProducts($filters);
$total_pages = ceil($total_products / $limit);

$categories = $catalog->getCategories();
$types = $category_id ? $catalog->getTypesByCategory($category_id) : [];
$brands = $catalog->getCarBrands();
$models = $brand_id ? $catalog->getModelsByBrand($brand_id) : [];

$base_url = "catalog.php?";
$params = [];
if ($category_slug) $params[] = "category=$category_slug";
if ($type_id) $params[] = "type=$type_id";
if ($brand_id) $params[] = "brand=$brand_id";
if ($model_id) $params[] = "model=$model_id";
if ($in_stock) $params[] = "in_stock=$in_stock";
$filter_url = $base_url . implode('&', $params);
?>

<main class="main-content">
    <div class="catalog-container">
        
        <div class="breadcrumbs">
            <a href="index.php">Главная</a>
            <span class="breadcrumb-separator">›</span>
            <span class="current">Каталог</span>
            <?php if ($current_category): ?>
                <span class="breadcrumb-separator">›</span>
                <span class="current"><?= htmlspecialchars($current_category['name']) ?></span>
            <?php endif; ?>
        </div>
        
        <div class="catalog-header">
            <h1 class="catalog-title">
                <?php if ($search): ?>
                    Результаты поиска: «<?= htmlspecialchars($search) ?>»
                <?php elseif ($current_category): ?>
                    <?= htmlspecialchars($current_category['name']) ?>
                <?php else: ?>
                    Каталог товаров
                <?php endif; ?>
            </h1>
            <p class="catalog-description">
                Найдено <?= $total_products ?> товаров
                <?php if ($in_stock): ?>
                    <span class="in-stock-badge-filter">в наличии</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="catalog-wrapper">
            <aside class="catalog-filters">
                <div class="filters-header">
                    <h3>Фильтры</h3>
                    <a href="catalog.php" class="clear-filters">
                        <i class="fas fa-times"></i> Сбросить
                    </a>
                </div>
                
                <div class="filter-section">
                    <h4 class="filter-title">Категории</h4>
                    <div class="filter-options">
                        <a href="catalog.php" class="filter-option <?= !$category_slug ? 'active' : '' ?>">
                            Все категории
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="catalog.php?category=<?= $cat['slug'] ?>" 
                               class="filter-option <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($types): ?>
                <div class="filter-section">
                    <h4 class="filter-title">Тип товара</h4>
                    <div class="filter-options">
                        <a href="?<?= http_build_query(array_filter(['category' => $category_slug, 'brand' => $brand_id, 'model' => $model_id, 'in_stock' => $in_stock])) ?>" 
                           class="filter-option <?= !$type_id ? 'active' : '' ?>">
                            Все типы
                        </a>
                        <?php foreach ($types as $type): ?>
                            <a href="?<?= http_build_query(array_filter([
                                'category' => $category_slug,
                                'type' => $type['id'],
                                'brand' => $brand_id,
                                'model' => $model_id,
                                'in_stock' => $in_stock
                            ])) ?>" 
                               class="filter-option <?= $type_id == $type['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($type['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="filter-section">
                    <h4 class="filter-title">Марка авто</h4>
                    <div class="filter-options">
                        <a href="?<?= http_build_query(array_filter([
                            'category' => $category_slug,
                            'type' => $type_id,
                            'model' => $model_id,
                            'in_stock' => $in_stock
                        ])) ?>" 
                           class="filter-option <?= !$brand_id ? 'active' : '' ?>">
                            Все марки
                        </a>
                        <?php foreach ($brands as $brand): ?>
                            <a href="?<?= http_build_query(array_filter([
                                'category' => $category_slug,
                                'type' => $type_id,
                                'brand' => $brand['id'],
                                'model' => $model_id,
                                'in_stock' => $in_stock
                            ])) ?>" 
                               class="filter-option <?= $brand_id == $brand['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($brand['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($models): ?>
                <div class="filter-section">
                    <h4 class="filter-title">Модель авто</h4>
                    <div class="filter-options">
                        <a href="?<?= http_build_query(array_filter([
                            'category' => $category_slug,
                            'type' => $type_id,
                            'brand' => $brand_id,
                            'in_stock' => $in_stock
                        ])) ?>" 
                           class="filter-option <?= !$model_id ? 'active' : '' ?>">
                            Все модели
                        </a>
                        <?php foreach ($models as $model): ?>
                            <a href="?<?= http_build_query(array_filter([
                                'category' => $category_slug,
                                'type' => $type_id,
                                'brand' => $brand_id,
                                'model' => $model['id'],
                                'in_stock' => $in_stock
                            ])) ?>" 
                               class="filter-option <?= $model_id == $model['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($model['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="filter-section">
                    <h4 class="filter-title">Наличие</h4>
                    <div class="filter-options">
                        <a href="?<?= http_build_query(array_filter([
                            'category' => $category_slug,
                            'type' => $type_id,
                            'brand' => $brand_id,
                            'model' => $model_id
                        ])) ?>" 
                           class="filter-option <?= !$in_stock ? 'active' : '' ?>">
                            Все товары
                        </a>
                        <a href="?<?= http_build_query(array_filter([
                            'category' => $category_slug,
                            'type' => $type_id,
                            'brand' => $brand_id,
                            'model' => $model_id,
                            'in_stock' => 1
                        ])) ?>" 
                           class="filter-option in-stock-filter <?= $in_stock == '1' ? 'active' : '' ?>">
                            <i class="fas fa-check-circle"></i> В наличии
                        </a>
                    </div>
                </div>
                
                <?php if ($category_slug || $type_id || $brand_id || $model_id || $in_stock): ?>
                <div class="active-filters">
                    <h4 class="filter-title">Активные фильтры</h4>
                    <div class="active-filters-list">
                        <?php if ($current_category): ?>
                            <span class="active-filter">
                                <?= htmlspecialchars($current_category['name']) ?>
                                <a href="?<?= http_build_query(array_filter([
                                    'type' => $type_id,
                                    'brand' => $brand_id,
                                    'model' => $model_id,
                                    'in_stock' => $in_stock
                                ])) ?>" class="remove-filter">×</a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($type_id): 
                            $type_name = '';
                            foreach ($types as $t) {
                                if ($t['id'] == $type_id) $type_name = $t['name'];
                            }
                        ?>
                            <span class="active-filter">
                                <?= htmlspecialchars($type_name) ?>
                                <a href="?<?= http_build_query(array_filter([
                                    'category' => $category_slug,
                                    'brand' => $brand_id,
                                    'model' => $model_id,
                                    'in_stock' => $in_stock
                                ])) ?>" class="remove-filter">×</a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($brand_id): 
                            $brand_name = '';
                            foreach ($brands as $b) {
                                if ($b['id'] == $brand_id) $brand_name = $b['name'];
                            }
                        ?>
                            <span class="active-filter">
                                <?= htmlspecialchars($brand_name) ?>
                                <a href="?<?= http_build_query(array_filter([
                                    'category' => $category_slug,
                                    'type' => $type_id,
                                    'model' => $model_id,
                                    'in_stock' => $in_stock
                                ])) ?>" class="remove-filter">×</a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($model_id): 
                            $model_name = '';
                            foreach ($models as $m) {
                                if ($m['id'] == $model_id) $model_name = $m['name'];
                            }
                        ?>
                            <span class="active-filter">
                                <?= htmlspecialchars($model_name) ?>
                                <a href="?<?= http_build_query(array_filter([
                                    'category' => $category_slug,
                                    'type' => $type_id,
                                    'brand' => $brand_id,
                                    'in_stock' => $in_stock
                                ])) ?>" class="remove-filter">×</a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($in_stock): ?>
                            <span class="active-filter">
                                В наличии
                                <a href="?<?= http_build_query(array_filter([
                                    'category' => $category_slug,
                                    'type' => $type_id,
                                    'brand' => $brand_id,
                                    'model' => $model_id
                                ])) ?>" class="remove-filter">×</a>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </aside>
            
            <div class="products-section">
                <?php if ($products): ?>
                    
                    <div class="products-toolbar">
                        <div class="products-count">
                            Показано <?= count($products) ?> из <?= $total_products ?> товаров
                        </div>
                        <div class="products-sort">
                            <label for="sort">Сортировка:</label>
                            <select id="sort" class="sort-select" onchange="applySorting(this.value)">
                                <option value="default" <?= (!isset($_GET['sort']) || $_GET['sort'] == 'default') ? 'selected' : '' ?>>По умолчанию</option>
                                <option value="price_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : '' ?>>Сначала дешевле</option>
                                <option value="price_desc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : '' ?>>Сначала дороже</option>
                                <option value="name_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'name_asc') ? 'selected' : '' ?>>По названию (А-Я)</option>
                                <option value="name_desc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'name_desc') ? 'selected' : '' ?>>По названию (Я-А)</option>
                                <option value="stock" <?= (isset($_GET['sort']) && $_GET['sort'] == 'stock') ? 'selected' : '' ?>>По наличию</option>
                            </select>
                        </div>
                    </div>

                    <script>
                    function applySorting(sortValue) {
                        let url = new URL(window.location.href);
                        url.searchParams.set('sort', sortValue);
                        if (sortValue === 'default') {
                            url.searchParams.delete('sort');
                        }
                        url.searchParams.set('page', '1');
                        window.location.href = url.toString();
                    }
                    </script>
                    
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card" onclick="window.location='product.php?id=<?= $product['id'] ?>'" style="cursor:pointer">
                               <div class="product-badges">
                                    <?php if ($product['available'] > 0): ?>
                                        <?php if ($product['available'] < 5): ?>
                                            <span class="badge badge-warning">Мало</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Нет</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php else: ?>
                                        <div class="product-no-image">
                                            <i class="fas fa-car"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <div class="product-brand">
                                        <?= htmlspecialchars($product['brand_name'] ?? '') ?>
                                    </div>
                                    
                                    <h3 class="product-title">
                                        <a href="product.php?id=<?= $product['id'] ?>">
                                            <?= htmlspecialchars(mb_substr($product['name'], 0, 60)) ?>
                                            <?= mb_strlen($product['name']) > 60 ? '...' : '' ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="product-article">
                                        Арт: <?= htmlspecialchars($product['article']) ?>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?= number_format($product['price'], 0, '', ' ') ?> ₽
                                    </div>
                                    
                                    <div class="product-stock <?= $product['available'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                        <?php if ($product['available'] > 10): ?>
                                            <i class="fas fa-check-circle"></i> В наличии
                                        <?php elseif ($product['available'] > 0): ?>
                                            <i class="fas fa-check-circle"></i> Осталось <?= $product['available'] ?> шт.
                                        <?php elseif ($product['quantity'] > 0): ?>
                                            <i class="fas fa-clock"></i> Скоро
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Нет в наличии
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($product['available'] > 0): ?>
                                        <button class="add-to-cart-btn"
                                            onclick="event.stopPropagation()"
                                            data-id="<?= $product['id'] ?>"
                                            data-name="<?= htmlspecialchars($product['name']) ?>"
                                            data-price="<?= $product['price'] ?>"
                                            data-article="<?= htmlspecialchars($product['article']) ?>"
                                            data-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                                            data-available="<?= $product['available'] ?>">
                                            <i class="fas fa-shopping-cart"></i> В корзину
                                        </button>
                                    <?php elseif ($product['quantity'] > 0): ?>
                                        <button class="add-to-cart-btn preorder-btn"
                                            onclick="event.stopPropagation()"
                                            data-id="<?= $product['id'] ?>"
                                            data-name="<?= htmlspecialchars($product['name']) ?>"
                                            data-price="<?= $product['price'] ?>"
                                            data-article="<?= htmlspecialchars($product['article']) ?>"
                                            data-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                                            data-available="<?= $product['available'] ?>">
                                            <i class="fas fa-clock"></i> Предзаказ
                                        </button>
                                    <?php else: ?>
                                        <button class="add-to-cart-btn disabled" onclick="event.stopPropagation()" disabled>
                                            <i class="fas fa-times-circle"></i> Нет
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1 && !$search): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge(
                                    array_filter([
                                        'category' => $category_slug,
                                        'type' => $type_id,
                                        'brand' => $brand_id,
                                        'model' => $model_id,
                                        'in_stock' => $in_stock
                                    ]),
                                    ['page' => $page - 1]
                                )) ?>" class="pagination-prev">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1): ?>
                                <a href="?<?= http_build_query(array_merge(
                                    array_filter([
                                        'category' => $category_slug,
                                        'type' => $type_id,
                                        'brand' => $brand_id,
                                        'model' => $model_id,
                                        'in_stock' => $in_stock
                                    ]),
                                    ['page' => 1]
                                )) ?>" class="pagination-page">1</a>
                                <?php if ($start > 2): ?>
                                    <span class="pagination-dots">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?<?= http_build_query(array_merge(
                                    array_filter([
                                        'category' => $category_slug,
                                        'type' => $type_id,
                                        'brand' => $brand_id,
                                        'model' => $model_id,
                                        'in_stock' => $in_stock
                                    ]),
                                    ['page' => $i]
                                )) ?>" class="pagination-page <?= $i == $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span class="pagination-dots">...</span>
                                <?php endif; ?>
                                <a href="?<?= http_build_query(array_merge(
                                    array_filter([
                                        'category' => $category_slug,
                                        'type' => $type_id,
                                        'brand' => $brand_id,
                                        'model' => $model_id,
                                        'in_stock' => $in_stock
                                    ]),
                                    ['page' => $total_pages]
                                )) ?>" class="pagination-page"><?= $total_pages ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge(
                                    array_filter([
                                        'category' => $category_slug,
                                        'type' => $type_id,
                                        'brand' => $brand_id,
                                        'model' => $model_id,
                                        'in_stock' => $in_stock
                                    ]),
                                    ['page' => $page + 1]
                                )) ?>" class="pagination-next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-products">
                        <i class="fas fa-box-open"></i>
                        <h3>Товары не найдены</h3>
                        <p>Попробуйте изменить параметры фильтрации</p>
                        <a href="catalog.php" class="reset-filters-btn">
                            <i class="fas fa-undo-alt"></i> Сбросить фильтры
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
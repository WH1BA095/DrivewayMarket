<?php include 'includes/header.php'; ?>

<main class="main-content">

    <div class="hero-section">
        <div class="hero-inner">
            <div class="hero-bg-icons" aria-hidden="true">
                <i class="fas fa-cog"></i>
                <i class="fas fa-wrench"></i>
                <i class="fas fa-car"></i>
                <i class="fas fa-oil-can"></i>
            </div>
            <h1 class="hero-title">Автозапчасти для любого авто</h1>
            <p class="hero-subtitle">Большое количество позиций в наличии — оригиналы и аналоги с доставкой по всей России</p>
            <div class="hero-actions">
                <a href="catalog.php" class="hero-btn-primary">
                    <i class="fas fa-th-large"></i> Весь каталог
                </a>
                <a href="catalog.php?in_stock=1" class="hero-btn-secondary">
                    <i class="fas fa-box-open"></i> Товары в наличии
                </a>
            </div>
        </div>
    </div>

    <div class="app-section">
        <div class="app-section-inner">
            <div class="app-section-icon">
                <img src="img/smLogo1.png" class="app-section-logo app-section-logo-light" alt="Driveway">
                <img src="img/smLogo2.png" class="app-section-logo app-section-logo-dark"  alt="Driveway">
            </div>
            <div class="app-section-text">
                <strong>Скачайте наше мобильное приложение</strong>
                <span>Магазин запчастей всегда под рукой — заказы, каталог и уведомления прямо в смартфоне</span>
            </div>
            <div class="app-section-btns">
                <a href="#" class="app-store-btn">
                    <i class="fab fa-apple"></i>
                    <span><small>Загрузить в</small>App Store</span>
                </a>
                <a href="#" class="app-store-btn">
                    <i class="fab fa-google-play"></i>
                    <span><small>Доступно в</small>Google Play</span>
                </a>
            </div>
        </div>
    </div>

    <div class="about-section">
        <h2 class="about-title">О компании</h2>
        
        <div class="about-grid">
            <div class="about-content">
                <div class="about-text">
                    <p>
                        DrivewayMarket — огромный выбор оригинальных запчастей и аналогов. 
                        Сотрудничаем напрямую с производителями из 
                        Европы, Японии, Кореи и России.
                    </p>
                    <p>
                        Все товары сертифицированы, гарантия до 2 лет. Доставка по всей 
                        России в день заказа.
                    </p>
                </div>
                
                <div class="about-features">
                    <div class="feature-item">
                        <span class="feature-check">✓</span>
                        <span class="feature-text">Быстрая доставка</span>
                    </div>
                    <div class="feature-item">
                        <span class="feature-check">✓</span>
                        <span class="feature-text">Гарантия 2 года</span>
                    </div>
                    <div class="feature-item">
                        <span class="feature-check">✓</span>
                        <span class="feature-text">Возврат 14 дней</span>
                    </div>
                    <div class="feature-item">
                        <span class="feature-check">✓</span>
                        <span class="feature-text">Поддержка 24/7</span>
                    </div>
                </div>
            </div>
            
            <div class="about-stats">
                <div class="stat-item">
                    <div class="stat-title">Доставка</div>
                    <div class="stat-subtitle">по всей России</div>
                </div>
                <div class="stat-item">
                    <div class="stat-title">Большой выбор</div>
                    <div class="stat-subtitle">запчастей</div>
                </div>
                <div class="stat-item">
                    <div class="stat-title">Большой выбор</div>
                    <div class="stat-subtitle">брендов</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">150+</div>
                    <div class="stat-label">городов</div>
                </div>
            </div>
        </div>
    </div>

    <div class="advantages-grid">
        <div class="advantage-card">
            <div class="advantage-icon">
                <i class="fas fa-wrench"></i>
            </div>
            <h3 class="advantage-title">Только оригиналы</h3>
            <p class="advantage-desc">Проверенные поставщики</p>
        </div>
        
        <div class="advantage-card">
            <div class="advantage-icon">
                <i class="fas fa-ruble-sign"></i>
            </div>
            <h3 class="advantage-title">Низкие цены</h3>
            <p class="advantage-desc">Без посредников</p>
        </div>
        
        <div class="advantage-card">
            <div class="advantage-icon">
                <i class="fas fa-truck"></i>
            </div>
            <h3 class="advantage-title">Быстрая доставка</h3>
            <p class="advantage-desc">По всей России</p>
        </div>
        
        <div class="advantage-card">
            <div class="advantage-icon">
                <i class="fas fa-gift"></i>
            </div>
            <h3 class="advantage-title">Бонусы</h3>
            <p class="advantage-desc">Накопительная система</p>
        </div>
    </div>

    <div class="brands-section">
        <h2 class="brands-title">Популярные бренды</h2>
        <div class="brands-grid">
            <div class="brand-item">Bosch</div>
            <div class="brand-item">Mann</div>
            <div class="brand-item">NGK</div>
            <div class="brand-item">Contitech</div>
            <div class="brand-item">Lemforder</div>
            <div class="brand-item">TRW</div>
        </div>
    </div>

</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
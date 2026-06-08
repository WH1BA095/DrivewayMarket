# DrivewayMarket — Интернет-магазин автозапчастей

Полноценный интернет-магазин автозапчастей: веб-сайт на PHP + мобильное приложение на React Native (Expo). Единая база данных MariaDB, двунаправленная синхронизация корзины между сайтом и приложением.

---

## Стек технологий

| Слой | Технологии |
|------|-----------|
| Веб-сайт | PHP 8.2, HTML/CSS/JS, сессионная авторизация |
| Мобильное приложение | React Native 0.74, Expo SDK 51, React Navigation |
| API | REST JSON API (token-based, Bearer) |
| База данных | MariaDB 12.2 |
| Инфраструктура | Docker, Nginx, PHP-FPM |
| Тесты | PHPUnit 11, Jest 29 |

---

## Структура проекта

```
driveway-project/
├── backend/              # Веб-сайт и API
│   ├── api/              # REST API для мобильного приложения
│   │   ├── app.php       # Основной обработчик (авторизация, товары, заказы, СБП)
│   │   ├── auth.php      # Профиль, избранное, автомобили пользователя
│   │   ├── cart.php      # Синхронизация корзины (сессии)
│   │   ├── availability.php  # Актуальные остатки товаров
│   │   ├── reviews.php   # Отзывы и вопросы
│   │   ├── save_order.php    # Создание заказа (доставка, оплата, контакт)
│   │   └── get_orders.php   # История заказов
│   ├── admin/            # Панель администратора
│   ├── config/           # Подключение к БД, функции авторизации
│   ├── css/              # Стили сайта
│   ├── js/               # cart.js, theme.js
│   ├── includes/         # Шапка, подвал, хелперы каталога
│   ├── index.php         # Главная страница
│   ├── catalog.php       # Каталог товаров
│   ├── product.php       # Страница товара
│   ├── cart.php          # Корзина
│   ├── checkout.php      # Оформление заказа
│   └── profile.php       # Личный кабинет
├── app/                  # Мобильное приложение (React Native / Expo)
│   ├── app.json          # Конфигурация Expo (иконки, разрешения, ATS)
│   ├── assets/           # Иконки приложения
│   └── src/
│       ├── api/          # Обёртка над REST API
│       ├── components/   # ProductCard, CategoryCard, StarRating, Logo
│       ├── context/      # AuthContext, CartContext, ThemeContext
│       ├── navigation/   # Структура навигации (Bottom Tabs)
│       └── screens/      # Home, Catalog, Product, Cart, Profile, Orders и др.
├── migrate/              # SQL-миграции
│   ├── 001_init_schema.sql
│   └── 002_seed_data.sql
├── nginx/                # Конфигурация Nginx
├── php/                  # Конфигурация PHP-FPM
├── tests/                # Unit-тесты
│   ├── backend/          # PHPUnit (29 тестов)
│   └── app/              # Jest (26 тестов)
└── docker-compose.yaml
```

---

## Быстрый старт

### Требования

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Node.js](https://nodejs.org/) 18+
- Для сборки iOS: macOS + Xcode 15+
- Для сборки Android: Android Studio + Java 17

### 1. Запуск бэкенда

```bash
# Клонировать репозиторий
git clone <repo-url>
cd driveway-project

# Запустить все сервисы
docker compose up -d
```

Сайт доступен по адресу: **http://localhost:8899**

База данных поднимается автоматически, миграции применяются при первом старте из папки `migrate/`.

### 2. Запуск мобильного приложения (разработка)

```bash
cd app
npm install
npx expo start
```

Отсканируй QR-код в [Expo Go](https://expo.dev/client), или нажми `i` для iOS Simulator / `a` для Android Emulator.

> **Важно:** адрес API задан в `app/src/api/index.js`. При запуске на реальном устройстве он должен указывать на IP сервера или машины в локальной сети.

### 3. Нативная сборка для установки на устройство

Приложение собирается в нативный бинарник (без Expo Go) и устанавливается напрямую:

**iOS** (требует macOS + Xcode):
```bash
cd app
npx expo prebuild --platform ios --clean
npx expo run:ios --configuration Release --device
```
После установки: **Настройки → Основные → VPN и управление устройством → Доверять**.
Подпись действительна 7 дней (бесплатный Apple ID).

**Android**:
```bash
cd app
npx expo prebuild --platform android --clean
export JAVA_HOME=$(/usr/libexec/java_home -v 17)   # macOS
npx expo run:android --variant release --device
```

---

## Функциональность

### Веб-сайт
- Каталог товаров с фильтрацией по категориям, поиском и пагинацией
- Страница товара с галереей, описанием, отзывами и рейтингом
- Корзина с валидацией остатков в реальном времени
- Оформление заказа: курьер / самовывоз / Почта России
- Оплата: онлайн картой (алгоритм Луна), СБП (QR-код), при получении
- Личный кабинет: история заказов с деталями доставки и оплаты, профиль, список автомобилей
- Тёмная / светлая тема
- Адаптивный дизайн
- Панель администратора: управление товарами, заказами, отзывами, экспорт

### Мобильное приложение (DrivewayMarket)
- Главная страница: поиск, категории, популярные товары
- Каталог с фильтрами по категориям
- Корзина с синхронизацией с сайтом
- Оформление заказа с оплатой по СБП (QR-код в приложении)
- Авторизация, регистрация, личный кабинет
- История заказов
- Избранное, список автомобилей пользователя
- Светлая / тёмная тема
- Поддержка iOS 15+ и Android (API 23+)

### Синхронизация корзины
Корзина полностью синхронизируется между сайтом и приложением в обе стороны:
- Изменения на сайте → применяются в приложении при возврате в foreground
- Изменения в приложении → применяются на сайте при следующей загрузке страницы
- Очистка корзины на любой платформе → очищает на обеих

---

## API

Базовый URL: `http://<host>:8899/api/app.php`

Авторизация: `Authorization: Bearer <token>`

| Метод | Action | Описание |
|-------|--------|----------|
| POST | `register` | Регистрация |
| POST | `login` | Вход, возвращает токен |
| GET | `products` | Список товаров (`page`, `per_page`, `category_id`, `search`) |
| GET | `product` | Один товар (`id`) |
| GET | `categories` | Список категорий |
| GET | `get_cart` | Корзина пользователя |
| POST | `sync_cart` | Синхронизация корзины |
| POST | `clear_cart` | Очистить корзину |
| GET | `my_orders` | История заказов |
| POST | `place_order` | Создать заказ (delivery, payment, contact) |
| POST | `sbp_pay` | Инициировать оплату по СБП |
| GET | `sbp_status` | Статус оплаты СБП |
| GET | `favorites` | Избранное |
| POST | `toggle_favorite` | Добавить / убрать из избранного |

---

## Тесты

### PHP (PHPUnit) — 29 тестов

```bash
cd tests/backend
./vendor/bin/phpunit
```

| Файл | Что тестирует |
|------|--------------|
| `ValidationTest.php` | Валидация email и телефона |
| `CartCalculationTest.php` | Расчёт суммы, количества, ограничение qty |
| `ApiResponseTest.php` | Формат JSON-ответов API, статусы заказов, санитизация |

### JavaScript (Jest) — 26 тестов

```bash
cd tests/app
npm test
```

| Файл | Что тестирует |
|------|--------------|
| `luhn.test.js` | Алгоритм Луна (валидация карты) |
| `cartLogic.test.js` | Чистая логика корзины (сумма, количество, capQty) |
| `validation.test.js` | Валидация email и телефона на клиенте |

---

## Переменные окружения

Создай файл `.env` в корне проекта:

```env
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=driveway_db
MYSQL_USER=driveway_user
MYSQL_PASSWORD=driveway_pass
DB_HOST=mariadb
DB_NAME=driveway_db
DB_USER=driveway_user
DB_PASS=driveway_pass
```

---

## Docker-образы

### Nginx (frontend)
Образ: `whnba095/driveway-project-frontend`  
Root-less запуск, убраны лишние capabilities. Вес: **61.4 MB**

### PHP-FPM (backend)
Образ: `whnba095/driveway-project-backend`  
Root-less запуск, убраны лишние capabilities. Вес: **164 MB**

---

## CI/CD

GitHub Actions пайплайн автоматически:
1. Запускает PHPUnit и Jest тесты
2. Собирает Docker-образы
3. Публикует в Docker Hub

---

## Лицензия

Учебный проект. Все права защищены.

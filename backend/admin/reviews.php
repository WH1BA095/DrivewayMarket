<?php
require_once '../config/auth.php';
if (!isLoggedIn() || empty($_SESSION['user_is_admin'])) {
    header('Location: ../index.php'); exit;
}
$adminName  = trim($_SESSION['user_firstname'] . ' ' . $_SESSION['user_lastname']) ?: 'Администратор';
$adminInit  = strtoupper(substr($_SESSION['user_firstname'] ?: 'A', 0, 1));
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Отзывы и вопросы — Driveway Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/admin.css">
<style>
*, *::before, *::after { box-sizing: border-box; }

.rv-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.rv-tab {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 20px;
    border: 2px solid var(--border); border-radius: var(--r-sm);
    background: var(--card-bg); cursor: pointer;
    font-size: 13px; font-weight: 600; color: var(--text-2);
    transition: border-color .18s, background .18s, color .18s;
    font-family: inherit; line-height: 1.4;
}
.rv-tab.active { border-color: var(--primary); background: var(--primary); color: #fff; }
.rv-tab:not(.active):hover { border-color: var(--primary); color: var(--primary); }
.rv-tab .badge {
    background: rgba(255,255,255,.25); border-radius: 20px;
    padding: 1px 8px; font-size: 11px; font-weight: 700;
}
.rv-tab:not(.active) .badge { background: rgba(227,22,11,.1); color: var(--primary); }

.rv-filters { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
.rv-filter-btn {
    padding: 7px 14px;
    border: 1.5px solid var(--border); border-radius: var(--r-sm);
    background: var(--card-bg); cursor: pointer;
    font-size: 12px; font-weight: 600; color: var(--text-2);
    transition: .15s; font-family: inherit; line-height: 1.4;
}
.rv-filter-btn.active { border-color: var(--primary); color: var(--primary); background: rgba(227,22,11,.06); }
.rv-filter-btn:not(.active):hover { border-color: var(--primary); color: var(--primary); }
.rv-search {
    flex: 1; min-width: 220px;
    padding: 8px 12px;
    border: 1.5px solid var(--border); border-radius: var(--r-sm);
    font-size: 13px; background: var(--card-bg); color: var(--text);
    font-family: inherit; outline: none; transition: border-color .15s;
}
.rv-search:focus { border-color: var(--primary); }

.rv-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 16px 18px;
    margin-bottom: 10px;
    transition: box-shadow .18s;
}
.rv-card:hover { box-shadow: var(--shadow-md); }

.rv-card-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
.rv-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(227,22,11,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 15px; flex-shrink: 0;
}
.rv-meta { flex: 1; min-width: 0; }
.rv-user { font-weight: 600; font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rv-sub  { font-size: 11px; color: var(--text-2); margin-top: 3px; }
.rv-product-link { color: var(--info); text-decoration: none; font-size: 12px; }
.rv-product-link:hover { text-decoration: underline; }

.stars-display { color: var(--warning); font-size: 14px; display: inline-flex; gap: 2px; }

.rv-status { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }
.rv-status-approved { background: #d1fae5; color: #065f46; }
.rv-status-rejected { background: #fee2e2; color: #991b1b; }
.rv-status-open     { background: #fef3c7; color: #92400e; }
.rv-status-answered { background: #d1fae5; color: #065f46; }
.rv-status-new      { background: #dbeafe; color: #1e40af; }
.rv-status-read     { background: var(--pg-bg); color: var(--text-2); }
.rv-status-replied  { background: #d1fae5; color: #065f46; }

.rv-title { font-weight: 600; font-size: 14px; margin-bottom: 5px; color: var(--text); }
.rv-body  { font-size: 13px; color: var(--text); line-height: 1.65; margin-bottom: 10px; }

.rv-reply-block {
    background: var(--pg-bg);
    border-left: 3px solid var(--primary);
    border-radius: 0 var(--r-sm) var(--r-sm) 0;
    padding: 10px 14px; margin-bottom: 10px;
    font-size: 13px; color: var(--text); line-height: 1.55;
}
.rv-reply-label {
    font-size: 11px; font-weight: 700; color: var(--primary);
    margin-bottom: 5px; display: flex; align-items: center; gap: 5px;
}

.rv-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.rv-btn {
    padding: 6px 12px; border-radius: 7px; border: 1.5px solid;
    cursor: pointer; font-size: 12px; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
    transition: .15s; background: transparent; font-family: inherit; line-height: 1.4;
}
.rv-btn-reply  { border-color: var(--info);    color: var(--info);    }
.rv-btn-reply:hover  { background: var(--info);    color: #fff; }
.rv-btn-reject { border-color: var(--danger);  color: var(--danger);  }
.rv-btn-reject:hover { background: var(--danger);  color: #fff; }
.rv-btn-restore{ border-color: var(--success); color: var(--success); }
.rv-btn-restore:hover{ background: var(--success); color: #fff; }
.rv-btn-del    { border-color: var(--border);  color: var(--text-2);  }
.rv-btn-del:hover    { border-color: var(--danger); color: var(--danger); }

.rv-reply-form { margin-top: 12px; display: none; }
.rv-reply-form textarea {
    width: 100%; min-height: 80px;
    padding: 10px 12px;
    border: 1.5px solid var(--border); border-radius: var(--r-sm);
    font-size: 13px; resize: vertical;
    background: var(--card-bg); color: var(--text);
    font-family: inherit; transition: border-color .15s; outline: none;
    display: block;
}
.rv-reply-form textarea:focus { border-color: var(--primary); }
.rv-send-btn {
    margin-top: 8px; padding: 8px 20px;
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--r-sm);
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: background .15s;
    display: inline-flex; align-items: center; gap: 6px;
}
.rv-send-btn:hover { background: var(--primary-h); }

.rv-empty, .rv-loading {
    text-align: center; padding: 40px 20px;
    color: var(--text-2); font-size: 14px;
}
.rv-empty i { font-size: 36px; opacity: .25; display: block; margin-bottom: 12px; }
.rv-loading i { font-size: 22px; display: block; margin-bottom: 8px; color: var(--text-2); }

.pager { display: flex; gap: 6px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
.pager button {
    padding: 6px 14px; border-radius: 7px;
    border: 1.5px solid var(--border);
    background: var(--card-bg); cursor: pointer;
    font-size: 13px; color: var(--text-2); transition: .15s; font-family: inherit;
}
.pager button.active   { border-color: var(--primary); background: var(--primary); color: #fff; }
.pager button:hover:not(.active) { border-color: var(--primary); color: var(--primary); }

.sup-subject { font-weight: 600; font-size: 14px; margin-bottom: 6px; color: var(--text); }
.sup-message {
    font-size: 13px; line-height: 1.65; color: var(--text);
    background: var(--pg-bg); border-radius: var(--r-sm);
    padding: 12px 14px; margin-bottom: 10px;
}
</style>
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
        <a href="products.php" class="sb-item"><i class="fas fa-boxes"></i> Товары</a>
        <div class="sb-section">Контент</div>
        <a href="reviews.php" class="sb-item active"><i class="fas fa-star"></i> Отзывы и вопросы</a>
        <div class="sb-section">Экспорт</div>
        <a href="export.php?type=products" class="sb-item"><i class="fas fa-file-excel"></i> Экспорт товаров</a>
        <a href="export.php?type=users"    class="sb-item"><i class="fas fa-file-csv"></i> Экспорт пользователей</a>
        <a href="export.php?type=stats"    class="sb-item"><i class="fas fa-chart-bar"></i> Экспорт статистики</a>
        <div class="sb-section">Прочее</div>
<a href="../index.php" class="sb-item"><i class="fas fa-external-link-alt"></i> Перейти на сайт</a>
    </nav>
    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= h($adminInit) ?></div>
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
        <div class="tb-title"><i class="fas fa-star" style="color:var(--primary);margin-right:8px;font-size:16px;"></i>Отзывы и вопросы</div>
        <div class="tb-sub"><?= date('d F Y') ?></div>
    </div>
    <div class="tb-right">
        <a href="products.php?modal=add" class="btn btn-primary"><i class="fas fa-plus"></i> Добавить товар</a>
    </div>
</header>

<div class="admin-content">
    <div class="rv-tabs">
        <button class="rv-tab active" onclick="switchMain('reviews')" id="mtab-reviews">
            <i class="fas fa-star"></i> Отзывы <span class="badge" id="badge-reviews">...</span>
        </button>
        <button class="rv-tab" onclick="switchMain('questions')" id="mtab-questions">
            <i class="fas fa-question-circle"></i> Вопросы <span class="badge" id="badge-questions">...</span>
        </button>
        <button class="rv-tab" onclick="switchMain('support')" id="mtab-support">
            <i class="fas fa-envelope"></i> Обращения <span class="badge" id="badge-support">...</span>
        </button>
    </div>

    <div id="section-reviews">
        <div class="rv-filters">
            <button class="rv-filter-btn active" onclick="setFilter('reviews','all',this)">Все</button>
            <button class="rv-filter-btn" onclick="setFilter('reviews','approved',this)">Опубликованы</button>
            <button class="rv-filter-btn" onclick="setFilter('reviews','rejected',this)">Скрытые</button>
            <input type="text" class="rv-search" id="search-reviews" placeholder="Поиск по товару или автору..." oninput="debounceSearch('reviews')">
        </div>
        <div id="list-reviews"><div class="rv-loading"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div></div>
        <div class="pager" id="pager-reviews"></div>
    </div>

    <div id="section-questions" style="display:none">
        <div class="rv-filters">
            <button class="rv-filter-btn active" onclick="setFilter('questions','all',this)">Все</button>
            <button class="rv-filter-btn" onclick="setFilter('questions','open',this)">Без ответа</button>
            <button class="rv-filter-btn" onclick="setFilter('questions','answered',this)">Отвечены</button>
            <input type="text" class="rv-search" id="search-questions" placeholder="Поиск по товару или вопросу..." oninput="debounceSearch('questions')">
        </div>
        <div id="list-questions"><div class="rv-loading"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div></div>
    </div>

    <div id="section-support" style="display:none">
        <div class="rv-filters">
            <button class="rv-filter-btn active" onclick="setFilter('support','all',this)">Все</button>
            <button class="rv-filter-btn" onclick="setFilter('support','new',this)">Новые</button>
            <button class="rv-filter-btn" onclick="setFilter('support','read',this)">Прочитанные</button>
            <button class="rv-filter-btn" onclick="setFilter('support','replied',this)">Отвечены</button>
        </div>
        <div id="list-support"><div class="rv-loading"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div></div>
    </div>
</div>
</div>
</div>

<script>
const API = 'api.php';
let state = {
    reviews:   { filter: 'all', search: '', page: 1 },
    questions: { filter: 'all', search: '', page: 1 },
    support:   { filter: 'all', search: '', page: 1 }
};
let debounceT = {};
let currentMain = 'reviews';

function stars(n) {
    let s = '';
    for (let i = 1; i <= 5; i++) s += `<i class="${i <= n ? 'fas' : 'far'} fa-star"></i>`;
    return `<span class="stars-display">${s}</span>`;
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmtDate(s) {
    if (!s) return '';
    return new Date(s).toLocaleString('ru-RU', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}
function avatar(name) {
    const c = (name || '?')[0].toUpperCase();
    return `<div class="rv-avatar">${c}</div>`;
}

function switchMain(tab) {
    currentMain = tab;
    ['reviews','questions','support'].forEach(t => {
        document.getElementById('section-' + t).style.display = t === tab ? 'block' : 'none';
        document.getElementById('mtab-' + t).classList.toggle('active', t === tab);
    });
    load(tab);
}

function setFilter(tab, val, btn) {
    state[tab].filter = val;
    state[tab].page   = 1;
    btn.closest('.rv-filters').querySelectorAll('.rv-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    load(tab);
}

function debounceSearch(tab) {
    clearTimeout(debounceT[tab]);
    debounceT[tab] = setTimeout(() => {
        state[tab].search = document.getElementById('search-' + tab).value;
        state[tab].page   = 1;
        load(tab);
    }, 400);
}

async function load(tab) {
    const s   = state[tab];
    let url   = API + '?';
    if (tab === 'reviews')   url += `action=get_all_reviews&status=${s.filter}&search=${encodeURIComponent(s.search)}&page=${s.page}`;
    if (tab === 'questions') url += `action=get_all_questions&status=${s.filter}&search=${encodeURIComponent(s.search)}&page=${s.page}`;
    if (tab === 'support')   url += `action=get_support&status=${s.filter}&page=${s.page}`;

    const res = await fetch(url).then(r => r.json()).catch(() => null);
    if (!res || !res.success) return;

    if (tab === 'reviews')   renderReviews(res.reviews || [], res.total || 0, res.pages || 1);
    if (tab === 'questions') renderQuestions(res.questions || []);
    if (tab === 'support')   renderSupport(res.messages || []);
}

async function loadBadges() {
    const [rv, qu, su] = await Promise.all([
        fetch(API + '?action=get_all_reviews&status=all&page=1').then(r => r.json()),
        fetch(API + '?action=get_all_questions&status=open&page=1').then(r => r.json()),
        fetch(API + '?action=get_support&status=new&page=1').then(r => r.json()),
    ]);
    if (rv.success) document.getElementById('badge-reviews').textContent   = rv.total || 0;
    if (qu.success) document.getElementById('badge-questions').textContent = (qu.questions||[]).length > 0 ? (qu.questions||[]).length + '+' : '0';
    if (su.success) document.getElementById('badge-support').textContent   = (su.messages||[]).length > 0 ? (su.messages||[]).length + '!' : '0';
}

function renderReviews(reviews, total, pages) {
    const el = document.getElementById('list-reviews');
    if (!reviews.length) { el.innerHTML = '<div class="rv-empty"><i class="far fa-comment-dots"></i>Отзывов нет</div>'; return; }
    el.innerHTML = reviews.map(r => `
        <div class="rv-card" id="rv-${r.id}">
            <div class="rv-card-head">
                ${avatar(r.display_name)}
                <div class="rv-meta">
                    <div class="rv-user">${esc(r.display_name)} <span style="font-weight:400;color:#94a3b8;font-size:11px">${esc(r.uemail||'')}</span></div>
                    <div class="rv-sub">
                        ${fmtDate(r.created_at)} ·
                        <a href="../product.php?id=${r.product_id}" target="_blank" class="rv-product-link">${esc(r.product_name)}</a>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
                    ${stars(r.rating)}
                    <span class="rv-status rv-status-${r.status}">${r.status === 'approved' ? 'Опубликован' : 'Скрыт'}</span>
                </div>
            </div>
            ${r.title ? `<div class="rv-title">${esc(r.title)}</div>` : ''}
            <div class="rv-body">${esc(r.body)}</div>
            ${r.admin_reply ? `
                <div class="rv-reply-block">
                    <div class="rv-reply-label"><i class="fas fa-reply"></i> Ответ администратора <span style="color:#94a3b8;font-weight:400">${fmtDate(r.admin_reply_at)}</span></div>
                    ${esc(r.admin_reply)}
                </div>` : ''}
            <div class="rv-actions">
                <button class="rv-btn rv-btn-reply" onclick="toggleReplyForm('rv',${r.id})"><i class="fas fa-reply"></i> Ответить</button>
                ${r.status === 'approved'
                    ? `<button class="rv-btn rv-btn-reject" onclick="modReview(${r.id},'reject_review')"><i class="fas fa-eye-slash"></i> Скрыть</button>`
                    : `<button class="rv-btn rv-btn-restore" onclick="modReview(${r.id},'restore_review')"><i class="fas fa-eye"></i> Показать</button>`}
                <button class="rv-btn rv-btn-del" onclick="delReview(${r.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="rv-reply-form" id="rform-rv-${r.id}">
                <textarea placeholder="Напишите ответ пользователю...">${esc(r.admin_reply||'')}</textarea>
                <button class="rv-send-btn" onclick="sendReply('rv',${r.id})"><i class="fas fa-paper-plane"></i> Отправить</button>
            </div>
        </div>`).join('');

    const pg = document.getElementById('pager-reviews');
    if (pages <= 1) { pg.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= pages; i++) {
        html += `<button class="${i === state.reviews.page ? 'active' : ''}" onclick="goPage('reviews',${i})">${i}</button>`;
    }
    pg.innerHTML = html;
}

function renderQuestions(questions) {
    const el = document.getElementById('list-questions');
    if (!questions.length) { el.innerHTML = '<div class="rv-empty"><i class="far fa-question-circle"></i>Вопросов нет</div>'; return; }
    el.innerHTML = questions.map(q => `
        <div class="rv-card" id="qv-${q.id}">
            <div class="rv-card-head">
                ${avatar(q.display_name)}
                <div class="rv-meta">
                    <div class="rv-user">${esc(q.display_name)}</div>
                    <div class="rv-sub">
                        ${fmtDate(q.created_at)} ·
                        <a href="../product.php?id=${q.product_id}" target="_blank" class="rv-product-link">${esc(q.product_name)}</a>
                    </div>
                </div>
                <span class="rv-status rv-status-${q.status}">${q.status === 'answered' ? 'Отвечен' : 'Без ответа'}</span>
            </div>
            <div class="rv-body"><i class="fas fa-question-circle" style="color:#f59e0b;margin-right:6px"></i>${esc(q.question)}</div>
            ${q.answer ? `
                <div class="rv-reply-block">
                    <div class="rv-reply-label"><i class="fas fa-reply"></i> Ответ администратора <span style="color:#94a3b8;font-weight:400">${fmtDate(q.answered_at)}</span></div>
                    ${esc(q.answer)}
                </div>` : ''}
            <div class="rv-actions">
                <button class="rv-btn rv-btn-reply" onclick="toggleReplyForm('qv',${q.id})"><i class="fas fa-reply"></i> Ответить</button>
                <button class="rv-btn rv-btn-del" onclick="delQuestion(${q.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="rv-reply-form" id="rform-qv-${q.id}">
                <textarea placeholder="Напишите ответ на вопрос...">${esc(q.answer||'')}</textarea>
                <button class="rv-send-btn" onclick="sendAnswer(${q.id})"><i class="fas fa-paper-plane"></i> Отправить</button>
            </div>
        </div>`).join('');
}

function renderSupport(msgs) {
    const el = document.getElementById('list-support');
    if (!msgs.length) { el.innerHTML = '<div class="rv-empty"><i class="far fa-envelope"></i>Обращений нет</div>'; return; }
    // при просмотре помечаем новые как прочитанные
    msgs.filter(m => m.status === 'new').forEach(m => markRead(m.id));
    el.innerHTML = msgs.map(m => `
        <div class="rv-card" id="sm-${m.id}">
            <div class="rv-card-head">
                ${avatar(m.name)}
                <div class="rv-meta">
                    <div class="rv-user">${esc(m.name)} <span style="font-weight:400;color:#94a3b8;font-size:11px">${esc(m.email)}</span></div>
                    <div class="rv-sub">${fmtDate(m.created_at)}</div>
                </div>
                <span class="rv-status rv-status-${m.status}">${{new:'Новое',read:'Прочитано',replied:'Отвечено'}[m.status]||m.status}</span>
            </div>
            ${m.subject ? `<div class="sup-subject">${esc(m.subject)}</div>` : ''}
            <div class="sup-message">${esc(m.message)}</div>
            ${m.reply ? `
                <div class="rv-reply-block">
                    <div class="rv-reply-label"><i class="fas fa-reply"></i> Ваш ответ <span style="color:#94a3b8;font-weight:400">${fmtDate(m.replied_at)}</span></div>
                    ${esc(m.reply)}
                </div>` : ''}
            <div class="rv-actions">
                <button class="rv-btn rv-btn-reply" onclick="toggleReplyForm('sm',${m.id}); markRead(${m.id})"><i class="fas fa-reply"></i> Ответить</button>
            </div>
            <div class="rv-reply-form" id="rform-sm-${m.id}">
                <textarea placeholder="Напишите ответ пользователю...">${esc(m.reply||'')}</textarea>
                <button class="rv-send-btn" onclick="sendSupportReply(${m.id})"><i class="fas fa-paper-plane"></i> Отправить</button>
            </div>
        </div>`).join('');
}

function toggleReplyForm(prefix, id) {
    const f = document.getElementById(`rform-${prefix}-${id}`);
    f.style.display = f.style.display === 'none' || !f.style.display ? 'block' : 'none';
}

async function sendReply(prefix, id) {
    const ta    = document.querySelector(`#rform-${prefix}-${id} textarea`).value.trim();
    if (!ta) return;
    const fd = new FormData();
    fd.append('action', 'reply_review');
    fd.append('review_id', id);
    fd.append('reply', ta);
    const res = await fetch(API, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) load('reviews');
}

async function sendAnswer(id) {
    const ta = document.querySelector(`#rform-qv-${id} textarea`).value.trim();
    if (!ta) return;
    const fd = new FormData();
    fd.append('action', 'answer_question');
    fd.append('question_id', id);
    fd.append('answer', ta);
    const res = await fetch(API, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) load('questions');
}

async function sendSupportReply(id) {
    const ta = document.querySelector(`#rform-sm-${id} textarea`).value.trim();
    if (!ta) return;
    const fd = new FormData();
    fd.append('action', 'reply_support');
    fd.append('msg_id', id);
    fd.append('reply', ta);
    const res = await fetch(API, { method:'POST', body:fd }).then(r => r.json());
    if (res.success) load('support');
}

async function modReview(id, action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('review_id', id);
    await fetch(API, { method:'POST', body:fd });
    load('reviews');
}

async function delReview(id) {
    if (!confirm('Удалить отзыв?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_review');
    fd.append('review_id', id);
    await fetch(API, { method:'POST', body:fd });
    document.getElementById('rv-' + id)?.remove();
}

async function delQuestion(id) {
    if (!confirm('Удалить вопрос?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_question');
    fd.append('question_id', id);
    await fetch(API, { method:'POST', body:fd });
    document.getElementById('qv-' + id)?.remove();
}

async function markRead(id) {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('msg_id', id);
    await fetch(API, { method:'POST', body:fd });
}

function goPage(tab, page) {
    state[tab].page = page;
    load(tab);
}

document.addEventListener('DOMContentLoaded', () => {
    loadBadges();
    load('reviews');
});
</script>
</body>
</html>

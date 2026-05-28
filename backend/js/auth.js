const AuthModal = (() => {
    const API = 'api/auth.php';

    function open(tab = 'login') {
        const overlay = document.getElementById('authModal');
        if (!overlay) return;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        switchTab(tab);
        clearErrors();
    }

    function close() {
        const overlay = document.getElementById('authModal');
        if (!overlay) return;
        overlay.style.display = 'none';
        document.body.style.overflow = '';
        clearErrors();
    }

    function switchTab(tab) {
        document.querySelectorAll('.auth-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.auth-tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'authTab' + cap(tab));
        });
        clearErrors();
    }

    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function clearErrors() {
        document.querySelectorAll('.auth-error').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
        document.querySelectorAll('#authModal .profile-input, #authModal input').forEach(inp => {
            inp.classList.remove('input-error', 'input-ok');
        });
    }

    function showError(id, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'flex';
    }

    function fieldError(inputId, msg) {
        const inp = document.getElementById(inputId);
        if (inp) inp.classList.add('input-error');
        return msg;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
    }

    async function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch(API, { method: 'POST', body: fd });
        return res.json();
    }

    async function login() {
        clearErrors();
        const email    = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value;

        let errMsg = null;
        if (!email)                    errMsg = fieldError('login-email',    'Введите email');
        else if (!isValidEmail(email)) errMsg = fieldError('login-email',    'Некорректный email');
        else if (!password)            errMsg = fieldError('login-password', 'Введите пароль');

        if (errMsg) { showError('login-error', errMsg); return; }

        setLoading('login-submit', true);
        try {
            const res = await post({ action: 'login', email, password });
            if (res.success) {
                onSuccess();
            } else {
                showError('login-error', res.message);
                document.getElementById('login-email').classList.add('input-error');
                document.getElementById('login-password').classList.add('input-error');
            }
        } catch(e) {
            showError('login-error', 'Ошибка сети, попробуйте ещё раз');
        }
        setLoading('login-submit', false);
    }

    async function register() {
        clearErrors();
        const firstname = document.getElementById('reg-firstname').value.trim();
        const lastname  = document.getElementById('reg-lastname').value.trim();
        const email     = document.getElementById('reg-email').value.trim();
        const phone     = document.getElementById('reg-phone').value.trim();
        const password  = document.getElementById('reg-password').value;
        const password2 = document.getElementById('reg-password2').value;

        let errMsg = null;
        if (!firstname || firstname.length < 2) errMsg = fieldError('reg-firstname', 'Введите имя (минимум 2 символа)');
        else if (!email)                         errMsg = fieldError('reg-email',     'Введите email');
        else if (!isValidEmail(email))           errMsg = fieldError('reg-email',     'Некорректный email');
        else if (!password)                      errMsg = fieldError('reg-password',  'Введите пароль');
        else if (password.length < 6)            errMsg = fieldError('reg-password',  'Пароль минимум 6 символов');
        else if (password !== password2)         errMsg = fieldError('reg-password2', 'Пароли не совпадают');

        if (errMsg) { showError('reg-error', errMsg); return; }

        setLoading('reg-submit', true);
        try {
            const res = await post({ action: 'register', firstname, lastname, email, phone, password, password2 });
            if (res.success) {
                onSuccess();
            } else {
                showError('reg-error', res.message);
            }
        } catch(e) {
            showError('reg-error', 'Ошибка сети, попробуйте ещё раз');
        }
        setLoading('reg-submit', false);
    }

    function onSuccess() {
        // если есть redirect-параметр — идём туда, иначе перезагружаем
        const params = new URLSearchParams(window.location.search);
        const redirect = params.get('redirect');
        if (redirect) {
            window.location.href = decodeURIComponent(redirect);
        } else {
            window.location.reload();
        }
    }

    async function logout() {
        await fetch(API + '?action=logout');
        localStorage.removeItem('driveway_cart');
        window.location.href = 'index.php';
    }

    function setLoading(id, loading) {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            btn._orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Подождите...';
        } else {
            btn.innerHTML = btn._orig || btn.innerHTML;
        }
    }

    function init() {
        // закрытие по клику на оверлей
        const overlay = document.getElementById('authModal');
        if (overlay) {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) close();
            });
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') close();
        });

        document.querySelectorAll('#authTabLogin input').forEach(inp => {
            inp.addEventListener('keydown', e => { if (e.key === 'Enter') login(); });
            inp.addEventListener('input', () => {
                inp.classList.remove('input-error');
                const err = document.getElementById('login-error');
                if (err) { err.style.display = 'none'; err.textContent = ''; }
            });
        });
        document.querySelectorAll('#authTabRegister input').forEach(inp => {
            inp.addEventListener('keydown', e => { if (e.key === 'Enter') register(); });
            inp.addEventListener('input', () => {
                inp.classList.remove('input-error');
                const err = document.getElementById('reg-error');
                if (err) { err.style.display = 'none'; err.textContent = ''; }
            });
        });

        const dropBtn = document.getElementById('userDropdownBtn');
        const drop    = document.getElementById('userDropdown');
        if (dropBtn && drop) {
            dropBtn.addEventListener('click', e => {
                e.stopPropagation();
                drop.classList.toggle('open');
            });
            document.addEventListener('click', () => drop.classList.remove('open'));
        }

        const params = new URLSearchParams(window.location.search);
        const authParam = params.get('auth');
        if (authParam === 'login' || authParam === 'register') {
            open(authParam);
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    return { open, close, switchTab, login, register, logout };
})();

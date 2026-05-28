const Cart = {
    get() {
        try {
            return JSON.parse(localStorage.getItem('driveway_cart')) || [];
        } catch {
            return [];
        }
    },

    save(items) {
        localStorage.setItem('driveway_cart', JSON.stringify(items));
        this.updateBadge();
        this.syncToServer(items);
    },

    add(product) {
        const items = this.get();
        const existing = items.find(i => i.id === product.id);
        const maxQty = product.available || 99;
        if (existing) {
            if (existing.qty >= maxQty) {
                this.showToast(product.name, true);
                return items.length;
            }
            existing.qty = existing.qty + 1;
        } else {
            items.push({ ...product, qty: 1 });
        }
        this.save(items);
        this.showToast(product.name, false);
        return items.length;
    },

    remove(id) {
        const items = this.get().filter(i => i.id !== id);
        this.save(items);
    },

    setQty(id, qty) {
        const items = this.get();
        const item = items.find(i => i.id === id);
        if (item) {
            if (qty < 1) {
                this.remove(id);
                return;
            }
            item.qty = Math.min(qty, item.available || 99);
            this.save(items);
        }
    },

    clear() {
        this.save([]);
    },

    total() {
        return this.get().reduce((sum, i) => sum + i.price * i.qty, 0);
    },

    count() {
        return this.get().reduce((sum, i) => sum + i.qty, 0);
    },

    syncToServer(items) {
        if (!window.CURRENT_USER_ID) return;
        fetch('api/cart.php?action=sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items }),
        }).catch(() => {});
    },

    refreshAvailability(callback) {
        const items = this.get();
        if (items.length === 0) { if (callback) callback(); return; }
        const ids = items.map(i => i.id).join(',');
        fetch('api/availability.php?ids=' + ids)
            .then(r => r.json())
            .then(map => {
                let changed = false;
                items.forEach(item => {
                    const fresh = map[item.id];
                    if (fresh !== undefined) {
                        // в корзине больше чем в наличии — обрезаем
                        if (item.qty > fresh) { item.qty = Math.max(1, fresh); changed = true; }
                        item.available = fresh;
                        changed = true;
                    }
                });
                if (changed) {
                    localStorage.setItem('driveway_cart', JSON.stringify(items));
                    this.updateBadge();
                }
                if (callback) callback();
            })
            .catch(() => { if (callback) callback(); });
    },

    loadFromServer() {
        if (!window.CURRENT_USER_ID) return;
        fetch('api/cart.php?action=get')
            .then(r => r.json())
            .then(data => {
                if (data.success && Array.isArray(data.items)) {
                    const serverItems = data.items.map(i => ({
                        id:        parseInt(i.id),
                        qty:       parseInt(i.qty),
                        name:      i.name,
                        price:     parseFloat(i.price),
                        image:     i.image || '',
                        article:   i.article || '',
                        available: parseInt(i.available) || 99,
                    }));
                    localStorage.setItem('driveway_cart', JSON.stringify(serverItems));
                    this.updateBadge();
                    if (typeof renderCart === 'function') renderCart();
                }
            })
            .catch(() => {});
    },

    updateBadge() {
        const count = this.count();
        let badge = document.querySelector('.cart-count');
        const cartBtn = document.querySelector('.cart-button');
        if (!cartBtn) return;

        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'cart-count';
                cartBtn.style.position = 'relative';
                cartBtn.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            if (badge) badge.style.display = 'none';
        }
    },

    showToast(name, maxReached = false) {
        let toast = document.getElementById('cart-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'cart-toast';
            toast.innerHTML = `
                <div class="cart-toast-inner">
                    <i class="fas fa-check-circle" id="cart-toast-icon"></i>
                    <span id="cart-toast-text"></span>
                    <a href="cart.php" class="cart-toast-link" id="cart-toast-link">Перейти в корзину</a>
                </div>`;
            document.body.appendChild(toast);
        }
        const icon = document.getElementById('cart-toast-icon');
        const link = document.getElementById('cart-toast-link');
        if (maxReached) {
            icon.className = 'fas fa-exclamation-circle';
            toast.style.background = '#e67e22';
            document.getElementById('cart-toast-text').textContent = 'Достигнут максимум для: ' + (name.length > 25 ? name.slice(0, 25) + '…' : name);
            link.style.display = 'none';
        } else {
            icon.className = 'fas fa-check-circle';
            toast.style.background = '';
            document.getElementById('cart-toast-text').textContent = 'Добавлено: ' + (name.length > 30 ? name.slice(0, 30) + '…' : name);
            link.style.display = '';
        }
        toast.classList.add('show');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.classList.remove('show'), 3000);
    },

    initButtons() {
        // .add-to-cart-large обрабатывается в product.php вместе с полем кол-ва
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            if (btn.dataset.cartInit) return;
            btn.dataset.cartInit = '1';
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id        = parseInt(btn.dataset.id);
                const name      = btn.dataset.name;
                const price     = parseFloat(btn.dataset.price);
                const image     = btn.dataset.image || '';
                const article   = btn.dataset.article || '';
                const available = parseInt(btn.dataset.available) || 99;
                if (!id || !name || !price) return;
                Cart.add({ id, name, price, image, article, available });

                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Добавлено';
                btn.style.background = '#2ecc71';
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.style.background = '';
                }, 1500);
            });
        });
        this.updateBadge();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Cart.loadFromServer();
    Cart.initButtons();
    Cart.updateBadge();
});

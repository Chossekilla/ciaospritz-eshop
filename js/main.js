// CIAO SPRITZ - Hlavní JavaScript

document.addEventListener('DOMContentLoaded', function () {

    // ==================== HAMBURGER MENU ====================
    const hamburger = document.getElementById('hamburger');
    const nav = document.getElementById('nav');

    if (hamburger && nav) {
        hamburger.addEventListener('click', function () {
            nav.classList.toggle('open');
            hamburger.classList.toggle('active');
        });

        // Zavřít menu po kliknutí na odkaz
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                nav.classList.remove('open');
                hamburger.classList.remove('active');
            });
        });
    }

    // ==================== HEADER SCROLL ====================
    const header = document.getElementById('header');
    if (header) {
        window.addEventListener('scroll', function () {
            header.classList.toggle('scrolled', window.scrollY > 20);
        });
    }

    // ==================== CATEGORY FILTER ====================
    const filterBtns = document.querySelectorAll('.filter-btn');
    const productCards = document.querySelectorAll('.product-card[data-category]');

    if (filterBtns.length && productCards.length) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const category = this.dataset.category;

                productCards.forEach(card => {
                    if (category === 'vse' || card.dataset.category === category) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    }

    // ==================== KOŠÍK - PŘIDAT ====================
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const productId = this.dataset.id;
            const qty = document.getElementById('qty-' + productId)?.value || 1;

            fetch(window.location.origin+'/cart-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${productId}&quantity=${qty}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    updateCartBadge(data.cartCount);
                    showToast(data.message || 'Přidáno do košíku!', 'success');
                    
                    // Animace tlačítka
                    this.textContent = '✓ Přidáno';
                    this.style.background = 'var(--green)';
                    setTimeout(() => {
                        this.textContent = '🛒 Do košíku';
                        this.style.background = '';
                    }, 2000);
                }
            })
            .catch(() => showToast('Chyba při přidání do košíku', 'error'));
        });
    });

    // ==================== KOŠÍK - MNOŽSTVÍ ====================
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const action = this.dataset.action;
            const productId = this.dataset.id;

            fetch(window.location.origin+'/cart-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}&product_id=${productId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
    });

    // ==================== TOAST NOTIFIKACE ====================
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: ${type === 'success' ? 'var(--green)' : '#dc3545'};
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        `;

        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // ==================== UPDATE CART BADGE ====================
    function updateCartBadge(count) {
        let badge = document.querySelector('.cart-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'cart-badge';
                document.querySelector('.cart-btn')?.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }

    // ==================== SMOOTH SCROLL ====================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ==================== ANIMACE NA SCROLL ====================
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.product-card, .article-card, .benefit-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });

    // CSS pro viditelné elementy
    const style = document.createElement('style');
    style.textContent = '.visible { opacity: 1 !important; transform: translateY(0) !important; }';
    document.head.appendChild(style);

});

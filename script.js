// ===== DOM Ready =====
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initScrollProgress();
    initScrollAnimations();
    initCountUp();
    initCountdown();
    initSmoothScroll();
    initStickyCta();
    initSocialProofToasts();
    initExitIntent();
    initLeadForms();
    initVideoPlaceholder();
});

// ===== NAVIGATION =====
function initNavigation() {
    const nav = document.getElementById('nav');
    const burger = document.getElementById('burger');
    const mobileMenu = document.getElementById('mobileMenu');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            nav.classList.add('nav--scrolled');
        } else {
            nav.classList.remove('nav--scrolled');
        }
    }, { passive: true });

    burger.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.toggle('active');
        burger.setAttribute('aria-expanded', isOpen);
        const spans = burger.querySelectorAll('span');
        if (isOpen) {
            spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
            spans[1].style.opacity = '0';
            spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
        } else {
            spans[0].style.transform = '';
            spans[1].style.opacity = '';
            spans[2].style.transform = '';
        }
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            burger.setAttribute('aria-expanded', 'false');
            const spans = burger.querySelectorAll('span');
            spans[0].style.transform = '';
            spans[1].style.opacity = '';
            spans[2].style.transform = '';
        });
    });
}

// ===== SCROLL PROGRESS BAR =====
function initScrollProgress() {
    const bar = document.getElementById('scrollProgress');
    window.addEventListener('scroll', () => {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        bar.style.width = progress + '%';
    }, { passive: true });
}

// ===== SCROLL ANIMATIONS (Intersection Observer) =====
function initScrollAnimations() {
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const elements = document.querySelectorAll('[data-animate]');

    if (prefersReduced) {
        elements.forEach(el => el.classList.add('animated'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = entry.target.dataset.delay || 0;
                setTimeout(() => {
                    entry.target.classList.add('animated');
                }, parseInt(delay));
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -30px 0px'
    });

    elements.forEach(el => observer.observe(el));
}

// ===== COUNT UP ANIMATION =====
function initCountUp() {
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const counters = document.querySelectorAll('[data-count]');

    if (prefersReduced) {
        counters.forEach(el => { el.textContent = el.dataset.count; });
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.count);
                animateCount(el, 0, target, 2000);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(counter => observer.observe(counter));
}

function animateCount(element, start, end, duration) {
    const startTime = performance.now();
    const range = end - start;

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = Math.round(start + range * eased);
        if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
}

// ===== COUNTDOWN TIMER =====
function initCountdown() {
    const STORAGE_KEY = 'book_countdown_end';
    let endTime = localStorage.getItem(STORAGE_KEY);

    if (!endTime || parseInt(endTime) < Date.now()) {
        endTime = Date.now() + 48 * 60 * 60 * 1000;
        localStorage.setItem(STORAGE_KEY, endTime.toString());
    } else {
        endTime = parseInt(endTime);
    }

    const hoursEl = document.getElementById('cd-hours');
    const minutesEl = document.getElementById('cd-minutes');
    const secondsEl = document.getElementById('cd-seconds');

    function tick() {
        const diff = Math.max(0, endTime - Date.now());
        hoursEl.textContent = String(Math.floor(diff / 3600000)).padStart(2, '0');
        minutesEl.textContent = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
        secondsEl.textContent = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
    }

    tick();
    setInterval(tick, 1000);
}

// ===== SMOOTH SCROLL =====
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (!target) return;
            e.preventDefault();
            const navHeight = document.getElementById('nav').offsetHeight;
            window.scrollTo({
                top: target.getBoundingClientRect().top + window.scrollY - navHeight - 20,
                behavior: 'smooth'
            });
        });
    });
}

// ===== STICKY MOBILE CTA =====
function initStickyCta() {
    const stickyCta = document.getElementById('stickyCta');
    const pricingSection = document.getElementById('pricing');

    window.addEventListener('scroll', () => {
        const scrollY = window.scrollY;
        const showAfter = window.innerHeight * 0.8;
        const pricingTop = pricingSection.getBoundingClientRect().top + scrollY;
        const pricingBottom = pricingTop + pricingSection.offsetHeight;

        // Show after scrolling past hero, hide when pricing is visible
        const inPricingView = scrollY + window.innerHeight > pricingTop && scrollY < pricingBottom;

        if (scrollY > showAfter && !inPricingView) {
            stickyCta.classList.add('visible');
        } else {
            stickyCta.classList.remove('visible');
        }
    }, { passive: true });
}

// ===== SOCIAL PROOF TOASTS =====
function initSocialProofToasts() {
    const toast = document.getElementById('toast');
    const toastAvatar = document.getElementById('toastAvatar');
    const toastName = document.getElementById('toastName');
    const toastAction = document.getElementById('toastAction');
    const toastClose = document.getElementById('toastClose');

    const purchases = [
        { name: 'Андрей из Москвы', action: 'купил тариф «Оптимальный»', initials: 'АМ', time: '3 мин. назад' },
        { name: 'Сергей из Казани', action: 'купил тариф «Базовый»', initials: 'СК', time: '7 мин. назад' },
        { name: 'Дмитрий из СПб', action: 'купил тариф «Премиум»', initials: 'ДС', time: '12 мин. назад' },
        { name: 'Олег из Новосибирска', action: 'скачал бесплатную главу', initials: 'ОН', time: '15 мин. назад' },
        { name: 'Виктор из Краснодара', action: 'купил тариф «Оптимальный»', initials: 'ВК', time: '18 мин. назад' },
        { name: 'Михаил из Тюмени', action: 'купил тариф «Оптимальный»', initials: 'МТ', time: '22 мин. назад' },
        { name: 'Алексей из Самары', action: 'скачал бесплатную главу', initials: 'АС', time: '25 мин. назад' },
        { name: 'Николай из Перми', action: 'купил тариф «Базовый»', initials: 'НП', time: '31 мин. назад' },
    ];

    let currentIndex = 0;
    let toastTimeout;

    function showToast() {
        const purchase = purchases[currentIndex];
        toastAvatar.textContent = purchase.initials;
        toastName.textContent = purchase.name;
        toastAction.textContent = purchase.action + ' · ' + purchase.time;

        toast.classList.add('visible');

        toastTimeout = setTimeout(() => {
            toast.classList.remove('visible');
            currentIndex = (currentIndex + 1) % purchases.length;
        }, 5000);
    }

    toastClose.addEventListener('click', () => {
        toast.classList.remove('visible');
        clearTimeout(toastTimeout);
    });

    // Start after 25 seconds, repeat every 35 seconds
    setTimeout(() => {
        showToast();
        setInterval(showToast, 35000);
    }, 25000);
}

// ===== EXIT INTENT POPUP =====
function initExitIntent() {
    const popup = document.getElementById('exitPopup');
    const closeBtn = document.getElementById('popupClose');
    const exitForm = document.getElementById('exitForm');
    const STORAGE_KEY = 'exit_popup_shown';

    let shown = sessionStorage.getItem(STORAGE_KEY);

    function showPopup() {
        if (shown) return;
        shown = true;
        sessionStorage.setItem(STORAGE_KEY, 'true');
        popup.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }

    function hidePopup() {
        popup.classList.remove('visible');
        document.body.style.overflow = '';
    }

    // Desktop: mouse leaves viewport top
    document.addEventListener('mouseout', (e) => {
        if (e.clientY <= 0 && !shown) {
            showPopup();
        }
    });

    // Mobile: show after 60 seconds of inactivity or scroll up pattern
    let lastScrollY = 0;
    let scrollUpCount = 0;
    window.addEventListener('scroll', () => {
        if (window.scrollY < lastScrollY) {
            scrollUpCount++;
        } else {
            scrollUpCount = 0;
        }
        lastScrollY = window.scrollY;

        // If user scrolled up significantly (might be leaving)
        if (scrollUpCount > 15 && window.scrollY > window.innerHeight * 2) {
            showPopup();
            scrollUpCount = 0;
        }
    }, { passive: true });

    closeBtn.addEventListener('click', hidePopup);

    popup.addEventListener('click', (e) => {
        if (e.target === popup) hidePopup();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hidePopup();
    });

    exitForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = document.getElementById('exitEmail').value;
        if (email) {
            exitForm.innerHTML = '<div class="popup__success"><p>Глава отправлена на <strong>' + escapeHtml(email) + '</strong></p><p>Проверьте почту (и папку «Спам»).</p></div>';
            setTimeout(hidePopup, 3000);
        }
    });
}

// ===== LEAD MAGNET FORMS =====
function initLeadForms() {
    const leadForm = document.getElementById('leadForm');
    if (!leadForm) return;

    leadForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = document.getElementById('leadEmail').value;
        if (email) {
            leadForm.innerHTML = '<div class="free-chapter__success"><p>Глава отправлена на <strong>' + escapeHtml(email) + '</strong></p><p>Проверьте почту (и папку «Спам»). Приятного чтения!</p></div>';
        }
    });
}

// ===== VIDEO PLACEHOLDER =====
function initVideoPlaceholder() {
    const placeholder = document.getElementById('videoPlaceholder');
    if (!placeholder) return;

    function activate() {
        placeholder.innerHTML = '<div class="video-placeholder__message"><p>Здесь будет видео-обзор строительства.</p><p>Подключите YouTube/Vimeo embed для реального видео.</p></div>';
        placeholder.classList.add('video-placeholder--active');
    }

    placeholder.addEventListener('click', activate);
    placeholder.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            activate();
        }
    });
}

// ===== UTILITY =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

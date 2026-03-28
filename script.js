// ===== DOM Ready =====
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initScrollProgress();
    initScrollAnimations();
    initCountUp();
    initSmoothScroll();
    initStickyCta();
    initDeliveryChoice();
    initOrderForm();
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

// ===== DELIVERY CHOICE =====
function initDeliveryChoice() {
    const radios = document.querySelectorAll('input[name="delivery"]');
    const cdekForm = document.getElementById('orderFormCdek');
    const avitoBlock = document.getElementById('orderAvito');
    const optionCdek = document.getElementById('optionCdek');
    const optionAvito = document.getElementById('optionAvito');

    if (!radios.length || !cdekForm || !avitoBlock) return;

    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.value === 'cdek') {
                cdekForm.style.display = '';
                avitoBlock.style.display = 'none';
                optionCdek.classList.add('delivery-option--active');
                optionAvito.classList.remove('delivery-option--active');
            } else {
                cdekForm.style.display = 'none';
                avitoBlock.style.display = '';
                optionCdek.classList.remove('delivery-option--active');
                optionAvito.classList.add('delivery-option--active');
            }
        });
    });

    // CDEK map on Yandex Maps
    initCdekYandexMap();
}

// ===== CDEK PICKUP POINTS ON YANDEX MAP =====
function initCdekYandexMap() {
    const searchInput = document.getElementById('cdekAddressSearch');
    const searchBtn = document.getElementById('cdekSearchBtn');
    const mapWrapper = document.getElementById('cdekMapWrapper');
    const selectedBlock = document.getElementById('cdekSelected');
    const selectedText = document.getElementById('cdekSelectedText');
    const addressInput = document.getElementById('orderCdekPointAddress');
    const changeBtn = document.getElementById('changeCdekPoint');

    if (!searchInput || !mapWrapper) return;

    let myMap = null;
    let isMapReady = false;

    function showMap(coords, zoom) {
        mapWrapper.style.display = 'block';

        if (!myMap) {
            ymaps.ready(function () {
                myMap = new ymaps.Map('cdek-map', {
                    center: coords || [55.76, 37.64],
                    zoom: zoom || 12,
                    controls: ['zoomControl', 'geolocationControl']
                });
                isMapReady = true;
                searchCdekPoints(coords || [55.76, 37.64]);
            });
        } else {
            myMap.setCenter(coords, zoom || 12);
            searchCdekPoints(coords);
        }
    }

    function searchCdekPoints(coords) {
        if (!myMap) return;

        // Удаляем старые метки
        myMap.geoObjects.removeAll();

        // Ищем пункты СДЭК через Яндекс.Поиск по организациям
        var searchControl = new ymaps.control.SearchControl({
            options: { provider: 'yandex#search', noPlacemark: true }
        });

        ymaps.geocode(coords).then(function (res) {
            var cityName = '';
            var geoObj = res.geoObjects.get(0);
            if (geoObj) {
                var addrParts = geoObj.getLocalities();
                cityName = addrParts.length ? addrParts[0] : geoObj.getAdministrativeAreas()[0] || '';
            }

            // Поиск пунктов СДЭК в этом районе
            var searchQuery = 'СДЭК пункт выдачи' + (cityName ? ' ' + cityName : '');
            ymaps.search(searchQuery, {
                boundedBy: myMap.getBounds(),
                strictBounds: false,
                results: 50
            }).then(function (searchRes) {
                searchRes.geoObjects.events.add('click', function (e) {
                    var target = e.get('target');
                    var address = target.properties.get('text') || target.properties.get('name') || '';
                    selectPoint(address);
                });
                myMap.geoObjects.add(searchRes.geoObjects);
            });
        });

        // Добавляем метку пользователя
        var userPlacemark = new ymaps.Placemark(coords, {
            iconCaption: 'Вы здесь'
        }, {
            preset: 'islands#redCircleDotIcon'
        });
        myMap.geoObjects.add(userPlacemark);
    }

    function selectPoint(address) {
        addressInput.value = address;
        selectedText.textContent = address;
        selectedBlock.style.display = 'flex';
        mapWrapper.style.display = 'none';
    }

    function doSearch() {
        var query = searchInput.value.trim();
        if (!query) return;

        ymaps.ready(function () {
            ymaps.geocode(query).then(function (res) {
                var firstResult = res.geoObjects.get(0);
                if (firstResult) {
                    var coords = firstResult.geometry.getCoordinates();
                    showMap(coords, 14);
                } else {
                    showMap([55.76, 37.64], 10);
                }
            });
        });
    }

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doSearch();
        }
    });

    if (changeBtn) {
        changeBtn.addEventListener('click', function () {
            selectedBlock.style.display = 'none';
            mapWrapper.style.display = 'block';
        });
    }
}

// ===== ORDER FORM (Robokassa) =====
// Конфигурация Робокассы — заменить на реальные данные после регистрации
const ROBOKASSA_CONFIG = {
    merchantLogin: 'YOUR_MERCHANT_LOGIN',  // Логин из ЛК Робокассы
    // Пароль #1 используется для формирования подписи
    // ВАЖНО: в продакшене подпись должна формироваться на сервере!
    // Для тестового режима можно использовать на клиенте
    password1: 'YOUR_PASSWORD_1',
    isTest: true,  // true = тестовый режим, false = боевой
    outSumm: '1990',
    description: 'Книга «Каркас над пропастью: строю дом на болоте»',
};

function initOrderForm() {
    const form = document.getElementById('orderFormCdek');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const name = document.getElementById('orderName').value.trim();
        const phone = document.getElementById('orderPhone').value.trim();
        const email = document.getElementById('orderEmail').value.trim();
        const city = document.getElementById('orderCity').value.trim();
        const cdekPointAddress = document.getElementById('orderCdekPointAddress').value;

        if (!name || !phone || !email || !city) {
            alert('Пожалуйста, заполните все поля');
            return;
        }

        if (!cdekPointAddress) {
            alert('Пожалуйста, выберите пункт выдачи СДЭК на карте');
            return;
        }

        // Генерация уникального номера заказа
        const invId = Date.now();

        // Пользовательские параметры (передаются в Робокассу и возвращаются в уведомлении)
        const shpParams = {
            'Shp_name': name,
            'Shp_phone': phone,
            'Shp_email': email,
            'Shp_city': city,
            'Shp_cdek_address': cdekPointAddress,
        };

        // Формирование URL Робокассы
        // ВАЖНО: В продакшене SignatureValue должна вычисляться на СЕРВЕРЕ!
        // На клиенте это только для демонстрации / тестового режима.
        const baseUrl = ROBOKASSA_CONFIG.isTest
            ? 'https://auth.robokassa.ru/Merchant/Index.aspx'
            : 'https://auth.robokassa.ru/Merchant/Index.aspx';

        const params = new URLSearchParams({
            MerchantLogin: ROBOKASSA_CONFIG.merchantLogin,
            OutSum: ROBOKASSA_CONFIG.outSumm,
            InvId: invId,
            Description: ROBOKASSA_CONFIG.description,
            Email: email,
            IsTest: ROBOKASSA_CONFIG.isTest ? '1' : '0',
            ...shpParams,
        });

        // Перенаправление на страницу оплаты Робокассы
        window.location.href = baseUrl + '?' + params.toString();
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

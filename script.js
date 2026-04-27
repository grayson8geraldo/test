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
    initReviewToggle();
    initMetrikaGoals();
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
                    var props = target.properties;

                    // Бизнес-поиск (ymaps.search) — метаданные организации
                    var companyMeta = props.get('metaDataProperty.CompanyMetaData') || {};
                    // Геокодер — полный адрес строкой
                    var geoMeta = props.get('metaDataProperty.GeocoderMetaData') || {};

                    var name = companyMeta.name || props.get('name') || 'Пункт СДЭК';
                    var address = companyMeta.address
                        || geoMeta.text
                        || props.get('description')
                        || '';

                    var full = address ? (name + ': ' + address) : name;
                    selectPoint(full);
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
        searchInput.value = address;
        selectedText.textContent = address;
        selectedBlock.style.display = 'flex';
        mapWrapper.style.display = 'none';
        updateDeliveryCost(address);
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
            addressInput.value = '';
            resetDeliveryCost();
        });
    }
}

// ===== РАСЧЁТ ДОСТАВКИ СДЭК ПО РЕГИОНУ =====
// Та же логика на сервере (payment.php) — итоговая сумма пересчитывается
// и подписывается там, на клиенте только отображение.
var BOOK_PRICE = 2990;

function detectDeliveryZone(address) {
    var lower = (address || '').toLowerCase();

    // Москва и Московская область
    var moscowRe = /моск(?!овск.*обл.*(?!московск))|подольск|балаших|химки|реутов|мытищ|любер|королёв|королев|красногорск|одинцов|жуков|пушкин|щёлков|щелков|долгопруд|зеленоград|солнечногорск|павлов посад|серпухов|видн|лобн|раменск|истр|ногинск|электросталь|орехово-зуев|сергиев посад|дзержинский|чехов|наро-фоминск|можайск|ступин|клин|дмитров|домодедов|апрелевк|бронниц|воскресенск|дедовск|жуковск|зарайск|кашир|коломн|красноарм|лосино-петровск|лыткарин|озёр|озер|пересвет|протвин|пущин|реш|рузск|серебряные пруды|солнечногорск|талдом|фрязин|шатур|щёлков|электрогорск|юбилейн|яхром|подмоск|московская обл/;
    if (moscowRe.test(lower)) {
        return { zone: 'msk', name: 'Москва и МО', cost: 300 };
    }

    // Сибирь и Дальний Восток
    var siberiaRe = /новосибирск|омск|томск|красноярск|иркутск|якут|саха респ|хабаровск|владивосток|магадан|сахалин|камчат|чукот|петропавловск-камчат|благовещенск|чит(?!к)|улан-удэ|кемеров|барнаул|новокузнецк|ангарск|братск|комсомольск-на-амуре|находк|уссурийск|биробиджан|анадырь|норильск|абакан|горно-алтайск|приморск(?:ий)? край|хабаровск(?:ий)? край|бурят|тыв|тува|хакас|чукотск|еврейск|сибирск|дальневосточн|алтайск(?:ий)? край|забайкальск|южно-сахалинск|нерюнгри|мирный|алдан|тында|свободный|зея|шимановск|райчихинск|белогорск|сковородино|холмск|корсаков|охотск|оха|северо-курильск|елизово|вилюч|ессо/;
    if (siberiaRe.test(lower)) {
        return { zone: 'sib', name: 'Сибирь и Дальний Восток', cost: 700 };
    }

    // Всё остальное — Европейская часть России (включая Урал, Юг, СЗФО, ПФО)
    return { zone: 'eu', name: 'Европейская часть России', cost: 500 };
}

function updateDeliveryCost(address) {
    var zone = detectDeliveryZone(address);
    var deliveryRow = document.getElementById('deliveryRow');
    var deliveryLabel = document.getElementById('deliveryLabel');
    var deliveryCost = document.getElementById('deliveryCost');
    var totalAmount = document.getElementById('totalAmount');
    var deliveryZoneInput = document.getElementById('deliveryZone');

    if (!deliveryRow || !totalAmount) return;

    deliveryRow.style.display = 'flex';
    deliveryLabel.textContent = 'Доставка СДЭК (' + zone.name + ')';
    deliveryCost.innerHTML = formatPrice(zone.cost) + ' ₽';
    totalAmount.innerHTML = formatPrice(BOOK_PRICE + zone.cost) + ' ₽';
    if (deliveryZoneInput) deliveryZoneInput.value = zone.zone;
}

function resetDeliveryCost() {
    var deliveryRow = document.getElementById('deliveryRow');
    var totalAmount = document.getElementById('totalAmount');
    var deliveryZoneInput = document.getElementById('deliveryZone');

    if (deliveryRow) deliveryRow.style.display = 'none';
    if (totalAmount) totalAmount.innerHTML = formatPrice(BOOK_PRICE) + ' ₽';
    if (deliveryZoneInput) deliveryZoneInput.value = '';
}

function formatPrice(amount) {
    return String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// ===== ORDER FORM (Robokassa) =====
// Все реквизиты Робокассы (MerchantLogin, Пароль #1/#2, цена, режим тест/бой)
// хранятся на сервере в /robokassa/config.php.
// Подпись формируется в /robokassa/payment.php — это безопасно.
// Клиент только валидирует поля и POST-ит форму на payment.php.

function initOrderForm() {
    const form = document.getElementById('orderFormCdek');
    if (!form) return;

    const surnameInput = document.getElementById('orderSurname');
    const nameInput = document.getElementById('orderName');
    const phoneInput = document.getElementById('orderPhone');
    const emailInput = document.getElementById('orderEmail');

    // ===== ФАМИЛИЯ: только буквы, пробелы, дефис =====
    surnameInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-zА-Яа-яЁё\s\-]/g, '');
    });

    // ===== МАСКА ТЕЛЕФОНА +7 (___) ___-__-__ =====
    function formatPhone(value) {
        var digits = value.replace(/\D/g, '');
        // Если начинается с 8, заменяем на 7
        if (digits.length > 0 && digits[0] === '8') {
            digits = '7' + digits.substring(1);
        }
        // Если не начинается с 7, добавляем
        if (digits.length > 0 && digits[0] !== '7') {
            digits = '7' + digits;
        }
        var formatted = '';
        if (digits.length > 0) formatted = '+' + digits[0];
        if (digits.length > 1) formatted += ' (' + digits.substring(1, 4);
        if (digits.length >= 4) formatted += ')';
        if (digits.length > 4) formatted += ' ' + digits.substring(4, 7);
        if (digits.length > 7) formatted += '-' + digits.substring(7, 9);
        if (digits.length > 9) formatted += '-' + digits.substring(9, 11);
        return formatted;
    }

    phoneInput.addEventListener('input', function () {
        var cursorPos = this.selectionStart;
        var oldLength = this.value.length;
        this.value = formatPhone(this.value);
        var newLength = this.value.length;
        cursorPos += newLength - oldLength;
        this.setSelectionRange(cursorPos, cursorPos);
    });

    phoneInput.addEventListener('focus', function () {
        if (!this.value) this.value = '+7 (';
    });

    phoneInput.addEventListener('blur', function () {
        if (this.value === '+7 (' || this.value === '+7') this.value = '';
    });

    // ===== ИМЯ: только буквы, пробелы, дефис =====
    nameInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-zА-Яа-яЁё\s\-]/g, '');
    });

    // ===== ВАЛИДАЦИЯ =====
    function showError(inputEl, errorId, message) {
        var errorEl = document.getElementById(errorId);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.add('visible');
        }
        inputEl.classList.add('invalid');
        inputEl.classList.remove('valid');
    }

    function clearError(inputEl, errorId) {
        var errorEl = document.getElementById(errorId);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }
        inputEl.classList.remove('invalid');
    }

    function markValid(inputEl, errorId) {
        clearError(inputEl, errorId);
        if (inputEl.value.trim()) inputEl.classList.add('valid');
    }

    function validateSurname() {
        var val = surnameInput.value.trim();
        if (!val) { showError(surnameInput, 'errorSurname', 'Введите фамилию'); return false; }
        if (val.length < 2) { showError(surnameInput, 'errorSurname', 'Фамилия слишком короткая'); return false; }
        markValid(surnameInput, 'errorSurname');
        return true;
    }

    function validateName() {
        var val = nameInput.value.trim();
        if (!val) { showError(nameInput, 'errorName', 'Введите имя'); return false; }
        if (val.length < 2) { showError(nameInput, 'errorName', 'Имя слишком короткое'); return false; }
        markValid(nameInput, 'errorName');
        return true;
    }

    function validatePhone() {
        var digits = phoneInput.value.replace(/\D/g, '');
        if (!digits || digits.length < 2) { showError(phoneInput, 'errorPhone', 'Введите номер телефона'); return false; }
        if (digits.length !== 11) { showError(phoneInput, 'errorPhone', 'Номер должен содержать 11 цифр'); return false; }
        markValid(phoneInput, 'errorPhone');
        return true;
    }

    function validateEmail() {
        var val = emailInput.value.trim();
        if (!val) { showError(emailInput, 'errorEmail', 'Введите email'); return false; }
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!emailRegex.test(val)) { showError(emailInput, 'errorEmail', 'Введите корректный email'); return false; }
        markValid(emailInput, 'errorEmail');
        return true;
    }

    function validateCdek() {
        var val = (document.getElementById('orderCdekPointAddress').value || '').trim();
        var errorEl = document.getElementById('errorCdek');
        if (val.length < 5) {
            if (errorEl) { errorEl.textContent = 'Выберите пункт выдачи СДЭК на карте (введите город и кликните на маркер)'; errorEl.classList.add('visible'); }
            return false;
        }
        if (errorEl) { errorEl.textContent = ''; errorEl.classList.remove('visible'); }
        return true;
    }

    // Валидация при потере фокуса
    surnameInput.addEventListener('blur', validateSurname);
    nameInput.addEventListener('blur', validateName);
    phoneInput.addEventListener('blur', validatePhone);
    emailInput.addEventListener('blur', validateEmail);

    // Убирать ошибку при вводе
    surnameInput.addEventListener('input', function () { if (surnameInput.classList.contains('invalid')) validateSurname(); });
    nameInput.addEventListener('input', function () { if (nameInput.classList.contains('invalid')) validateName(); });
    phoneInput.addEventListener('input', function () { if (phoneInput.classList.contains('invalid')) validatePhone(); });
    emailInput.addEventListener('input', function () { if (emailInput.classList.contains('invalid')) validateEmail(); });

    // ===== ОТПРАВКА ФОРМЫ =====
    // Форма нативно POST-ом уходит на /robokassa/payment.php,
    // где сервер формирует подпись с Паролем #1 (на клиенте пароль не хранится!)
    // и редиректит на Робокассу. Здесь делаем только клиентскую валидацию.
    form.addEventListener('submit', (e) => {
        var isValid = true;
        if (!validateSurname()) isValid = false;
        if (!validateName()) isValid = false;
        if (!validatePhone()) isValid = false;
        if (!validateEmail()) isValid = false;
        if (!validateCdek()) isValid = false;

        if (!isValid) {
            e.preventDefault();
            return;
        }
        // На всякий случай — пересчитываем зону доставки из выбранного адреса
        var zoneInput = document.getElementById('deliveryZone');
        var addrInput = document.getElementById('orderCdekPointAddress');
        if (zoneInput && addrInput && !zoneInput.value && addrInput.value) {
            zoneInput.value = detectDeliveryZone(addrInput.value).zone;
        }
        if (typeof ym === 'function') ym(108704155, 'reachGoal', 'payment_click');
    });
}

// ===== REVIEW TOGGLE (Читать полностью / Свернуть) =====
function initReviewToggle() {
    document.querySelectorAll('.review-card__text').forEach(function (text) {
        // Сначала замеряем полную высоту
        var fullHeight = text.scrollHeight;
        text.classList.add('is-clamped');

        // Ждём рендера, чтобы замерить обрезанную высоту
        requestAnimationFrame(function () {
            var clampedHeight = text.clientHeight;
            // Если текст обрезался — добавляем кнопку
            if (fullHeight > clampedHeight + 2) {
                var btn = document.createElement('button');
                btn.className = 'review-card__toggle';
                btn.textContent = 'Читать полностью';
                text.after(btn);

                btn.addEventListener('click', function () {
                    var isClamped = text.classList.contains('is-clamped');
                    if (isClamped) {
                        text.classList.remove('is-clamped');
                        btn.textContent = 'Свернуть';
                    } else {
                        text.classList.add('is-clamped');
                        btn.textContent = 'Читать полностью';
                    }
                });
            }
        });
    });
}

// ===== YANDEX METRIKA GOALS =====
function initMetrikaGoals() {
    if (typeof ym !== 'function') return;

    // Клик «Перейти на Авито»
    var avitoLink = document.getElementById('avitoLink');
    if (avitoLink) {
        avitoLink.addEventListener('click', function () {
            ym(108704155, 'reachGoal', 'avito_click');
        });
    }

    // Клик по любой кнопке «Заказать книгу» / «Купить» / «Получить книгу»
    document.querySelectorAll('a[href="#pricing"]').forEach(function (link) {
        link.addEventListener('click', function () {
            ym(108704155, 'reachGoal', 'zakaz_click');
        });
    });

    // Просмотр секции «Заказать книгу» (pricing)
    var pricingSection = document.getElementById('pricing');
    if (pricingSection && 'IntersectionObserver' in window) {
        var fired = false;
        var observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && !fired) {
                fired = true;
                ym(108704155, 'reachGoal', 'pricing_view');
                observer.disconnect();
            }
        }, { threshold: 0.3 });
        observer.observe(pricingSection);
    }
}

// ===== UTILITY =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

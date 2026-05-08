@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-12 px-4 sm:px-6 lg:px-0">
        <div class="max-w-3xl mx-auto">
            <x-common.component-card>
                <x-slot name="title">
                    {{ __('messages.send.title') }}
                </x-slot>

                <div id="telegram-sender-root" class="space-y-8">
                    {{-- Toast --}}
                    <div id="toast" class="fixed top-6 right-6 max-w-xs z-[999999] hidden">
                        <div id="toastInner" class="px-5 py-4 rounded-2xl shadow-2xl text-white text-sm">
                            <div id="toastMessage"></div>
                        </div>
                    </div>

                    {{-- Errors --}}
                    <div id="errorsContainer" class="space-y-2" aria-live="polite"></div>

                    {{-- Phone selector --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('messages.send.sender_phone') }}
                        </label>

                        <div class="relative">
                            <button id="phoneToggleBtn" type="button"
                                class="w-full flex items-center justify-between border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-3xl px-6 py-4 text-left text-gray-900 dark:text-gray-100 hover:border-brand-400 transition-all focus:outline-none focus:border-brand-500">
                                <span id="selectedPhoneText"
                                    class="text-base">{{ __('messages.send.phone_placeholder') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div id="phoneList"
                                class="absolute left-0 right-0 mt-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl shadow-2xl max-h-64 overflow-auto z-50 py-2 hidden">
                                <div id="phoneItems" class="divide-y divide-gray-100 dark:divide-gray-700"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Catalog multi-select --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('messages.send.recipient_catalog') }}
                        </label>

                        <div class="relative">
                            <button id="catalogToggleBtn" type="button"
                                class="w-full flex items-center justify-between border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-3xl px-5 py-3.5 text-left text-gray-900 dark:text-gray-100 hover:border-brand-400 transition-all focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-300">
                                <span id="catalogsLabel"
                                    class="text-sm font-medium truncate">{{ __('messages.send.catalog_placeholder') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500 flex-shrink-0 ml-2"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div id="catalogDropdown"
                                class="absolute left-0 right-0 mt-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl max-h-64 overflow-y-auto z-50 hidden">
                                <div id="catalogItems" class="p-3 space-y-1"></div>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 pl-1">
                            {{ __('messages.send.multi_catalog_note') }}
                        </p>
                    </div>

                    {{-- Interval --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('messages.send.interval') }}
                        </label>

                        <select id="intervalSelect"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-3xl px-6 py-4 text-base focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
                        </select>


                    </div>

                    {{-- Duration --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('messages.send.loop_count') }}
                        </label>

                        <div class="relative">
                            <button id="durationToggleBtn" type="button"
                                class="w-full flex items-center justify-between border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-3xl px-5 py-3.5 text-left text-gray-900 dark:text-gray-100 hover:border-brand-400 transition-all focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-300">
                                <span id="selectedDurationText" class="text-sm font-medium truncate">1
                                    {{ __('messages.hour') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500 flex-shrink-0 ml-2"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div id="durationDropdown"
                                class="absolute left-0 right-0 mt-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl z-50 hidden overflow-hidden"
                                style="max-height: 320px;">
                                <div id="durationItems" class="py-2 overflow-y-auto"
                                    style="max-height: 320px; overscroll-behavior: contain;">
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 pl-1">
                            1–48 {{ __('messages.hour') }}.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('messages.send.message_text') }}
                        </label>
                        <textarea id="messageInput" rows="7"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-3xl px-6 py-5 text-base focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all resize-y"
                            placeholder="{{ __('messages.send.message_placeholder') }}"></textarea>
                    </div>

                    {{-- Submit --}}
                    <div class="pt-4">
                        <button id="sendBtn"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-10 py-4 bg-brand-600 hover:bg-brand-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 text-white font-semibold text-base rounded-3xl shadow-xl shadow-brand-500/30 active:scale-[0.97] transition-all">
                            <span id="sendSpinner" class="hidden w-6 h-6 animate-spin">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                            </span>
                            <span id="sendBtnText">{{ __('messages.send.submit') }}</span>
                        </button>
                    </div>
                </div>
            </x-common.component-card>
        </div>
    </div>

    <script>
        (function() {
            const ownPhones = @json($ownPhones ?? []);
            const catalogs = @json($catalogs ?? []);
            const globalCatalogs = @json($globalCatalogs ?? []);
            const minutePackages = @json($minutePackages ?? []);
            const isMinuteMode = @json(isset($minuteAccess) && $minuteAccess->is_active && !empty($minutePackages));

            const root = document.getElementById('telegram-sender-root');
            if (!root) return;

            const phoneToggleBtn = document.getElementById('phoneToggleBtn');
            const phoneList = document.getElementById('phoneList');
            const phoneItems = document.getElementById('phoneItems');
            const selectedPhoneTextEl = document.getElementById('selectedPhoneText');

            const catalogToggleBtn = document.getElementById('catalogToggleBtn');
            const catalogDropdown = document.getElementById('catalogDropdown');
            const catalogItems = document.getElementById('catalogItems');
            const catalogsLabelEl = document.getElementById('catalogsLabel');

            const intervalSelect = document.getElementById('intervalSelect');

            const durationToggleBtn = document.getElementById('durationToggleBtn');
            const durationDropdown = document.getElementById('durationDropdown');
            const durationItems = document.getElementById('durationItems');
            const selectedDurationTextEl = document.getElementById('selectedDurationText');

            const messageInput = document.getElementById('messageInput');
            const errorsContainer = document.getElementById('errorsContainer');

            const toast = document.getElementById('toast');
            const toastInner = document.getElementById('toastInner');
            const toastMessage = document.getElementById('toastMessage');

            const sendBtn = document.getElementById('sendBtn');
            const sendSpinner = document.getElementById('sendSpinner');
            const sendBtnText = document.getElementById('sendBtnText');

            let selectedPhoneId = null;
            let selectedCatalogs = [];
            let selectedInterval = 60; // default: 1 soat
            let selectedDuration = 1; // default: 1 soat
            let loading = false;

            function safeNumber(v, fallback = 0) {
                const n = Number(v);
                return Number.isFinite(n) ? n : fallback;
            }

            function showToast(message = '', type = 'success', timeout = 2500) {
                if (!toast) return;
                toastMessage.textContent = message || @json(__('messages.send.success_default'));
                toastInner.className = 'px-5 py-4 rounded-2xl shadow-2xl text-white text-sm';
                toastInner.classList.add(type === 'success' ? 'bg-emerald-600' : 'bg-red-600');
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, timeout);
            }

            function showErrors(errorsObj) {
                errorsContainer.innerHTML = '';
                if (!errorsObj || Object.keys(errorsObj).length === 0) return;

                Object.keys(errorsObj).forEach(key => {
                    const msgs = Array.isArray(errorsObj[key]) ? errorsObj[key] : [String(errorsObj[key])];
                    const div = document.createElement('div');
                    div.className =
                        'bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-2xl px-5 py-4 text-sm';
                    div.textContent = msgs.join(', ');
                    errorsContainer.appendChild(div);
                });

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            function setLoading(val) {
                loading = !!val;
                if (loading) {
                    sendBtn.setAttribute('disabled', 'disabled');
                    sendSpinner.classList.remove('hidden');
                    sendBtnText.textContent = @json(__('messages.send.sending'));
                } else {
                    sendBtn.removeAttribute('disabled');
                    sendSpinner.classList.add('hidden');
                    sendBtnText.textContent = @json(__('messages.send.submit'));
                }
            }

            function renderPhones() {
                phoneItems.innerHTML = '';
                const list = Array.isArray(ownPhones) ? ownPhones : [];

                if (!list.length) {
                    phoneItems.innerHTML = `<div class="px-6 py-4 text-sm text-gray-500">-</div>`;
                    return;
                }

                list.forEach((p, idx) => {
                    const id = p && p.id != null ? p.id : idx;
                    const phone = p && p.phone ? p.phone : '';

                    const item = document.createElement('div');
                    item.className =
                        'px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer text-base text-gray-900 dark:text-gray-100';
                    item.textContent = phone;

                    item.addEventListener('click', () => {
                        selectPhone(id, phone);
                        hidePhoneList();
                    });

                    phoneItems.appendChild(item);
                });
            }

            function renderCatalogs() {
                catalogItems.innerHTML = '';

                const userCats = Array.isArray(catalogs) ? catalogs : [];
                const globalCats = Array.isArray(globalCatalogs) ? globalCatalogs : [];

                userCats.forEach((cat, idx) => {
                    const cid = (cat && cat.id != null) ? Number(cat.id) : `u_${idx}`;
                    const title = cat && cat.title ? cat.title : '';

                    const container = document.createElement('div');
                    container.className =
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = cid;
                    checkbox.className =
                        'w-4 h-4 text-brand-600 border-gray-300 dark:border-gray-600 rounded focus:ring-brand-500';
                    checkbox.addEventListener('click', (e) => {
                        e.stopPropagation();
                        toggleCatalog(cid);
                        updateCatalogCheckboxes();
                        updateCatalogsLabel();
                    });

                    container.addEventListener('click', () => {
                        toggleCatalog(cid);
                        updateCatalogCheckboxes();
                        updateCatalogsLabel();
                    });

                    const label = document.createElement('label');
                    label.className = 'text-sm text-gray-700 dark:text-gray-300 cursor-pointer truncate flex-1';
                    label.textContent = title;

                    container.appendChild(checkbox);
                    container.appendChild(label);
                    catalogItems.appendChild(container);
                });

                if (globalCats.length) {
                    const sep = document.createElement('div');
                    sep.className = 'pt-4 mt-2 border-t border-gray-100 dark:border-gray-700';

                    const head = document.createElement('div');
                    head.className =
                        'uppercase text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1.5 px-3';
                    head.innerHTML = `<span>🌐</span> ${@json(__('messages.send.global_catalogs'))}`;
                    sep.appendChild(head);

                    globalCats.forEach((cat, idx) => {
                        const cid = (cat && cat.id != null) ? Number(cat.id) : `g_${idx}`;
                        const title = cat && cat.title ? cat.title : '';

                        const container = document.createElement('div');
                        container.className =
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors';

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.value = cid;
                        checkbox.className =
                            'w-4 h-4 text-brand-600 border-gray-300 dark:border-gray-600 rounded focus:ring-brand-500';
                        checkbox.addEventListener('click', (e) => {
                            e.stopPropagation();
                            toggleCatalog(cid);
                            updateCatalogCheckboxes();
                            updateCatalogsLabel();
                        });

                        container.addEventListener('click', () => {
                            toggleCatalog(cid);
                            updateCatalogCheckboxes();
                            updateCatalogsLabel();
                        });

                        const label = document.createElement('label');
                        label.className =
                            'text-sm text-blue-600 dark:text-blue-400 cursor-pointer truncate flex-1';
                        label.textContent = title;

                        container.appendChild(checkbox);
                        container.appendChild(label);
                        sep.appendChild(container);
                    });

                    catalogItems.appendChild(sep);
                }

                updateCatalogCheckboxes();
                updateCatalogsLabel();
            }

            function renderIntervalOptions() {
                intervalSelect.innerHTML = '';

                const hoursList = [1, 2, 3, 4, 6, 8];

                hoursList.forEach((h, index) => {
                    const o = document.createElement('option');
                    o.value = String(h * 61);
                    o.textContent = `${h} ${@json(__('messages.hour'))}`;
                    intervalSelect.appendChild(o);

                    if (index === 0) {
                        selectedInterval = h * 61;
                    }
                });

                if (isMinuteMode && Array.isArray(minutePackages) && minutePackages.length) {
                    const separator = document.createElement('option');
                    separator.disabled = true;
                    separator.textContent = '────────────';
                    intervalSelect.appendChild(separator);

                    minutePackages.forEach((min) => {
                        const o = document.createElement('option');
                        o.value = String(min);
                        o.textContent = `${min} ${@json(__('messages.minute'))}`;
                        intervalSelect.appendChild(o);
                    });
                }

                intervalSelect.value = String(selectedInterval);
            }

            function renderDurationOptions() {
                durationItems.innerHTML = '';

                for (let i = 1; i <= 48; i++) {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className =
                        'w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-800';
                    item.dataset.value = String(i);

                    const left = document.createElement('span');
                    left.className = 'text-gray-800 dark:text-gray-100';
                    left.textContent = `${i} ${@json(__('messages.hour'))}`;

                    const check = document.createElement('span');
                    check.className = 'text-brand-600 dark:text-brand-400 text-xs font-semibold hidden';
                    check.textContent = '✓';

                    item.appendChild(left);
                    item.appendChild(check);

                    item.addEventListener('click', () => {
                        selectedDuration = i;
                        updateDurationLabel();
                        updateDurationActive();
                        hideDurationDropdown();
                    });

                    durationItems.appendChild(item);
                }

                updateDurationLabel();
                updateDurationActive();
            }

            function updateDurationLabel() {
                selectedDurationTextEl.textContent = `${selectedDuration} ${@json(__('messages.hour'))}`;
            }

            function updateDurationActive() {
                const items = durationItems.querySelectorAll('[data-value]');
                items.forEach(el => {
                    const isActive = Number(el.dataset.value) === Number(selectedDuration);
                    el.classList.toggle('bg-brand-50', isActive);
                    el.classList.toggle('dark:bg-brand-950', isActive);

                    const check = el.querySelector('span:last-child');
                    if (check) check.classList.toggle('hidden', !isActive);
                });
            }

            function updateCatalogCheckboxes() {
                const inputs = catalogItems.querySelectorAll('input[type="checkbox"]');
                inputs.forEach(inp => {
                    const val = inp.value;
                    const numeric = Number(val);
                    const exists = selectedCatalogs.indexOf(numeric) !== -1 || selectedCatalogs.indexOf(val) !==
                        -1;
                    inp.checked = exists;
                });
            }

            function updateCatalogsLabel() {
                if (!selectedCatalogs || selectedCatalogs.length === 0) {
                    catalogsLabelEl.textContent = @json(__('messages.send.catalog_placeholder'));
                    return;
                }

                const map = new Map();
                (catalogs || []).forEach(c => {
                    if (c && c.id != null) map.set(Number(c.id), c.title || '')
                });
                (globalCatalogs || []).forEach(c => {
                    if (c && c.id != null) map.set(Number(c.id), c.title || '')
                });

                const names = [];
                selectedCatalogs.forEach(id => {
                    const key = Number(id);
                    if (map.has(key)) names.push(map.get(key));
                    else if (typeof id === 'string') names.push(id);
                });

                if (names.length > 3) {
                    catalogsLabelEl.textContent = names.slice(0, 3).join(', ') + ' va yana ' + (names.length - 3) +
                        ' ta';
                } else {
                    catalogsLabelEl.textContent = names.join(', ');
                }
            }

            function showPhoneList() {
                phoneList.classList.remove('hidden');
            }

            function hidePhoneList() {
                phoneList.classList.add('hidden');
            }

            function showCatalogDropdown() {
                catalogDropdown.classList.remove('hidden');
            }

            function hideCatalogDropdown() {
                catalogDropdown.classList.add('hidden');
            }

            function showDurationDropdown() {
                durationDropdown.classList.remove('hidden');
            }

            function hideDurationDropdown() {
                durationDropdown.classList.add('hidden');
            }

            function selectPhone(id, text) {
                selectedPhoneId = id;
                selectedPhoneTextEl.textContent = text || @json(__('messages.send.phone_placeholder'));
            }

            function toggleCatalog(id) {
                const numeric = Number(id);
                const idxNum = selectedCatalogs.indexOf(numeric);
                const idxRaw = selectedCatalogs.indexOf(String(id));

                if (idxNum === -1 && idxRaw === -1) {
                    if (!Number.isNaN(numeric)) selectedCatalogs.push(numeric);
                    else selectedCatalogs.push(String(id));
                } else {
                    if (idxNum !== -1) selectedCatalogs.splice(idxNum, 1);
                    else selectedCatalogs.splice(idxRaw, 1);
                }
            }

            function validate() {
                const errs = {};

                if (!selectedPhoneId) {
                    errs.phone = [@json(__('messages.send_errors.phone_required'))];
                }

                if (!selectedCatalogs.length) {
                    errs.catalogs = [@json(__('messages.send_errors.catalog_required'))];
                }

                const msg = (messageInput.value || '').trim();
                if (!msg) {
                    errs.message = [@json(__('messages.send_errors.message_required'))];
                }

                // const durationHours = safeNumber(selectedDuration, 1);
                // const intervalMinutes = safeNumber(intervalSelect.value, 60);
                // const intervalHours = intervalMinutes / 60;

                // if (durationHours < 1 || durationHours > 48) {
                //     errs.duration = ['Duration 1–48 soat oralig‘ida bo‘lishi kerak.'];
                // }

                // if (![1, 2, 3, 4, 6, 8].includes(intervalHours)) {
                //     errs.interval = ['Interval faqat 1, 2, 3, 4, 6 yoki 8 soat bo‘lishi kerak.'];
                // }

                // if (durationHours < intervalHours) {
                //     errs.duration = ['Duration intervaldan kichik bo‘lishi mumkin emas.'];
                // }

                showErrors(errs);
                return Object.keys(errs).length === 0;
            }

            async function sendMessages() {
                if (loading) return;
                if (!validate()) return;

                setLoading(true);
                showErrors({});

                const intervalMinutes = safeNumber(intervalSelect.value, 60);
                const durationHours = safeNumber(selectedDuration, 1);

                const payload = {
                    phone_id: selectedPhoneId,
                    catalog_ids: (selectedCatalogs || []).map(v => Number(v) || v),
                    interval: intervalMinutes,
                    duration: durationHours,
                    // loopcount: durationHours,
                    message: messageInput.value || ''
                };

                try {
                    const res = await fetch(@json(route('telegram.send-messages')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        if (data && data.errors) showErrors(data.errors);
                        else if (data && data.message) showErrors({
                            general: [data.message]
                        });
                        else showErrors({
                            general: [@json(__('messages.send_errors.server_error'))]
                        });

                        setLoading(false);
                        return;
                    }

                    showToast(data.message || @json(__('messages.send.success_default')), 'success', 1800);

                    setTimeout(() => {
                        window.location.href = @json(route('departments.show', ['department' => $department->id]));
                    }, 1400);

                } catch (e) {
                    console.error(e);
                    showErrors({
                        general: [@json(__('messages.send_errors.connection_error'))]
                    });
                } finally {
                    setLoading(false);
                }
            }

            document.addEventListener('click', function(ev) {
                const target = ev.target;

                if (phoneList && phoneToggleBtn && !phoneToggleBtn.contains(target) && !phoneList.contains(
                        target)) {
                    hidePhoneList();
                }

                if (catalogDropdown && catalogToggleBtn && !catalogToggleBtn.contains(target) && !
                    catalogDropdown.contains(target)) {
                    hideCatalogDropdown();
                }

                if (durationDropdown && durationToggleBtn && !durationToggleBtn.contains(target) && !
                    durationDropdown.contains(target)) {
                    hideDurationDropdown();
                }
            });

            if (phoneToggleBtn) {
                phoneToggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (phoneList.classList.contains('hidden')) showPhoneList();
                    else hidePhoneList();
                });
            }

            if (catalogToggleBtn) {
                catalogToggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (catalogDropdown.classList.contains('hidden')) showCatalogDropdown();
                    else hideCatalogDropdown();
                });
            }

            if (durationToggleBtn) {
                durationToggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (durationDropdown.classList.contains('hidden')) showDurationDropdown();
                    else hideDurationDropdown();
                });
            }

            if (intervalSelect) {
                intervalSelect.addEventListener('change', function() {
                    selectedInterval = safeNumber(this.value, selectedInterval);
                });
            }

            if (sendBtn) {
                sendBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sendMessages();
                });
            }

            function init() {
                renderPhones();
                renderCatalogs();
                renderIntervalOptions();
                renderDurationOptions();

                selectedPhoneTextEl.textContent = @json(__('messages.send.phone_placeholder'));
                updateCatalogsLabel();
                updateDurationLabel();
                setLoading(false);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endsection

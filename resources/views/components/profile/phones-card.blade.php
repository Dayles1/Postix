@props(['user', 'canEdit' => false, 'canLogout'])

@php
    $activePhoneModel = $user->phones->firstWhere('is_active', true);
    $activePhone = $activePhoneModel?->phone;

    // Oxirgi telefon (eng oxiri)
    $lastPhone = $user->phones->sortByDesc('id')->first()?->phone;

    $hasActivePhone = (bool) $activePhone;

    // Input uchun default qiymat:
    // active bo'lmasa oxirgi telefon chiqadi
    $prefillPhone = $activePhone ?? $lastPhone;
@endphp

<div id="phonesCard" class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 bg-white dark:bg-gray-800 w-full">
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('messages.phones_title') }}</h4>
        <div class="text-sm text-gray-500">{{ __('messages.phones_subtitle') }}</div>
    </div>

    {{-- Current phone bar --}}
    <div id="currentPhoneBar" class="@if(!$hasActivePhone) hidden @endif mb-3">
        <div class="flex items-center gap-2">
            <div class="flex-1 rounded-md border px-3 py-2 bg-gray-50 dark:bg-gray-900">
                <span id="currentPhoneTop" class="text-sm text-gray-800 dark:text-white">
                    {{ $activePhone ?? '-' }}
                </span>
            </div>
            <div class="text-xs text-gray-500">
                <span id="currentPhoneStateTop" class="font-medium">
                    {{ $hasActivePhone ? __('messages.connected') : __('messages.not_connected') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    <div id="phonesAlerts" class="space-y-2 mb-3" aria-live="polite"></div>

    {{-- ACTIVE PHONE --}}
    <div id="activePhoneWrap" class="@if(!$hasActivePhone) hidden @endif mb-3">
        <div class="flex items-center gap-2">
            <div class="flex-1 rounded-md border px-3 py-2 bg-gray-50 dark:bg-gray-900">
                <span id="activePhoneText" class="text-sm text-gray-800 dark:text-white">{{ $activePhone }}</span>
            </div>
            @if ($canLogout)
                <form id="logoutForm" method="POST" action="{{ route('telegram.logout', ['user_id' => $user->id]) }}" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="phone" value="{{ $activePhone }}">
                <button id="btnLogout" type="submit" class="w-full sm:w-auto rounded-2xl bg-red-600 text-white px-3 py-2 text-sm">
                    {{ __('messages.layout.logout') }}
                </button>
            </form>
            @endif
            
        </div>

        <div class="text-xs text-gray-500 mt-2">
            {{ __('messages.status_label') }}:
            <span id="phoneStatusTextActive" class="font-medium">
                {{ $hasActivePhone ? __('messages.connected') : __('messages.not_connected') }}
            </span>
        </div>
    </div>

    {{-- PHONE INPUT --}}
    <div id="phoneInputWrap" class="@if($hasActivePhone) hidden @endif mb-3">
        <label class="block text-xs text-gray-500 mb-1">{{ __('messages.phone_label') }}</label>

        <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
            <input
                id="phoneInput"
                name="phone"
                type="text"
                value="{{ $prefillPhone ?? '' }}"
                class="flex-1 rounded-md border px-3 py-2"
                placeholder="{{ __('messages.phone_placeholder') }}"
                @if(!$canEdit) readonly @endif
            >

            <div class="flex gap-2 w-full sm:w-auto">
                @if($canEdit)
                    <button id="btnSendPhone" class="w-full sm:w-auto rounded-2xl bg-blue-600 text-white px-3 py-2 text-sm">
                        {{ __('messages.sendm') }}
                    </button>
                @endif

                <button id="btnRestart" class="hidden rounded-2xl bg-gray-700 text-white px-3 py-2 text-sm" title="Reload page">
                    Restart
                </button>
            </div>
        </div>

        <div id="phoneFieldError" class="text-sm text-red-600 mt-1" aria-live="polite"></div>

        <div class="text-xs text-gray-500 mt-2">
            {{ __('messages.status_label') }}:
            <span id="phoneStatusText" class="font-medium">
                {{ $hasActivePhone ? __('messages.connected') : __('messages.not_connected') }}
            </span>
        </div>
    </div>

    {{-- CODE INPUT --}}
    <div id="codeWrap" class="hidden mb-3">
        <label class="block text-xs text-gray-500 mb-1">{{ __('messages.enter_code_label') }}</label>
        <div class="flex flex-col sm:flex-row gap-2">
            <input id="codeInput" type="text" class="flex-1 rounded-md border px-3 py-2" placeholder="{{ __('messages.enter_code_placeholder') }}">
            <button id="btnVerifyCode" class="w-full sm:w-auto rounded-2xl bg-green-600 text-white px-3 py-2 text-sm">
                {{ __('messages.verify') }}
            </button>
        </div>
        <div id="codeFieldError" class="text-sm text-red-600 mt-1" aria-live="polite"></div>
    </div>

    {{-- PASSWORD (2FA) --}}
    <div id="passwordWrap" class="hidden mb-3">
        <label class="block text-xs text-gray-500 mb-1">{{ __('messages.enter_password_label') }}</label>
        <div class="flex flex-col sm:flex-row gap-2">
            <input id="passwordInput" type="password" class="flex-1 rounded-md border px-3 py-2" placeholder="{{ __('messages.enter_password_placeholder') }}">
            <button id="btnSendPassword" class="w-full sm:w-auto rounded-2xl bg-indigo-600 text-white px-3 py-2 text-sm">
                {{ __('messages.sendm') }}
            </button>
        </div>
        <div id="passwordFieldError" class="text-sm text-red-600 mt-1" aria-live="polite"></div>
    </div>

    {{-- FAILED / RETRY --}}
    <div id="failedWrap" class="hidden mb-3">
        <div id="failedMessage" class="rounded-md bg-red-50 dark:bg-red-900/30 p-4 text-sm text-red-800 dark:text-red-200">
            {{ __('messages.verification_failed_try_again') }}
        </div>
        <button id="btnReload" class="mt-3 w-full sm:w-auto rounded-2xl bg-gray-700 hover:bg-gray-800 text-white px-6 py-2.5 text-sm font-medium transition">
            {{ __('messages.reload_page') }}
        </button>
    </div>
</div>

<script>
(function(){
    const CAN_EDIT = @json($canEdit);
    const USER_ID  = @json($user->id);

    const HAS_ACTIVE_PHONE = @json($hasActivePhone);
    let currentPhone = @json($activePhone) || null;
    let initialPhone = currentPhone;
    let initialIsActive = @json($hasActivePhone);

    const URL_SEND_PHONE    = "{{ route('telegram.sendPhone') }}";
    const URL_SEND_CODE     = "{{ route('telegram.sendCode') }}";
    const URL_SEND_PASSWORD = "{{ route('telegram.password') }}";
    const URL_STATUS        = "{{ route('telegram.status') }}";
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    @php
    $translations = [
        'pending' => __('messages.pending'),
        'processing' => __('messages.processing'),
        'sms_sent' => __('messages.sms_sent'),
        'need_password' => __('messages.need_password'),
        'success' => __('messages.connected'),
        'failed' => __('messages.failed'),
        'waiting_server' => __('messages.waiting_server'),
        'phone_required' => __('messages.phone_required'),
        'code_required' => __('messages.code_required'),
        'password_required' => __('messages.password_required'),
        'no_permission' => __('messages.no_permission'),
        'network_error' => __('messages.network_error'),
        'verification_failed_try_again' => __('messages.verification_failed_try_again'),
        'verifying' => __('messages.verifying'),
        'checking_2fa' => __('messages.checking_2fa'),
        'connected' => __('messages.connected'),
        'not_connected' => __('messages.not_connected'),
        'no_phone' => '—'
    ];
    @endphp
    const T = @json($translations);

    // Elements
    const container        = document.getElementById('phonesCard');
    const alerts           = document.getElementById('phonesAlerts');

    const currentPhoneBar  = document.getElementById('currentPhoneBar');
    const currentPhoneTop  = document.getElementById('currentPhoneTop');
    const currentPhoneStateTop = document.getElementById('currentPhoneStateTop');

    const activePhoneWrap  = document.getElementById('activePhoneWrap');
    const activePhoneText  = document.getElementById('activePhoneText');
    const phoneStatusTextActive = document.getElementById('phoneStatusTextActive');
    const logoutForm       = document.getElementById('logoutForm');
    const btnLogout        = document.getElementById('btnLogout');

    const phoneInputWrap   = document.getElementById('phoneInputWrap');
    const phoneInput       = document.getElementById('phoneInput');
    const btnSendPhone     = document.getElementById('btnSendPhone');
    const btnRestart       = document.getElementById('btnRestart');

    const codeWrap         = document.getElementById('codeWrap');
    const codeInput        = document.getElementById('codeInput');
    const btnVerifyCode    = document.getElementById('btnVerifyCode');

    const passwordWrap     = document.getElementById('passwordWrap');
    const passwordInput    = document.getElementById('passwordInput');
    const btnSendPassword  = document.getElementById('btnSendPassword');

    const phoneStatusText  = document.getElementById('phoneStatusText');
    const failedWrap       = document.getElementById('failedWrap');
    const failedMessageEl  = document.getElementById('failedMessage');
    const btnReload        = document.getElementById('btnReload');

    const phoneFieldError  = document.getElementById('phoneFieldError');
    const codeFieldError   = document.getElementById('codeFieldError');
    const passwordFieldError = document.getElementById('passwordFieldError');

    let pollInterval = null;
    let polling = false;
    const shownAlerts = new Set();

    function clearAlerts() {
        if (!alerts) return;
        alerts.innerHTML = '';
        shownAlerts.clear();
    }

    function showAlert(type, text, ttl = 6000) {
        if (!alerts || !text) return;
        if (shownAlerts.has(text)) return;
        shownAlerts.add(text);

        const div = document.createElement('div');
        div.className = 'rounded-md px-3 py-2 text-sm';

        if (type === 'success') div.classList.add('bg-green-50','text-green-800','dark:bg-green-900/30','dark:text-green-200');
        else if (type === 'error') div.classList.add('bg-red-50','text-red-800','dark:bg-red-900/30','dark:text-red-200');
        else div.classList.add('bg-gray-50','text-gray-800','dark:bg-gray-800','dark:text-gray-200');

        div.innerText = text;
        alerts.appendChild(div);

        setTimeout(() => {
            if (div.parentNode) div.remove();
            shownAlerts.delete(text);
        }, ttl);
    }

    function setLoading(btn, on) {
        if (!btn) return;
        if (on) {
            btn.dataset.orig = btn.innerHTML;
            btn.innerHTML = '…';
            btn.disabled = true;
        } else {
            btn.innerHTML = btn.dataset.orig || btn.innerHTML;
            btn.disabled = false;
        }
    }

    function show(el) { if (el) el.classList.remove('hidden'); }
    function hide(el) { if (el) el.classList.add('hidden'); }

    function setFieldError(el, msg) { if (!el) return; el.innerText = msg || ''; }
    function clearFieldError(el) { if (!el) return; el.innerText = ''; }

    async function fetchJson(url, options = {}) {
        try {
            const res = await fetch(url, options);
            const text = await res.text();

            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (e) {
                data = { message: text || null };
            }

            return { ok: res.ok, status: res.status, data };
        } catch (e) {
            return { ok: false, status: 0, data: { message: 'network_error' } };
        }
    }

    function startPolling() {
        if (polling) return;
        polling = true;
        if (btnRestart) btnRestart.classList.remove('hidden');
        checkStatus();
        pollInterval = setInterval(checkStatus, 2000);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        polling = false;
        if (btnRestart) btnRestart.classList.add('hidden');
    }

    async function checkStatus() {
        if (!currentPhone) return;

        try {
            const { ok, status, data } = await fetchJson(URL_STATUS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ phone: currentPhone, user_id: USER_ID })
            });

            if (!ok && status !== 422) {
                showAlert('neutral', T['waiting_server'] || 'Waiting for server...');
                return;
            }

            handleStatus(data || {});
        } catch (e) {
            console.error('status check error', e);
            showAlert('error', T['network_error'] || 'Network error');
        }
    }

    function updateTopPhoneDisplay(phone, stateKey) {
        if (phone) {
            show(currentPhoneBar);
        } else if (!HAS_ACTIVE_PHONE) {
            hide(currentPhoneBar);
        }

        if (currentPhoneTop) currentPhoneTop.innerText = phone || T['no_phone'];

        if (currentPhoneStateTop) {
            if (stateKey) {
                currentPhoneStateTop.innerText = T[stateKey] || stateKey;
            } else {
                currentPhoneStateTop.innerText = initialIsActive
                    ? (T['connected'] || 'Connected')
                    : (T['not_connected'] || 'Not connected');
            }
        }
    }

    function handleStatus(d) {
        clearAlerts();
        clearFieldError(phoneFieldError);
        clearFieldError(codeFieldError);
        clearFieldError(passwordFieldError);

        const s = d?.status || '';
        const msg = d?.message || (d?.message_key && T[d.message_key]) || '';

        if (d?.phone) {
            currentPhone = d.phone;

            if (phoneInput) phoneInput.value = currentPhone;
            if (activePhoneText) activePhoneText.innerText = currentPhone;

            updateTopPhoneDisplay(currentPhone, null);
        }

        if (s === 'pending' || s === 'processing') {
            if (phoneStatusText) phoneStatusText.innerText = T['processing'] || 'Processing';
            if (phoneStatusTextActive) phoneStatusTextActive.innerText = T['processing'] || 'Processing';

            updateTopPhoneDisplay(currentPhone, 'processing');
            showAlert('neutral', msg || T['processing']);
            return;
        }

        if (s === 'sms_sent') {
            hide(phoneInputWrap);
            show(currentPhoneBar);
            show(codeWrap);

            updateTopPhoneDisplay(currentPhone, 'sms_sent');
            if (phoneStatusText) phoneStatusText.innerText = msg || (T['sms_sent'] || 'SMS sent');
            showAlert('success', msg || (T['sms_sent'] || 'SMS sent'));
            return;
        }

        if (s === 'success') {
            hide(codeWrap);
            hide(passwordWrap);
            hide(phoneInputWrap);
            show(currentPhoneBar);
            show(activePhoneWrap);

            updateTopPhoneDisplay(currentPhone, 'connected');

            if (phoneStatusTextActive) phoneStatusTextActive.innerText = msg || (T['connected'] || 'Connected');
            showAlert('success', msg || T['success']);
            stopPolling();
            setTimeout(() => window.location.reload(), 1200);
            return;
        }

        if (s === 'need_password' || s === '2fa_password_required') {
            hide(phoneInputWrap);
            hide(codeWrap);
            show(passwordWrap);
            show(currentPhoneBar);

            updateTopPhoneDisplay(currentPhone, 'need_password');
            if (phoneStatusText) phoneStatusText.innerText = msg || (T['need_password'] || 'Need password');
            showAlert('neutral', msg || T['need_password']);
            return;
        }

        if (s === 'failed') {
            hide(phoneInputWrap);
            hide(codeWrap);
            hide(passwordWrap);
            show(failedWrap);
            show(currentPhoneBar);

            const serverMsg = msg || T['verification_failed_try_again'];
            if (failedMessageEl) failedMessageEl.innerText = serverMsg;
            if (phoneStatusText) phoneStatusText.innerText = serverMsg;

            updateTopPhoneDisplay(currentPhone, 'failed');
            showAlert('error', serverMsg);
            stopPolling();
            return;
        }

        if (msg) showAlert('neutral', msg);
    }

    // SEND PHONE
    async function sendPhone(phone) {
        clearFieldError(phoneFieldError);
        clearFieldError(codeFieldError);
        clearFieldError(passwordFieldError);

        if (!CAN_EDIT) {
            showAlert('error', T['no_permission']);
            return;
        }

        if (!phone) {
            setFieldError(phoneFieldError, T['phone_required']);
            showAlert('error', T['phone_required']);
            return;
        }

        currentPhone = phone;
        updateTopPhoneDisplay(currentPhone, 'processing');

        if (container) container.scrollIntoView({ behavior: 'smooth', block: 'start' });

        setLoading(btnSendPhone, true);
        clearAlerts();
        show(currentPhoneBar);

        if (phoneInput) phoneInput.readOnly = true;

        try {
            const { ok, status, data } = await fetchJson(URL_SEND_PHONE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ phone, user_id: USER_ID })
            });

            setLoading(btnSendPhone, false);

            if (status === 422 && data?.errors) {
                const phoneErr = data.errors.phone ? data.errors.phone.join('; ') : null;
                if (phoneErr) {
                    setFieldError(phoneFieldError, phoneErr);
                    showAlert('error', phoneErr);
                } else {
                    const firstErr = Object.values(data.errors).flat()[0];
                    showAlert('error', firstErr || (data.message || 'Validation error'));
                }

                if (!initialPhone && phoneInput) phoneInput.readOnly = false;
                return;
            }

            if (status === 403) {
                const msg = data?.message || T['no_permission'] || 'Forbidden';
                showAlert('error', msg);
                if (!initialPhone && phoneInput) phoneInput.readOnly = false;
                return;
            }

            if (!ok) {
                const msg = data?.message || 'Server error';
                showAlert('error', msg);
                if (!initialPhone && phoneInput) phoneInput.readOnly = false;
                return;
            }

            const serverMsg = data?.message || (T['sms_sent'] ? `${T['sms_sent']} ${currentPhone}` : `SMS sent to ${currentPhone}`);
            showAlert('neutral', serverMsg);

            if (data?.session_id) {
                window._telegramSessionId = data.session_id;
            }

            show(phoneInputWrap);
            hide(phoneInputWrap);
            show(currentPhoneBar);

            startPolling();
        } catch (e) {
            setLoading(btnSendPhone, false);
            showAlert('error', T['network_error']);
            console.error(e);
            if (!initialPhone && phoneInput) phoneInput.readOnly = false;
        }
    }

    // VERIFY CODE
    async function verifyCode(code) {
        clearFieldError(codeFieldError);
        clearFieldError(phoneFieldError);
        clearFieldError(passwordFieldError);

        if (!currentPhone) {
            showAlert('error', T['phone_required']);
            return;
        }

        if (!code) {
            setFieldError(codeFieldError, T['code_required']);
            showAlert('error', T['code_required']);
            return;
        }

        setLoading(btnVerifyCode, true);
        clearAlerts();
        showAlert('neutral', T['verifying'] || 'Verifying code...');

        try {
            const { ok, status, data } = await fetchJson(URL_SEND_CODE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ phone: currentPhone, code, user_id: USER_ID })
            });

            setLoading(btnVerifyCode, false);

            if (status === 422 && data?.errors) {
                const codeErr = data.errors.code ? data.errors.code.join('; ') : null;
                if (codeErr) {
                    setFieldError(codeFieldError, codeErr);
                    showAlert('error', codeErr);
                } else {
                    const firstErr = Object.values(data.errors).flat()[0];
                    showAlert('error', firstErr || (data.message || 'Validation error'));
                }
                return;
            }

            if (!ok) {
                const msg = data?.message || 'Server error';
                showAlert('error', msg);
                return;
            }

            hide(codeWrap);
            show(currentPhoneBar);
            startPolling();

            showAlert('neutral', data?.message || T['verifying'] || 'Processing verification...');
        } catch (e) {
            setLoading(btnVerifyCode, false);
            showAlert('error', T['network_error']);
            console.error(e);
        }
    }

    // SEND PASSWORD
    async function sendPassword(pass) {
        clearFieldError(passwordFieldError);
        clearFieldError(codeFieldError);
        clearFieldError(phoneFieldError);

        if (!currentPhone) {
            showAlert('error', T['phone_required']);
            return;
        }

        if (!pass) {
            setFieldError(passwordFieldError, T['password_required']);
            showAlert('error', T['password_required']);
            return;
        }

        setLoading(btnSendPassword, true);
        clearAlerts();
        showAlert('neutral', T['checking_2fa'] || 'Checking 2FA...');

        try {
            const { ok, status, data } = await fetchJson(URL_SEND_PASSWORD, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ phone: currentPhone, password: pass, user_id: USER_ID })
            });

            setLoading(btnSendPassword, false);

            if (status === 422 && data?.errors) {
                const passErr = data.errors.password ? data.errors.password.join('; ') : null;
                if (passErr) {
                    setFieldError(passwordFieldError, passErr);
                    showAlert('error', passErr);
                } else {
                    const firstErr = Object.values(data.errors).flat()[0];
                    showAlert('error', firstErr || (data.message || 'Validation error'));
                }
                return;
            }

            if (!ok) {
                const msg = data?.message || 'Server error';
                showAlert('error', msg);
                return;
            }

            hide(passwordWrap);
            show(currentPhoneBar);
            startPolling();

            showAlert('neutral', data?.message || T['checking_2fa'] || 'Processing 2FA...');
        } catch (e) {
            setLoading(btnSendPassword, false);
            showAlert('error', T['network_error']);
            console.error(e);
        }
    }

    // Listeners
    if (btnSendPhone) btnSendPhone.addEventListener('click', (e) => {
        e.preventDefault();
        const p = (document.getElementById('phoneInput')?.value || '').trim();
        sendPhone(p);
    });

    if (btnVerifyCode) btnVerifyCode.addEventListener('click', (e) => {
        e.preventDefault();
        verifyCode((codeInput?.value || '').trim());
    });

    if (btnSendPassword) btnSendPassword.addEventListener('click', (e) => {
        e.preventDefault();
        sendPassword((passwordInput?.value || '').trim());
    });

    if (btnReload) btnReload.addEventListener('click', () => window.location.reload());
    if (btnRestart) btnRestart.addEventListener('click', () => window.location.reload());

    if (logoutForm) {
        logoutForm.addEventListener('submit', function(e){
            // agar xohlasangiz AJAX logout qilsa bo'ladi, hozir oddiy submit qoldirildi
        });
    }

    // Init UI
    (function init() {
        clearAlerts();
        hide(codeWrap);
        hide(passwordWrap);
        hide(failedWrap);
        if (btnRestart) btnRestart.classList.add('hidden');

        if (initialPhone && initialIsActive) {
            show(currentPhoneBar);
            show(activePhoneWrap);
            hide(phoneInputWrap);

            if (activePhoneText) activePhoneText.innerText = currentPhone;
            if (phoneStatusTextActive) phoneStatusTextActive.innerText = T['connected'] || 'Connected';
            updateTopPhoneDisplay(currentPhone, 'connected');
        } else {
            hide(currentPhoneBar);
            hide(activePhoneWrap);
            show(phoneInputWrap);

            if (phoneInput) {
                phoneInput.value = phoneInput.value || '';
                phoneInput.readOnly = !CAN_EDIT;
            }

            if (phoneStatusText) phoneStatusText.innerText = T['not_connected'] || 'Not connected';
            updateTopPhoneDisplay(null, null);
        }
    })();
})();
</script>
(function () {
    const config = window.telegramLoginConfig || {};

    const DEPARTMENT_ID = config.departmentId || 0;
    const USERS_INDEX_URL = config.usersIndexUrl || '/';
    const USER_PROFILE_BASE = config.userProfileBase || '/profile';
    const btnUserProfile = document.getElementById('btnUserProfile');

    const ADMIN_SEND_PHONE_URL = config.adminSendPhoneUrl || '';
    const SEND_CODE_URL = config.sendCodeUrl || '';
    const SEND_PASSWORD_URL = config.sendPasswordUrl || '';
    const STATUS_URL = config.statusUrl || '';
    const CREATE_USER_URL = config.createUserUrl || '';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const ROLE_MAP = config.roleMap || {};
    const AUTH_USER_ROLE = config.authUserRole || '';
    const USERS_LIMIT_REACHED = !!config.usersLimitReached;
    const NEW_USER_NEED_BAN = !!config.newUserNeedBan;
    const IS_SUPERADMIN = !!config.isSuperadmin;

    const T = {
        processing: config.texts?.processing || 'Processing...',
        waiting_server: config.texts?.waiting_server || 'Waiting for server...',
        sms_sent: config.texts?.sms_sent || 'SMS sent',
        need_password: config.texts?.need_password || '2FA password required',
        phone_required: config.texts?.phone_required || 'Phone required',
        code_required: config.texts?.code_required || 'Code required',
        password_required: config.texts?.password_required || 'Password required',
        network_error: config.texts?.network_error || 'Network error',
        verification_failed_try_again: config.texts?.verification_failed_try_again || 'Verification failed. Try again.',
        connected: config.texts?.connected || 'Connected',
        ban_required: config.texts?.ban_required || 'Ban start date is required',
    };

    const alertError = document.getElementById('alertError');

    const nameInput = document.getElementById('name');
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    const roleInput = document.getElementById('role');
    const phoneInput = document.getElementById('phone');
    const banStartsAtInput = document.getElementById('ban_starts_at');

    const catalogCheckboxes = Array.from(document.querySelectorAll('.catalog-checkbox'));
    const catalogIdsError = document.getElementById('catalogIdsError');

    const btnSubmit = document.getElementById('btnSubmit');

    const codeWrap = document.getElementById('codeWrap');
    const codeInput = document.getElementById('codeInput');
    const btnVerifyCode = document.getElementById('btnVerifyCode');

    const passwordWrap = document.getElementById('passwordWrap');
    const passwordInputEl = document.getElementById('passwordInput');
    const btnSendPassword = document.getElementById('btnSendPassword');

    const failedWrap = document.getElementById('failedWrap');
    const failedMessage = document.getElementById('failedMessage');
    const btnReload = document.getElementById('btnReload');

    const withoutPhoneCheckbox = document.getElementById('withoutPhoneCheckbox');
    const minuteCheckbox = document.getElementById('minute_package');
    const minuteBlock = document.getElementById('minuteBlock');
    const userLimitBlock = document.getElementById('userLimitBlock');
    const userLimitInput = document.getElementById('userLimitInput');

    const phoneFeedback = document.getElementById('phoneFeedback');
    const codeFeedback = document.getElementById('codeFeedback');
    const passwordFeedback = document.getElementById('passwordFeedback');

    const nameError = document.getElementById('nameError');
    const loginError = document.getElementById('loginError');
    const passwordError = document.getElementById('passwordError');
    const roleError = document.getElementById('roleError');
    const phoneError = document.getElementById('phoneError');
    const userLimitError = document.getElementById('userLimitError');
    const banStartsAtError = document.getElementById('banStartsAtError');

    const topInputs = document.getElementById('topInputs');
    const phoneBlock = document.getElementById('phoneBlock');
    const withoutPhoneBlock = document.getElementById('withoutPhoneBlock');

    let currentPhone = null;
    let currentUserId = null;
    let pollInterval = null;
    let polling = false;
    let currentStep = 'phone';

    function hide(el) {
        if (el) el.classList.add('hidden');
    }

    function show(el) {
        if (el) el.classList.remove('hidden');
    }

    function clearFieldErrors() {
        [nameError, loginError, passwordError, roleError, catalogIdsError, phoneError, userLimitError, banStartsAtError].forEach(el => {
            if (el) {
                el.textContent = '';
                hide(el);
            }
        });
    }

    function setLoading(btn, on) {
        if (!btn) return;

        if (on) {
            btn.dataset.orig = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span>';
            btn.disabled = true;
        } else {
            btn.innerHTML = btn.dataset.orig || btn.innerHTML;
            btn.disabled = false;
        }
    }

    function showFeedback(feedbackEl, text, type = 'neutral') {
        if (!feedbackEl) return;

        feedbackEl.innerHTML = '';

        const div = document.createElement('div');
        div.className = `flex items-center gap-2 ${
            type === 'success'
                ? 'feedback-success'
                : type === 'error'
                    ? 'feedback-error'
                    : 'feedback-neutral'
        }`;

        if (text && (text.toLowerCase().includes('processing') || text.toLowerCase().includes('waiting'))) {
            div.innerHTML = `<span class="spinner"></span><span>${text}</span>`;
        } else {
            div.textContent = text;
        }

        feedbackEl.appendChild(div);
    }

    function clearAllFeedback() {
        [phoneFeedback, codeFeedback, passwordFeedback].forEach(el => {
            if (el) el.innerHTML = '';
        });
    }

    function lockInputs() {
        if (topInputs) topInputs.classList.add('locked-field');

        [nameInput, loginInput, passwordInput, roleInput, phoneInput, userLimitInput, withoutPhoneCheckbox, minuteCheckbox, banStartsAtInput]
            .forEach(el => {
                if (el) el.disabled = true;
            });

        catalogCheckboxes.forEach(cb => cb.disabled = true);

        if (btnSubmit) btnSubmit.disabled = true;
    }

    function getCheckedCatalogIds() {
        if (!IS_SUPERADMIN) return [];

        return catalogCheckboxes
            .filter(cb => cb.checked)
            .map(cb => parseInt(cb.value, 10))
            .filter(id => Number.isInteger(id) && id > 0);
    }

    function appendHiddenInput(form, name, value) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = name;
        inp.value = value ?? '';
        form.appendChild(inp);
    }

    function appendHiddenArray(form, name, values) {
        (values || []).forEach((val) => {
            appendHiddenInput(form, `${name}[]`, val);
        });
    }

    function validate(requirePhone = true) {
        clearFieldErrors();

        if (roleInput && !roleInput.value) {
            roleError.textContent = 'Role tanlang';
            show(roleError);
            return false;
        }

        if (userLimitBlock && !userLimitBlock.classList.contains('hidden')) {
            const v = parseInt(userLimitInput.value || '', 10);
            if (!Number.isInteger(v) || v < 1) {
                userLimitError.textContent = 'Iltimos, 1 yoki undan katta butun son kiriting';
                show(userLimitError);
                return false;
            }
        }

        if (NEW_USER_NEED_BAN && !(banStartsAtInput && banStartsAtInput.value)) {
            banStartsAtError.textContent = T.ban_required;
            show(banStartsAtError);
            return false;
        }

        if (requirePhone && !phoneInput.value.trim()) {
            phoneError.textContent = T.phone_required;
            show(phoneError);
            return false;
        }

        return true;
    }

    function onRoleChange() {
        const roleName = (ROLE_MAP[roleInput?.value] ?? '').toLowerCase();

        if (roleName === 'admin') {
            hide(phoneBlock);
            hide(minuteBlock);
            hide(withoutPhoneBlock);
        } else {
            show(phoneBlock);
            show(minuteBlock);
            show(withoutPhoneBlock);
        }

        if (AUTH_USER_ROLE === 'superadmin' && roleName === 'admin') {
            show(userLimitBlock);
        } else {
            hide(userLimitBlock);
        }
    }

    if (roleInput) roleInput.addEventListener('change', onRoleChange);
    onRoleChange();

    if (USERS_LIMIT_REACHED && AUTH_USER_ROLE === 'admin') {
        lockInputs();
        const limitAlert = document.getElementById('limitAlert');
        if (limitAlert) {
            limitAlert.classList.remove('hidden');
        } else {
            showAlertGlobal('Foydalanuvchilar limiti to\'ldirilgan — yangi foydalanuvchi qo‘shib bo‘lmaydi.');
        }
    }

    function applyWithoutPhoneUI(isWithoutPhone) {
        if (isWithoutPhone) {
            hide(phoneBlock);
            if (phoneInput) phoneInput.value = '';
            if (minuteCheckbox) minuteCheckbox.checked = false;
        } else {
            show(minuteBlock);

            const roleName = (ROLE_MAP[roleInput?.value] ?? '').toLowerCase();
            if (roleName === 'admin') {
                hide(phoneBlock);
            } else {
                show(phoneBlock);
            }
        }
    }

    if (withoutPhoneCheckbox) {
        withoutPhoneCheckbox.addEventListener('change', (e) => {
            applyWithoutPhoneUI(e.target.checked);
        });

        applyWithoutPhoneUI(withoutPhoneCheckbox.checked);
    }

    function startPolling() {
        if (polling) return;
        polling = true;
        checkStatus();
        pollInterval = setInterval(checkStatus, 2000);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        polling = false;
    }

    async function checkStatus() {
        if (!currentPhone || !currentUserId) return;

        try {
            const res = await fetch(STATUS_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: currentPhone, user_id: currentUserId })
            });

            const data = await res.json().catch(() => null);
            handleStatus(data);
        } catch (e) {
            showAlertGlobal(T.network_error);
        }
    }

    function handleStatus(d) {
        if (!d) {
            showAlertGlobal(T.network_error);
            stopPolling();
            return;
        }

        const s = d?.status || '';
        const msg = d?.message || T.processing;

        if (s === 'pending' || s === 'processing') {
            const fb = currentStep === 'code' ? codeFeedback : currentStep === 'password' ? passwordFeedback : phoneFeedback;
            showFeedback(fb, msg, 'neutral');
            return;
        }

        stopPolling();

        if (s === 'sms_sent') {
            currentStep = 'code';
            hide(phoneFeedback);
            show(codeWrap);
            showFeedback(codeFeedback, msg || T.sms_sent, 'success');
            codeInput.focus();
            return;
        }

        if (s === 'need_password' || s === '2fa_password_required') {
            currentStep = 'password';
            hide(codeFeedback);
            show(passwordWrap);
            showFeedback(passwordFeedback, msg || T.need_password, 'neutral');
            return;
        }

        if (s === 'success') {
            const redirectUrl = d?.redirect || null;
            const userId = d?.user_id || d?.user?.id || currentUserId;

            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }

            if (userId) {
                window.location.href = `${USER_PROFILE_BASE}/${userId}`;
                return;
            }

            window.location.href = USERS_INDEX_URL;
            return;
        }

        if (s === 'failed') {
            hide(codeWrap);
            hide(passwordWrap);
            show(failedWrap);

            failedMessage.textContent = d?.message || T.verification_failed_try_again;

            const userId = d?.user_id || currentUserId;
            if (userId && btnUserProfile) {
                btnUserProfile.href = `${USER_PROFILE_BASE}/${userId}`;
                btnUserProfile.classList.remove('hidden');
            } else if (btnUserProfile) {
                btnUserProfile.classList.add('hidden');
            }

            return;
        }

        showAlertGlobal(msg);
    }

    function showAlertGlobal(text) {
        if (!alertError) return;
        alertError.textContent = text;
        show(alertError);
        setTimeout(() => hide(alertError), 6000);
    }

    function getBanStartsAt() {
        return banStartsAtInput?.value || null;
    }

    function buildCreatePayload() {
        return {
            name: nameInput.value.trim(),
            login: loginInput.value.trim(),
            password: passwordInput.value.trim(),
            role_id: roleInput ? roleInput.value : null,
            department_id: DEPARTMENT_ID,
            minute_package: (minuteCheckbox && minuteCheckbox.checked) ? 1 : 0,
            ban_starts_at: getBanStartsAt(),
            catalog_ids: getCheckedCatalogIds(),
        };
    }

    function addCatalogIdsToForm(form, catalogIds) {
        (catalogIds || []).forEach((id) => {
            appendHiddenInput(form, 'catalog_ids[]', id);
        });
    }

    async function sendPhone() {
        const requirePhone = !(withoutPhoneCheckbox && withoutPhoneCheckbox.checked);

        if (!validate(requirePhone)) return;

        if (withoutPhoneCheckbox && withoutPhoneCheckbox.checked) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = CREATE_USER_URL;
            form.style.display = 'none';

            appendHiddenInput(form, '_token', CSRF);

            const fields = buildCreatePayload();

            appendHiddenInput(form, 'name', fields.name);
            appendHiddenInput(form, 'login', fields.login);
            appendHiddenInput(form, 'password', fields.password);
            appendHiddenInput(form, 'role_id', fields.role_id);
            appendHiddenInput(form, 'department_id', fields.department_id);
            appendHiddenInput(form, 'minute_package', fields.minute_package);
            appendHiddenInput(form, 'ban_starts_at', fields.ban_starts_at);

            if (IS_SUPERADMIN) {
                addCatalogIdsToForm(form, fields.catalog_ids);
            }

            const selectedRoleName = (ROLE_MAP[roleInput?.value] ?? '').toLowerCase();
            if (AUTH_USER_ROLE === 'superadmin' && selectedRoleName === 'admin' && userLimitInput) {
                appendHiddenInput(form, 'user_limit', parseInt(userLimitInput.value || '10', 10) || 10);
            }

            document.body.appendChild(form);
            form.submit();
            return;
        }

        setLoading(btnSubmit, true);
        clearAllFeedback();
        currentStep = 'phone';
        showFeedback(phoneFeedback, T.processing, 'neutral');

        const payload = {
            ...buildCreatePayload(),
            phone: phoneInput.value.trim(),
            auth_user_role: AUTH_USER_ROLE,
        };

        try {
            const res = await fetch(ADMIN_SEND_PHONE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            setLoading(btnSubmit, false);

            if (!res.ok) {
                const json = await res.json().catch(() => null);

                if (json?.errors) {
                    Object.keys(json.errors).forEach(k => {
                        const map = {
                            name: 'nameError',
                            login: 'loginError',
                            password: 'passwordError',
                            role_id: 'roleError',
                            catalog_ids: 'catalogIdsError',
                            phone: 'phoneError',
                            ban_starts_at: 'banStartsAtError'
                        };

                        const errEl = document.getElementById(map[k] || k + 'Error');
                        if (errEl) {
                            errEl.textContent = json.errors[k].join(' ');
                            show(errEl);
                        }
                    });
                    return;
                }

                showAlertGlobal(json?.message || 'Server error');
                return;
            }

            const data = await res.json().catch(() => null);

            if (data?.status === 'processing' && data?.user_id) {
                currentPhone = payload.phone;
                currentUserId = data.user_id;

                lockInputs();
                btnSubmit.style.display = 'none';
                startPolling();
                showFeedback(phoneFeedback, T.waiting_server, 'neutral');
            } else if (data?.status === 'success') {
                if (data?.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data?.user_id) {
                    window.location.href = `${USER_PROFILE_BASE}/${data.user_id}`;
                    return;
                }

                window.location.href = USERS_INDEX_URL;
                return;
            } else {
                showAlertGlobal(data?.message || 'Unknown error');
            }
        } catch (e) {
            setLoading(btnSubmit, false);
            showAlertGlobal(T.network_error);
        }
    }

    async function createUserDirectly() {
        if (!validate(false)) return;

        if (USERS_LIMIT_REACHED && AUTH_USER_ROLE === 'admin') {
            showAlertGlobal('Foydalanuvchilar limiti to‘ldirilgan — yangi foydalanuvchi qo‘shib bo‘lmaydi.');
            return;
        }

        setLoading(btnSubmit, true);
        clearAllFeedback();

        const payload = buildCreatePayload();

        const selectedRoleName = (ROLE_MAP[roleInput?.value] ?? '').toLowerCase();
        if (AUTH_USER_ROLE === 'superadmin' && selectedRoleName === 'admin' && userLimitInput) {
            payload.user_limit = parseInt(userLimitInput.value || '10', 10) || 10;
        }

        try {
            const res = await fetch(CREATE_USER_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            setLoading(btnSubmit, false);

            if (!res.ok) {
                const json = await res.json().catch(() => null);

                if (json?.errors) {
                    Object.keys(json.errors).forEach(k => {
                        const map = {
                            name: 'nameError',
                            login: 'loginError',
                            password: 'passwordError',
                            role_id: 'roleError',
                            catalog_ids: 'catalogIdsError',
                            user_limit: 'userLimitError',
                            ban_starts_at: 'banStartsAtError'
                        };

                        const errEl = document.getElementById(map[k] || k + 'Error');
                        if (errEl) {
                            errEl.textContent = json.errors[k].join(' ');
                            show(errEl);
                        }
                    });
                    return;
                }

                showAlertGlobal(json?.message || 'Server error');
                return;
            }

            const data = await res.json();

            if (data?.status === 'success') {
                const userId = data.user_id || data.user?.id;
                if (userId) {
                    window.location.href = `${USER_PROFILE_BASE}/${userId}`;
                } else {
                    window.location.href = USERS_INDEX_URL;
                }
            } else {
                showAlertGlobal(data?.message || 'Unknown error');
            }
        } catch (e) {
            setLoading(btnSubmit, false);
            showAlertGlobal(T.network_error);
        }
    }

    async function verifyCode() {
        const code = codeInput.value.trim();
        if (!code) {
            showFeedback(codeFeedback, T.code_required, 'error');
            return;
        }
        if (!currentUserId) {
            showAlertGlobal('User ID topilmadi');
            return;
        }

        setLoading(btnVerifyCode, true);
        currentStep = 'code';
        showFeedback(codeFeedback, T.processing, 'neutral');

        try {
            const res = await fetch(SEND_CODE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: currentPhone, code, user_id: currentUserId })
            });

            const data = await res.json().catch(() => null);
            setLoading(btnVerifyCode, false);

            if (!res.ok) {
                showFeedback(codeFeedback, data?.message || 'Error', 'error');
                return;
            }

            hide(codeWrap);
            startPolling();
        } catch (e) {
            setLoading(btnVerifyCode, false);
            showFeedback(codeFeedback, T.network_error, 'error');
        }
    }

    async function sendPassword() {
        const pass = passwordInputEl.value.trim();
        if (!pass) {
            showFeedback(passwordFeedback, T.password_required, 'error');
            return;
        }
        if (!currentUserId) return;

        setLoading(btnSendPassword, true);
        currentStep = 'password';
        showFeedback(passwordFeedback, T.processing, 'neutral');

        try {
            const res = await fetch(SEND_PASSWORD_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: currentPhone, password: pass, user_id: currentUserId })
            });

            const data = await res.json().catch(() => null);
            setLoading(btnSendPassword, false);

            if (!res.ok) {
                showFeedback(passwordFeedback, data?.message || 'Error', 'error');
                return;
            }

            startPolling();
        } catch (e) {
            setLoading(btnSendPassword, false);
            showFeedback(passwordFeedback, T.network_error, 'error');
        }
    }

    if (btnSubmit) {
        btnSubmit.addEventListener('click', (e) => {
            e.preventDefault();
            clearFieldErrors();
            clearAllFeedback();

            const roleName = (ROLE_MAP[roleInput?.value] ?? '').toLowerCase();

            if ((withoutPhoneCheckbox && withoutPhoneCheckbox.checked) || roleName === 'admin') {
                createUserDirectly();
            } else {
                sendPhone();
            }
        });
    }

    if (btnVerifyCode) {
        btnVerifyCode.addEventListener('click', (e) => {
            e.preventDefault();
            verifyCode();
        });
    }

    if (btnSendPassword) {
        btnSendPassword.addEventListener('click', (e) => {
            e.preventDefault();
            sendPassword();
        });
    }

    if (btnReload) {
        btnReload.addEventListener('click', () => window.location.reload());
    }

    phoneInput && phoneInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') btnSubmit.click();
    });

    codeInput && codeInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') btnVerifyCode.click();
    });

    passwordInputEl && passwordInputEl.addEventListener('keydown', e => {
        if (e.key === 'Enter') btnSendPassword.click();
    });
})();
/*
|--------------------------------------------------------------------------
| Telegram sessions
|--------------------------------------------------------------------------
|
| Every action on this page only queues an artisan command - MadelineProto
| cannot run inside a web request. So the page never gets an answer back
| from the button it pressed: it gets an account in an in-flight status,
| and polls until the command has written the real outcome onto it.
|
*/

import { createListPage } from './list-page';

/** How often the login dialog asks whether the command has answered. */
const LOGIN_POLL_MS = 2000;

/** How often the list refreshes while some row is still in flight. */
const LIST_POLL_MS = 4000;

/**
 * Telegram error names worth a sentence of their own. Anything else is
 * shown as it came.
 */
const KNOWN_ERRORS = [
    'PHONE_CODE_INVALID',
    'PHONE_CODE_EXPIRED',
    'PASSWORD_HASH_INVALID',
    'PASSWORD_EXPIRED',
    'PHONE_NUMBER_INVALID',
    'PHONE_NUMBER_BANNED',
    'PHONE_NUMBER_FLOOD',
    'FLOOD_WAIT',
    'AUTH_KEY_UNREGISTERED',
    'SESSION_REVOKED',
    'USER_DEACTIVATED',
    'SESSION_NOT_FOUND',
    'NOT_LOGGED_IN',
    'ACCOUNT_NOT_REGISTERED',
];

const TONES = {
    success: {
        badge: 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20',
        dot: 'bg-success-500',
    },
    progress: {
        badge: 'bg-blue-light-50 text-blue-light-700 ring-1 ring-inset ring-blue-light-600/20 dark:bg-blue-light-500/10 dark:text-blue-light-400 dark:ring-blue-light-500/20',
        dot: 'bg-blue-light-500 animate-pulse',
    },
    waiting: {
        badge: 'bg-warning-50 text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20',
        dot: 'bg-warning-500',
    },
    error: {
        badge: 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400 dark:ring-error-500/20',
        dot: 'bg-error-500',
    },
    muted: {
        badge: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/10',
        dot: 'bg-gray-400',
    },
};

const STATE_TONES = {
    listening: 'success',
    stopped: 'waiting',
    active: 'success',
    warning: 'waiting',
    no_file: 'error',
    sending_code: 'progress',
    verifying: 'progress',
    checking: 'progress',
    logging_out: 'progress',
    awaiting_code: 'waiting',
    awaiting_password: 'waiting',
    stale: 'waiting',
    code_invalid: 'error',
    failed: 'error',
    revoked: 'error',
    logged_out: 'muted',
    new: 'muted',
};

/**
 * One word for where an account stands, out of the raw status and the
 * flags around it. Everything on the page is keyed on this.
 */
export function sessionState(row) {
    if (!row) {
        return 'new';
    }

    if (row.is_stale) {
        return 'stale';
    }

    switch (row.status) {
        case 'processing':
            return 'sending_code';
        case 'code_sent':
            return 'awaiting_code';
        case 'verifying':
            return 'verifying';
        case 'need_password':
        case 'password_invalid':
            return 'awaiting_password';
        case 'checking':
            return 'checking';
        case 'logging_out':
            return 'logging_out';
        case 'code_invalid':
            return 'code_invalid';
        case 'revoked':
            return 'revoked';
        case 'logged_out':
            return 'logged_out';
        default:
            break;
    }

    if (row.is_authorized) {
        if (!row.session_exists) {
            return 'no_file';
        }

        /* running / stopped are written by the listener itself. */
        if (row.is_listening) {
            return 'listening';
        }

        if (row.is_primary && row.status === 'stopped') {
            return 'stopped';
        }

        return row.last_error ? 'warning' : 'active';
    }

    return row.status === 'failed' ? 'failed' : 'new';
}

function jsonHeaders(csrf) {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
    };
}

export function sessionsPage(config) {
    const t = config.translations;
    const ui = config.ui;

    const emptyLogin = {
        account: null,
        phone: '',
        code: '',
        password: '',
        /** What was typed last, so "verifying" knows which step it is. */
        lastInput: null,
        submitting: false,
        error: null,
        fieldErrors: {},
    };

    return {
        ui,

        ...createListPage({
            endpoint: config.endpoints.index,

            translations: t,

            defaults: {
                search: '',
                state: '',
                sort: 'created_at',
                direction: 'desc',
                per_page: 20,
                page: 1,
            },

            onLoaded(json) {
                this.stats = {
                    total: Number(json.stats?.total ?? 0),
                    authorized: Number(json.stats?.authorized ?? 0),
                    pending: Number(json.stats?.pending ?? 0),
                    problem: Number(json.stats?.problem ?? 0),
                    logged_out: Number(json.stats?.logged_out ?? 0),
                };

                this.syncOpenAccounts();
                this.scheduleListPoll();
            },
        }),

        endpoints: config.endpoints,

        processNames: config.processes,

        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        stats: { total: 0, authorized: 0, pending: 0, problem: 0, logged_out: 0 },

        listPollTimer: null,

        /* Details dialog */
        detailOpen: false,
        detail: null,
        detailBusy: null,
        detailError: null,

        /* Login wizard */
        loginOpen: false,
        login: { ...emptyLogin },
        loginPollTimer: null,

        /* Confirmation (logout / delete) */
        confirmOpen: false,
        confirmAction: null,
        confirmBusy: false,
        confirmError: null,

        init() {
            this.initList();
        },

        destroy() {
            clearTimeout(this.listPollTimer);
            clearTimeout(this.loginPollTimer);
        },

        /*
        |----------------------------------------------------------------
        | Requests
        |----------------------------------------------------------------
        */

        accountUrl(id, suffix = '') {
            return `${this.endpoints.base}/${encodeURIComponent(id)}${suffix}`;
        },

        async request(url, method = 'POST', body = null) {
            const response = await fetch(url, {
                method,
                headers: jsonHeaders(this.csrf),
                credentials: 'same-origin',
                cache: 'no-store',
                body: body === null ? null : JSON.stringify(body),
            });

            let json = {};

            try {
                json = await response.json();
            } catch (_) {
                json = {};
            }

            if (!response.ok) {
                const error = new Error(json.message || `HTTP ${response.status}`);

                error.status = response.status;
                error.errors = json.errors || {};

                throw error;
            }

            return json.data ?? json;
        },

        async fetchAccount(id) {
            return this.request(this.accountUrl(id), 'GET');
        },

        /**
         * Puts a fresh copy of an account everywhere it is on screen.
         */
        replaceAccount(account) {
            if (!account) {
                return;
            }

            const index = this.rows.findIndex((row) => row.id === account.id);

            if (index !== -1) {
                this.rows.splice(index, 1, account);
            }

            if (this.detail?.id === account.id) {
                this.detail = account;
            }

            if (this.login.account?.id === account.id) {
                this.login.account = account;
            }
        },

        /** After a list reload the details dialog follows the new row. */
        syncOpenAccounts() {
            const fresh = this.detail && this.rows.find((row) => row.id === this.detail.id);

            if (fresh) {
                this.detail = fresh;
            }
        },

        /**
         * Keeps the list moving while some command is still working, so a
         * logout started from the details dialog lands without a click.
         */
        scheduleListPoll() {
            clearTimeout(this.listPollTimer);

            if (!this.rows.some((row) => row.is_in_flight && !row.is_stale)) {
                return;
            }

            this.listPollTimer = setTimeout(() => {
                if (!this.loading) {
                    this.load();
                }
            }, LIST_POLL_MS);
        },

        /*
        |----------------------------------------------------------------
        | Presentation
        |----------------------------------------------------------------
        */

        state: sessionState,

        stateLabel(row) {
            return t.state[sessionState(row)] ?? row?.status ?? this.dash;
        },

        stateHint(row) {
            return t.state_hint[sessionState(row)] ?? '';
        },

        stateClass(row) {
            return TONES[STATE_TONES[sessionState(row)] ?? 'muted'].badge;
        },

        stateDot(row) {
            return TONES[STATE_TONES[sessionState(row)] ?? 'muted'].dot;
        },

        avatarClass(row) {
            return {
                success: 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400',
                progress: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400',
                waiting: 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400',
                error: 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400',
                muted: 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
            }[STATE_TONES[sessionState(row)] ?? 'muted'];
        },

        displayName(row) {
            return row?.name || this.phone(row?.phone);
        },

        avatarText(row) {
            return row?.name ? this.initials(row.name) : '#';
        },

        /**
         * A Telegram error in words, when it is one we know.
         */
        errorText(raw) {
            if (!raw) {
                return '';
            }

            const known = KNOWN_ERRORS.find((key) => String(raw).includes(key));

            if (!known) {
                return raw;
            }

            if (known === 'FLOOD_WAIT') {
                const seconds = String(raw).match(/FLOOD_WAIT_(\d+)/)?.[1];

                return seconds
                    ? t.telegram_errors.FLOOD_WAIT_SECONDS.replace(':seconds', seconds)
                    : t.telegram_errors.FLOOD_WAIT;
            }

            return t.telegram_errors[known] ?? raw;
        },

        processLabel(process) {
            return this.processNames[process?.process] ?? process?.process ?? this.dash;
        },

        processState(process) {
            if (!process.is_available) {
                return 'disabled';
            }

            if (process.is_stale_busy) {
                return 'stuck';
            }

            if (process.is_busy) {
                return 'busy';
            }

            return process.consecutive_failures > 0 ? 'failing' : 'ready';
        },

        processClass(process) {
            return {
                disabled: TONES.error.badge,
                stuck: TONES.waiting.badge,
                busy: TONES.progress.badge,
                failing: TONES.waiting.badge,
                ready: TONES.success.badge,
            }[this.processState(process)];
        },

        processDot(process) {
            return {
                disabled: TONES.error.dot,
                stuck: TONES.waiting.dot,
                busy: TONES.progress.dot,
                failing: TONES.waiting.dot,
                ready: TONES.success.dot,
            }[this.processState(process)];
        },

        processes(row) {
            return Array.isArray(row?.processes) ? row.processes : [];
        },

        canContinueLogin(row) {
            return !!row && !row.is_authorized
                && ['awaiting_code', 'awaiting_password', 'sending_code', 'verifying'].includes(sessionState(row));
        },

        canRestartLogin(row) {
            return !!row && !row.is_authorized
                && ['code_invalid', 'failed', 'revoked', 'logged_out', 'new', 'stale'].includes(sessionState(row));
        },

        /** The listener refreshes its own profile; nobody else opens its session. */
        canCheck(row) {
            return !!row?.is_authorized
                && !row.is_listening
                && (!row.is_in_flight || row.is_stale);
        },

        canLogout(row) {
            return !!row && (row.is_authorized || row.session_exists)
                && (!row.is_in_flight || row.is_stale);
        },

        canDelete(row) {
            return !!row && !row.is_authorized && (!row.is_in_flight || row.is_stale);
        },

        filterByState(state) {
            this.filters.state = this.filters.state === state ? '' : state;
            this.applyFilters();
        },

        /*
        |----------------------------------------------------------------
        | Details dialog
        |----------------------------------------------------------------
        */

        openDetail(row) {
            this.detail = row;
            this.detailError = null;
            this.detailOpen = true;
        },

        closeDetail() {
            this.detailOpen = false;
        },

        async runDetail(action, request) {
            if (this.detailBusy) {
                return;
            }

            this.detailBusy = action;
            this.detailError = null;

            try {
                const account = await request();

                this.replaceAccount(account);
                this.scheduleListPoll();

                return account;
            } catch (error) {
                this.detailError = error?.message || t.errors.action;

                return null;
            } finally {
                this.detailBusy = null;
            }
        },

        async checkSession(row) {
            const account = await this.runDetail(
                'check',
                () => this.request(this.accountUrl(row.id, '/check')),
            );

            if (account) {
                this.notify(t.messages.check_started);
            }
        },

        async toggleProcess(row, process) {
            const account = await this.runDetail(
                `process-${process.process}`,
                () => this.request(
                    this.accountUrl(row.id, `/processes/${encodeURIComponent(process.process)}`),
                    'PUT',
                    { is_available: !process.is_available },
                ),
            );

            if (account) {
                this.notify(process.is_available ? t.messages.process_disabled : t.messages.process_enabled);
            }
        },

        /*
        |----------------------------------------------------------------
        | Confirmation
        |----------------------------------------------------------------
        */

        askConfirm(action) {
            this.confirmAction = action;
            this.confirmError = null;
            this.detailOpen = false;
            this.confirmOpen = true;
        },

        closeConfirm() {
            if (this.confirmBusy) {
                return;
            }

            this.confirmOpen = false;

            if (this.detail) {
                this.detailOpen = true;
            }
        },

        async confirm() {
            if (this.confirmBusy || !this.detail) {
                return;
            }

            this.confirmBusy = true;
            this.confirmError = null;

            const id = this.detail.id;

            try {
                if (this.confirmAction === 'delete') {
                    await this.request(this.accountUrl(id), 'DELETE');

                    this.detail = null;
                    this.notify(t.messages.deleted);
                } else {
                    const account = await this.request(this.accountUrl(id, '/logout'));

                    this.replaceAccount(account);
                    this.notify(t.messages.logout_started);
                }

                this.confirmOpen = false;
                this.load();
            } catch (error) {
                this.confirmError = error?.message || t.errors.action;
            } finally {
                this.confirmBusy = false;
            }
        },

        /*
        |----------------------------------------------------------------
        | Login wizard
        |----------------------------------------------------------------
        */

        /**
         * Which screen of the wizard the account is on.
         */
        loginStep() {
            const account = this.login.account;

            if (!account) {
                return 'phone';
            }

            if (account.is_authorized) {
                return 'done';
            }

            return {
                sending_code: 'sending',
                verifying: 'verifying',
                awaiting_code: 'code',
                awaiting_password: 'password',
            }[sessionState(account)] ?? 'failed';
        },

        /**
         * done / current / todo for the three dots on top of the wizard
         * (0 phone, 1 code, 2 password).
         */
        stepperState(index) {
            const step = this.loginStep();

            if (step === 'done') {
                return 'done';
            }

            const current = {
                phone: 0,
                sending: 1,
                code: 1,
                password: 2,
                verifying: this.login.lastInput === 'password' ? 2 : 1,
                failed: this.login.lastInput === 'password' ? 2 : 1,
            }[step] ?? 0;

            if (index < current) {
                return 'done';
            }

            return index === current ? 'current' : 'todo';
        },

        loginWaiting() {
            return ['sending', 'verifying'].includes(this.loginStep());
        },

        openLogin(row = null) {
            clearTimeout(this.loginPollTimer);

            /*
             * A login that is over (logged out, failed, stuck) starts again
             * from the phone screen, with the number already filled in.
             */
            this.login = {
                ...emptyLogin,
                account: row && !this.canRestartLogin(row) ? row : null,
                phone: row?.phone || '',
            };

            this.detailOpen = false;
            this.loginOpen = true;

            this.pollLogin();
            this.focusLoginField();
        },

        closeLogin() {
            if (this.login.submitting) {
                return;
            }

            clearTimeout(this.loginPollTimer);

            this.loginOpen = false;

            this.load();
        },

        focusLoginField() {
            this.$nextTick(() => {
                const ref = {
                    phone: 'loginPhone',
                    code: 'loginCode',
                    password: 'loginPassword',
                }[this.loginStep()];

                this.$refs[ref]?.focus({ preventScroll: true });
            });
        },

        pollLogin() {
            clearTimeout(this.loginPollTimer);

            if (!this.loginOpen || !this.login.account || !this.loginWaiting()) {
                return;
            }

            this.loginPollTimer = setTimeout(async () => {
                const before = this.loginStep();

                try {
                    this.replaceAccount(await this.fetchAccount(this.login.account.id));
                } catch (_) {
                    /* A missed beat is fine; the next one will catch up. */
                }

                if (this.loginStep() !== before) {
                    this.onLoginStepChanged();
                }

                this.pollLogin();
            }, LOGIN_POLL_MS);
        },

        onLoginStepChanged() {
            const step = this.loginStep();

            if (step === 'done') {
                this.notify(t.messages.authorized);
            }

            if (step === 'code' || step === 'password') {
                this.focusLoginField();
            }
        },

        loginFieldError(field) {
            const messages = this.login.fieldErrors[field];

            return Array.isArray(messages) ? messages[0] : null;
        },

        async submitLogin(url, body, input) {
            if (this.login.submitting) {
                return;
            }

            this.login.lastInput = input;
            this.login.submitting = true;
            this.login.error = null;
            this.login.fieldErrors = {};

            try {
                const account = await this.request(url, 'POST', body);

                this.login.account = account;
                this.replaceAccount(account);

                /* A code or password is single-use either way. */
                if (input !== 'phone') {
                    this.login[input] = '';
                }

                this.pollLogin();
            } catch (error) {
                if (error.status === 422) {
                    this.login.fieldErrors = error.errors;
                }

                this.login.error = error?.message || t.errors.action;
            } finally {
                this.login.submitting = false;
            }
        },

        sendPhone() {
            return this.submitLogin(this.endpoints.store, { phone: this.login.phone }, 'phone');
        },

        /** Same phone, new code - the only way on after a wrong one. */
        restartLogin() {
            this.login.phone = this.login.account?.phone || this.login.phone;

            return this.sendPhone();
        },

        sendCode() {
            return this.submitLogin(
                this.accountUrl(this.login.account.id, '/code'),
                { code: this.login.code },
                'code',
            );
        },

        sendPassword() {
            return this.submitLogin(
                this.accountUrl(this.login.account.id, '/password'),
                { password: this.login.password },
                'password',
            );
        },
    };
}

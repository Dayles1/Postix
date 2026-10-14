/*
|--------------------------------------------------------------------------
| Contact sync: group members into the accounts' contacts
|--------------------------------------------------------------------------
|
| One page: the groups that are read, how many contacts each account holds,
| and the log. State changes on its own while a run is going, so the summary
| polls the way the watchdog page does.
|
*/

import { requestJson } from './monitoring';
import { EM_DASH, formatDate, formatNumber, formatPhone, formatRelative } from './format';

const POLL_MS = 5000;

const EVENT_TONES = {
    started: 'bg-blue-light-50 text-blue-light-700 ring-1 ring-inset ring-blue-light-600/20 dark:bg-blue-light-500/10 dark:text-blue-light-400',
    members: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-300',
    added: 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400',
    skipped: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-300',
    joined: 'bg-blue-light-50 text-blue-light-700 ring-1 ring-inset ring-blue-light-600/20 dark:bg-blue-light-500/10 dark:text-blue-light-400',
    disabled: 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400',
    failed: 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400',
    flood: 'bg-warning-50 text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400',
    finished: 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400',
};

const notify = (message, success = true) => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, success } }));
};

export function contactSyncPage(config) {
    const t = config.translations;
    const ui = config.ui;

    return {
        t,
        ui,

        endpoints: config.endpoints,

        status: null,

        logs: [],

        logMeta: { current_page: 1, last_page: 1, total: 0 },

        filters: { event: '', account_id: '', group_id: '' },

        loading: false,

        error: null,

        busy: null,

        newLink: '',

        timer: null,

        dash: EM_DASH,

        events: Object.keys(EVENT_TONES),

        init() {
            this.load();
            this.loadLogs();
        },

        destroy() {
            clearTimeout(this.timer);
        },

        async load() {
            clearTimeout(this.timer);

            this.loading = true;

            try {
                const json = await requestJson(this.endpoints.show);

                this.status = json.data;
                this.error = null;
            } catch (error) {
                this.error = error?.message || t.errors.load_failed;
            } finally {
                this.loading = false;

                this.timer = setTimeout(() => {
                    this.load();

                    /* The log only moves while something is running. */
                    if (this.status?.running && this.logMeta.current_page === 1) {
                        this.loadLogs(1);
                    }
                }, POLL_MS);
            }
        },

        async loadLogs(page = 1) {
            const query = new URLSearchParams({ page, per_page: 50 });

            Object.entries(this.filters).forEach(([key, value]) => {
                if (value !== '') {
                    query.set(key, value);
                }
            });

            try {
                const json = await requestJson(`${this.endpoints.logs}?${query}`);

                this.logs = json.data;
                this.logMeta = {
                    current_page: json.current_page,
                    last_page: json.last_page,
                    total: json.total,
                };
            } catch (error) {
                this.error = error?.message || t.errors.load_failed;
            }
        },

        async act(key, url, method = 'POST', body = null) {
            if (this.busy) {
                return false;
            }

            this.busy = key;

            try {
                const json = await requestJson(url, method, body);

                if (json.message) {
                    notify(json.message);
                }

                await this.load();

                return true;
            } catch (error) {
                notify(error?.message || t.errors.action, false);

                return false;
            } finally {
                this.busy = null;
            }
        },

        run(groupId = null) {
            return this.act('run', this.endpoints.run, 'POST', groupId ? { group_id: groupId } : null)
                .then(() => this.loadLogs(1));
        },

        async addGroup() {
            const link = this.newLink.trim();

            if (link === '') {
                return;
            }

            if (await this.act('add', this.endpoints.groups, 'POST', { link })) {
                this.newLink = '';
            }
        },

        enableAccount(account) {
            return this.act(`enable-${account.id}`, `${this.endpoints.accounts}/${account.id}/enable`, 'POST');
        },

        toggleGroup(group) {
            return this.act(`toggle-${group.id}`, `${this.endpoints.groups}/${group.id}`, 'PATCH', {
                is_active: !group.is_active,
            });
        },

        removeGroup(group) {
            if (!window.confirm(t.groups.confirm_delete)) {
                return null;
            }

            return this.act(`delete-${group.id}`, `${this.endpoints.groups}/${group.id}`, 'DELETE');
        },

        /*
        |----------------------------------------------------------------
        | Presentation
        |----------------------------------------------------------------
        */

        eventClass(event) {
            return EVENT_TONES[event] ?? EVENT_TONES.members;
        },

        eventLabel(event) {
            return t.events[event] ?? event;
        },

        logContext(row) {
            const context = row.context ?? {};

            return context.username
                ? `@${context.username}`
                : (context.telegram_user_id ?? '');
        },

        groupName(group) {
            return group?.title || group?.link || EM_DASH;
        },

        number: formatNumber,
        date: formatDate,
        relative: formatRelative,
        phone: formatPhone,
    };
}

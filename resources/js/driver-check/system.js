/*
|--------------------------------------------------------------------------
| How it works
|--------------------------------------------------------------------------
|
| Every independent logic of the Telegram side, which account does it right
| now and whether that account's process is up, and the problems told to the
| developer lately. Read only; one request, refreshed in the background.
|
*/

import { formatDate, formatRelative } from './format';

export function systemPage(config) {
    const t = config.translations;

    return {
        ui: config.ui,

        translations: t,

        endpoints: config.endpoints,

        loading: false,

        error: null,

        logics: [],

        listeners: [],

        alerts: { configured: false, recent: [] },

        init() {
            this.load();

            setInterval(() => this.load(true), 15000);
        },

        async load(silent = false) {
            if (!silent) {
                this.loading = true;
            }

            this.error = null;

            try {
                const response = await fetch(this.endpoints.index, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const json = await response.json();

                this.logics = Array.isArray(json.logics) ? json.logics : [];
                this.listeners = Array.isArray(json.listeners) ? json.listeners : [];
                this.alerts = json.alerts || { configured: false, recent: [] };
            } catch (error) {
                this.error = error?.message || t.errors.load;
            } finally {
                this.loading = false;
            }
        },

        /* The shared empty state offers a filter reset; this page has none. */
        hasActiveFilters() {
            return false;
        },

        resetFilters() {},

        /** The logics an admin can hand to another account. */
        movableCount() {
            return this.logics.filter((logic) => logic.movable).length;
        },

        logicName(logic) {
            return t.logics[logic.key]?.name || logic.key;
        },

        logicWhat(logic) {
            return t.logics[logic.key]?.what || '';
        },

        logicNote(logic) {
            return t.logics[logic.key]?.note || '';
        },

        dotClass(running) {
            return running ? 'bg-success-500' : 'bg-error-500';
        },

        listenerState(row) {
            if (!row.is_enabled) {
                return t.state.off;
            }

            return row.running ? t.state.running : t.state.stopped;
        },

        date(value) {
            return formatDate(value);
        },

        relative(value) {
            return formatRelative(value);
        },
    };
}

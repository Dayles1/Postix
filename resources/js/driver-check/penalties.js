/*
|--------------------------------------------------------------------------
| CRM penalties (client checks)
|--------------------------------------------------------------------------
|
| Read only. The listener forwards a penalty the moment it arrives and
| sends one comment per batch once the bot's burst is over; this page shows
| where each one got to. Failures are retried by the listener itself while
| the penalty is fresh, so there is nothing to press here - what fixes a
| "skipped" row is a Telegram contact on the people pages.
|
*/

import { createListPage } from './list-page';

/*
 * Written out in full: Tailwind only sees literals.
 */
const STATUS_TONES = {
    sent: {
        badge: 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20',
        dot: 'bg-success-500',
    },
    forwarded: {
        badge: 'bg-blue-light-50 text-blue-light-700 ring-1 ring-inset ring-blue-light-600/20 dark:bg-blue-light-500/10 dark:text-blue-light-400 dark:ring-blue-light-500/20',
        dot: 'bg-blue-light-500 animate-pulse',
    },
    pending: {
        badge: 'bg-warning-50 text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20',
        dot: 'bg-warning-500',
    },
    failed: {
        badge: 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400 dark:ring-error-500/20',
        dot: 'bg-error-500',
    },
    skipped: {
        badge: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/10',
        dot: 'bg-gray-400',
    },
};

const LEVEL_TONES = [
    'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300',
    'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
    'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
    'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
];

const ROLE_TONES = {
    operation: 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
    sales: 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-400',
};

/** A list that updates by itself while some penalty is still on its way. */
const POLL_MS = 15000;

export function penaltiesPage(config) {
    const t = config.translations;
    const ui = config.ui;
    const levelNames = Array.isArray(config.levelNames) ? config.levelNames : [];

    const emptyStats = {
        total: 0,
        sent: 0,
        in_progress: 0,
        failed: 0,
        skipped: 0,
        critical: 0,
        people: 0,
        roles: { operation: 0, sales: 0 },
    };

    return {
        ui,

        ...createListPage({
            endpoint: config.endpoints.index,

            translations: t,

            defaults: {
                search: '',
                role: '',
                status: '',
                level: '',
                operation_user_id: '',
                period_from: '',
                period_to: '',
                sort: 'created_at',
                direction: 'desc',
                per_page: 20,
                page: 1,
            },

            onLoaded(json) {
                this.stats = {
                    ...emptyStats,
                    ...(json.stats || {}),
                    roles: { ...emptyStats.roles, ...(json.stats?.roles || {}) },
                };

                if (json.settings) {
                    this.settings = {
                        enabled: !!json.settings.enabled,
                        comments_enabled: !!json.settings.comments_enabled,
                    };
                }

                this.syncDetail();
                this.schedulePoll();
            },
        }),

        endpoints: config.endpoints,

        stats: { ...emptyStats },

        /* The two switches of the flow (changed on the settings page). */
        settings: { enabled: true, comments_enabled: true },


        pollTimer: null,

        detailOpen: false,

        detail: null,

        init() {
            this.initList();
        },

        destroy() {
            clearTimeout(this.pollTimer);
        },

        /**
         * Forwarded rows wait for their batch comment, which the listener
         * sends within a minute or so: keep the page honest without a click.
         */
        schedulePoll() {
            clearTimeout(this.pollTimer);

            if (!this.rows.some((row) => row.status === 'pending' || row.status === 'forwarded')) {
                return;
            }

            this.pollTimer = setTimeout(() => {
                if (!this.loading && !document.hidden) {
                    this.load();
                } else {
                    this.schedulePoll();
                }
            }, POLL_MS);
        },

        /*
        |----------------------------------------------------------------
        | Filters
        |----------------------------------------------------------------
        */

        roleTabs() {
            return [
                { value: '', label: t.tabs.all, count: this.stats.roles.operation + this.stats.roles.sales },
                { value: 'operation', label: t.tabs.operation, count: this.stats.roles.operation },
                { value: 'sales', label: t.tabs.sales, count: this.stats.roles.sales },
            ];
        },

        setRole(role) {
            this.filters.role = role;
            this.applyFilters();
        },

        periodPresets() {
            return [
                { value: 'all', label: t.filters.period_all },
                { value: 'today', label: t.filters.period_today },
                { value: 'week', label: t.filters.period_week },
                { value: 'month', label: t.filters.period_month },
                { value: 'custom', label: t.filters.period_custom },
            ];
        },

        /** Arrived from a person's row: name them, and offer the way back. */
        personFilterName() {
            if (!this.filters.operation_user_id) {
                return null;
            }

            const row = this.rows.find(
                (item) => String(item.person?.id) === String(this.filters.operation_user_id),
            );

            return row?.person?.name ?? `#${this.filters.operation_user_id}`;
        },

        clearPerson() {
            this.filters.operation_user_id = '';
            this.applyFilters();
        },

        /*
        |----------------------------------------------------------------
        | Presentation
        |----------------------------------------------------------------
        */

        statusText(row) {
            return t.statuses[row?.status] ?? row?.status ?? this.dash;
        },

        statusClass(row) {
            return (STATUS_TONES[row?.status] ?? STATUS_TONES.skipped).badge;
        },

        statusDot(row) {
            return (STATUS_TONES[row?.status] ?? STATUS_TONES.skipped).dot;
        },

        reasonText(row) {
            return row?.reason ? (t.reasons[row.reason] ?? row.reason) : '';
        },

        /** Named as on the settings page; a level since removed is just "Level N". */
        levelText(level) {
            return levelNames[Number(level)] ?? t.level_n.replace(':n', level);
        },

        /** Calm to harsh, spread over however many levels are configured. */
        levelClass(level) {
            const top = Math.max(1, levelNames.length - 1);
            const step = Math.round((Math.min(Number(level) || 0, top) / top) * (LEVEL_TONES.length - 1));

            return LEVEL_TONES[step];
        },

        roleText(role) {
            return t.tabs[role] ?? role ?? '';
        },

        roleClass(role) {
            return ROLE_TONES[role] ?? ROLE_TONES.operation;
        },

        /** The person found by name, or the name as the bot wrote it. */
        responsibleName(row) {
            return row?.person?.name || row?.responsible_name || t.table.no_responsible;
        },

        responsibleRole(row) {
            return row?.responsible_role || row?.person?.role || null;
        },

        personUrl(row) {
            if (!row?.person) {
                return null;
            }

            const base = row.person.role === 'sales' ? this.endpoints.sales : this.endpoints.operators;

            return `${base}?search=${encodeURIComponent(row.person.name)}`;
        },

        /*
        |----------------------------------------------------------------
        | Details
        |----------------------------------------------------------------
        */

        openDetail(row) {
            this.detail = row;
            this.detailOpen = true;
        },

        closeDetail() {
            this.detailOpen = false;
        },

        /** After a poll the open dialog follows the fresh copy of its row. */
        syncDetail() {
            if (!this.detail) {
                return;
            }

            const fresh = this.rows.find((row) => row.id === this.detail.id);

            if (fresh) {
                this.detail = fresh;
            }
        },

        metric(row, key) {
            const value = row?.metrics?.[key];

            return value === null || value === undefined ? this.dash : this.number(value);
        },
    };
}

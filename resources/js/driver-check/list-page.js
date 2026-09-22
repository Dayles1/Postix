/*
|--------------------------------------------------------------------------
| Shared list-page behaviour
|--------------------------------------------------------------------------
|
| Operators, operation users, drivers and resolved phones are the same page
| with different columns: filter -> fetch -> paginate -> mirror the state
| into the URL. This factory owns that cycle once.
|
| A page adds its own columns and any extra state on top via object spread:
|
|     Alpine.data('dcDrivers', () => ({ ...createListPage({...}), ... }))
|
*/

import {
    EM_DASH,
    formatCompact,
    formatDate,
    formatNumber,
    formatPercent,
    formatPhone,
    formatRelative,
    formatScore,
    initials,
    telegramHandle,
    telegramUrl,
} from './format';

import { statusLabel, statusTone, statusType } from './status';

/**
 * Filters the user never thinks of as filters - they must not count towards
 * the "3 filters active" badge, and Reset must not be offered for them.
 */
const PRESENTATION_KEYS = ['page', 'per_page', 'sort', 'direction'];

const isoDay = (date) => {
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
};

/**
 * Concrete dates for a named period, so the server never has to know what
 * "last week" means and the export link can reuse the same range.
 */
export function periodRange(preset) {
    const today = new Date();

    if (preset === 'today') {
        return { from: isoDay(today), to: isoDay(today) };
    }

    if (preset === 'week' || preset === 'last_week') {
        const from = new Date(today);
        from.setDate(from.getDate() - 6);

        return { from: isoDay(from), to: isoDay(today) };
    }

    if (preset === 'month' || preset === 'last_month') {
        const from = new Date(today);
        from.setDate(from.getDate() - 29);

        return { from: isoDay(from), to: isoDay(today) };
    }

    return { from: '', to: '' };
}

export function createListPage({
    endpoint,
    defaults,
    translations = {},
    onLoaded = null,
}) {
    return {
        endpoint,

        translations,

        defaults: { ...defaults },

        filters: { ...defaults },

        rows: [],

        loading: false,

        error: null,

        /** Advanced filters stay folded away until asked for. */
        advancedOpen: false,

        periodPreset: 'all',

        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: Number(defaults.per_page ?? 10),
            total: 0,
            from: 0,
            to: 0,
        },

        /** Lets a newer request cancel the one it replaces. */
        pendingRequest: null,

        /*
        |----------------------------------------------------------------
        | Lifecycle
        |----------------------------------------------------------------
        */

        initList() {
            this.readUrl();
            this.load();
        },

        /*
        |----------------------------------------------------------------
        | Query
        |----------------------------------------------------------------
        */

        buildParams(includePage = true) {
            const params = new URLSearchParams();

            Object.entries(this.filters).forEach(([key, value]) => {
                if (key === 'page') {
                    return;
                }

                if (value === true) {
                    params.set(key, '1');

                    return;
                }

                if (
                    value === false
                    || value === ''
                    || value === null
                    || value === undefined
                ) {
                    return;
                }

                params.set(key, String(value));
            });

            if (includePage && Number(this.filters.page) > 1) {
                params.set('page', String(this.filters.page));
            }

            return params;
        },

        /**
         * The export endpoints take from/to; the list API takes
         * period_from/period_to. Same range, two spellings.
         */
        exportParams(extra = {}) {
            const params = this.buildParams(false);

            if (params.has('period_from')) {
                params.set('from', params.get('period_from'));
                params.delete('period_from');
            }

            if (params.has('period_to')) {
                params.set('to', params.get('period_to'));
                params.delete('period_to');
            }

            params.delete('per_page');

            Object.entries(extra).forEach(([key, value]) => {
                params.set(key, String(value));
            });

            return params;
        },

        async load() {
            if (this.pendingRequest) {
                this.pendingRequest.abort();
            }

            const controller = new AbortController();

            this.pendingRequest = controller;
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch(
                    `${this.endpoint}?${this.buildParams().toString()}`,
                    {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: controller.signal,
                    },
                );

                const json = await this.readJson(response);

                this.rows = Array.isArray(json.data) ? json.data : [];

                this.setPagination(json);

                if (typeof onLoaded === 'function') {
                    onLoaded.call(this, json);
                }

                this.syncUrl();
            } catch (error) {
                /* A cancelled request is not a failure the user should see. */
                if (error?.name === 'AbortError') {
                    return;
                }

                this.rows = [];

                this.error = error?.message
                    || this.translations?.errors?.load_failed
                    || this.translations?.errors?.unknown
                    || 'Error';
            } finally {
                if (this.pendingRequest === controller) {
                    this.pendingRequest = null;
                    this.loading = false;
                }
            }
        },

        async readJson(response) {
            let json = {};

            try {
                json = await response.json();
            } catch (_) {
                json = {};
            }

            if (!response.ok) {
                throw new Error(json.message || `HTTP ${response.status}`);
            }

            return json;
        },

        setPagination(json) {
            const meta = json.meta || {};

            this.pagination = {
                current_page: Number(meta.current_page ?? this.filters.page ?? 1),
                last_page: Number(meta.last_page ?? 1),
                per_page: Number(meta.per_page ?? this.filters.per_page ?? 10),
                total: Number(meta.total ?? 0),
                from: Number(meta.from ?? 0),
                to: Number(meta.to ?? 0),
            };

            this.filters.page = this.pagination.current_page;
        },

        /*
        |----------------------------------------------------------------
        | Filter actions
        |----------------------------------------------------------------
        */

        applyFilters() {
            this.filters.page = 1;
            this.load();
        },

        resetFilters() {
            this.filters = { ...this.defaults };
            this.periodPreset = 'all';
            this.load();
        },

        clearSearch() {
            this.filters.search = '';
            this.applyFilters();
        },

        toggleDirection() {
            this.filters.direction = this.filters.direction === 'asc' ? 'desc' : 'asc';
            this.applyFilters();
        },

        setPeriod(preset) {
            this.periodPreset = preset;

            if (preset === 'custom') {
                /* Keep whatever dates are already typed, just switch mode. */
                return;
            }

            const { from, to } = periodRange(preset);

            this.filters.period_from = from;
            this.filters.period_to = to;

            this.applyFilters();
        },

        applyCustomPeriod() {
            const from = this.filters.period_from;
            const to = this.filters.period_to;

            /* A reversed range is a typo, not a request for zero rows. */
            if (from && to && from > to) {
                this.filters.period_from = to;
                this.filters.period_to = from;
            }

            this.periodPreset = 'custom';

            this.applyFilters();
        },

        /**
         * How many real filters are narrowing the list right now - drives the
         * count badge on the mobile "Filters" button.
         */
        activeFilterCount() {
            return Object.keys(this.defaults).reduce((count, key) => {
                if (PRESENTATION_KEYS.includes(key)) {
                    return count;
                }

                const value = this.filters[key];
                const fallback = this.defaults[key];

                if (value === fallback) {
                    return count;
                }

                if (value === '' || value === null || value === undefined || value === false) {
                    return count;
                }

                return count + 1;
            }, 0);
        },

        hasActiveFilters() {
            return this.activeFilterCount() > 0;
        },

        isDefaultSort() {
            return this.filters.sort === this.defaults.sort
                && this.filters.direction === this.defaults.direction;
        },

        /*
        |----------------------------------------------------------------
        | Pagination
        |----------------------------------------------------------------
        */

        goToPage(page) {
            page = Number(page);

            if (
                !Number.isFinite(page)
                || page < 1
                || page > this.pagination.last_page
                || page === this.pagination.current_page
                || this.loading
            ) {
                return;
            }

            this.filters.page = page;

            this.load();

            /*
             * Paging on a phone otherwise leaves you looking at the bottom of
             * a brand new page.
             */
            this.$nextTick(() => {
                this.$refs.listTop?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        },

        changePerPage() {
            this.filters.page = 1;
            this.load();
        },

        visiblePages() {
            const current = Number(this.pagination.current_page);
            const last = Number(this.pagination.last_page);

            if (last <= 7) {
                return Array.from({ length: last }, (_, index) => index + 1);
            }

            const pages = [1];

            if (current > 4) {
                pages.push('start-gap');
            }

            const start = Math.max(2, current - 1);
            const end = Math.min(last - 1, current + 1);

            for (let page = start; page <= end; page += 1) {
                pages.push(page);
            }

            if (current < last - 3) {
                pages.push('end-gap');
            }

            pages.push(last);

            return [...new Set(pages)];
        },

        isGap(page) {
            return page === 'start-gap' || page === 'end-gap';
        },

        /*
        |----------------------------------------------------------------
        | URL <-> state
        |----------------------------------------------------------------
        */

        readUrl() {
            const params = new URLSearchParams(window.location.search);

            Object.keys(this.defaults).forEach((key) => {
                const fallback = this.defaults[key];
                const raw = params.get(key);

                if (raw === null) {
                    this.filters[key] = fallback;

                    return;
                }

                if (typeof fallback === 'boolean') {
                    this.filters[key] = raw === '1' || raw === 'true';

                    return;
                }

                if (typeof fallback === 'number') {
                    const number = Number(raw);

                    this.filters[key] = Number.isFinite(number) && number > 0
                        ? number
                        : fallback;

                    return;
                }

                this.filters[key] = raw;
            });

            if ('period_from' in this.defaults) {
                this.periodPreset = (this.filters.period_from || this.filters.period_to)
                    ? 'custom'
                    : 'all';
            }

            /* Nothing is more annoying than a shared link that hides its own filters. */
            this.advancedOpen = this.activeFilterCount() > (this.filters.search ? 1 : 0);
        },

        syncUrl() {
            const query = this.buildParams().toString();

            const next = window.location.pathname + (query ? `?${query}` : '');

            if (next === window.location.pathname + window.location.search) {
                return;
            }

            window.history.replaceState({}, '', next);
        },

        /*
        |----------------------------------------------------------------
        | Presentation helpers
        |----------------------------------------------------------------
        */

        dash: EM_DASH,

        number: formatNumber,
        compact: formatCompact,
        percent: formatPercent,
        score: formatScore,
        date: formatDate,
        relative: formatRelative,
        phone: formatPhone,
        initials,
        handle: telegramHandle,
        telegramUrl,

        statusType,

        statusTone(value, variant = 'badge') {
            return statusTone(value, variant);
        },

        /**
         * Pages pass their own label bundle, because "Confirmed" lives under
         * a different key on every one of them.
         */
        statusLabel(value, labels) {
            return statusLabel(value, labels ?? this.statusLabels ?? {});
        },

        /*
        |----------------------------------------------------------------
        | Small interactions
        |----------------------------------------------------------------
        */

        notify(message, success = true) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message, success },
            }));
        },

        async copy(value, message = null) {
            const text = String(value ?? '').trim();

            if (text === '') {
                return;
            }

            try {
                await navigator.clipboard.writeText(text);
            } catch (_) {
                /* Clipboard API needs a secure context; fall back to a textarea. */
                const area = document.createElement('textarea');

                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';

                document.body.appendChild(area);
                area.select();

                try {
                    document.execCommand('copy');
                } catch (__) {
                    document.body.removeChild(area);

                    return;
                }

                document.body.removeChild(area);
            }

            this.notify(message || text);
        },
    };
}

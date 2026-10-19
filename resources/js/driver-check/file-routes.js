/*
|--------------------------------------------------------------------------
| File routes
|--------------------------------------------------------------------------
|
| The notifications bot's morning files: one row per kind (found by a
| keyword in the caption or file name) with the groups it is forwarded
| to, and the journal of the last forwards under it. A handful of rows,
| so no paging: everything comes in one request.
|
*/

import { formatDate, formatNumber, formatRelative } from './format';

const emptyForm = {
    id: null,
    name: '',
    keywords: '',
    targets: '',
    listener_key: '',
    is_active: true,
};

export function fileRoutesPage(config) {
    const t = config.translations;

    return {
        ui: config.ui,

        translations: t,

        endpoints: config.endpoints,

        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        loading: false,

        error: null,

        rows: [],

        recent: [],

        sources: [],

        /* The accounts a kind can go out from: [{key, name}] - Driver Check ('_dc') and the listeners. */
        accounts: [],

        formOpen: false,

        saving: false,

        formError: null,

        formErrors: {},

        confirmOpen: false,

        deleting: false,

        confirmError: null,

        form: { ...emptyForm },

        init() {
            this.load();
        },

        async load() {
            this.loading = true;
            this.error = null;

            try {
                const json = await this.request(this.endpoints.index);

                this.rows = Array.isArray(json.routes) ? json.routes : [];
                this.recent = Array.isArray(json.recent) ? json.recent : [];
                this.sources = Array.isArray(json.source_chat_ids) ? json.source_chat_ids : [];
                this.accounts = Array.isArray(json.accounts) ? json.accounts : [];
            } catch (error) {
                this.error = error?.message || t.errors.load;
            } finally {
                this.loading = false;
            }
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            let json = {};

            try {
                json = await response.json();
            } catch (_) {
                json = {};
            }

            if (response.status === 422) {
                const error = new Error(json.message || t.errors.save);
                error.errors = json.errors || {};

                throw error;
            }

            if (!response.ok) {
                throw new Error(json.message || `HTTP ${response.status}`);
            }

            return json;
        },

        /*
        |----------------------------------------------------------------
        | Display
        |----------------------------------------------------------------
        */

        number(value) {
            return formatNumber(value);
        },

        date(value) {
            return formatDate(value);
        },

        relative(value) {
            return formatRelative(value);
        },

        /* The shared empty state offers a filter reset; this page has none. */
        hasActiveFilters() {
            return false;
        },

        resetFilters() {},

        keywordList(row) {
            return String(row.keywords || '')
                .split(',')
                .map((keyword) => keyword.trim())
                .filter(Boolean);
        },

        /** The account that forwards this kind now: its own choice, or the owner of the files. */
        senderName(row) {
            const key = row.sender || '_dc';

            return this.accounts.find((account) => account.key === key)?.name || row.sender || 'Driver Check';
        },

        /** Set on the kind itself, or following "Files from bots". */
        senderOwn(row) {
            return !!row.listener_key;
        },

        forwardFile(item) {
            return item.caption || item.file_name || '—';
        },

        forwardStatusClass(status) {
            return {
                sent: 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20',
                failed: 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400 dark:ring-error-500/20',
                unmatched: 'bg-warning-50 text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20',
            }[status] || 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300';
        },

        rowStatusClass(row) {
            return row.is_active
                ? 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20'
                : 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/10';
        },

        /*
        |----------------------------------------------------------------
        | Form
        |----------------------------------------------------------------
        */

        openCreate() {
            this.form = { ...emptyForm };

            this.openForm();
        },

        openEdit(row) {
            this.form = {
                id: row.id,
                name: row.name || '',
                keywords: row.keywords || '',
                targets: (row.target_chat_ids || []).join('\n'),
                listener_key: row.listener_key || '',
                is_active: !!row.is_active,
            };

            this.openForm();
        },

        openForm() {
            this.formError = null;
            this.formErrors = {};
            this.formOpen = true;

            this.$nextTick(() => {
                this.$refs.firstField?.focus({ preventScroll: true });
            });
        },

        closeForm() {
            if (this.saving) {
                return;
            }

            this.formOpen = false;
        },

        /** The targets come back as target_chat_ids.0, .1 ... */
        fieldError(field) {
            const key = Object.keys(this.formErrors).find(
                (name) => name === field || name.startsWith(field + '.'),
            );

            const messages = key ? this.formErrors[key] : null;

            return Array.isArray(messages) ? messages[0] : null;
        },

        async save() {
            if (this.saving) {
                return;
            }

            this.saving = true;
            this.formError = null;
            this.formErrors = {};

            const editing = !!this.form.id;

            try {
                await this.request(
                    editing ? `${this.endpoints.base}/${this.form.id}` : this.endpoints.store,
                    {
                        method: editing ? 'PUT' : 'POST',
                        body: JSON.stringify({
                            name: this.form.name,
                            keywords: this.form.keywords,
                            target_chat_ids: this.form.targets,
                            listener_key: this.form.listener_key || null,
                            is_active: this.form.is_active,
                        }),
                    },
                );

                this.formOpen = false;

                this.notify(editing ? t.messages.updated : t.messages.created);

                this.load();
            } catch (error) {
                this.formErrors = error?.errors || {};
                this.formError = error?.message || t.errors.save;
            } finally {
                this.saving = false;
            }
        },

        /*
        |----------------------------------------------------------------
        | Removal: the edit form steps aside for the confirmation.
        |----------------------------------------------------------------
        */

        askDelete() {
            if (this.saving || !this.form.id) {
                return;
            }

            this.confirmError = null;
            this.formOpen = false;
            this.confirmOpen = true;
        },

        closeConfirm() {
            if (this.deleting) {
                return;
            }

            this.confirmOpen = false;

            if (this.form.id) {
                this.openForm();
            }
        },

        async destroy() {
            if (this.deleting || !this.form.id) {
                return;
            }

            this.deleting = true;
            this.confirmError = null;

            try {
                await this.request(`${this.endpoints.base}/${this.form.id}`, { method: 'DELETE' });

                this.confirmOpen = false;
                this.form = { ...emptyForm };

                this.notify(t.messages.deleted);

                this.load();
            } catch (error) {
                this.confirmError = error?.message || t.errors.delete;
            } finally {
                this.deleting = false;
            }
        },

        notify(message, success = true) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message, success },
            }));
        },
    };
}

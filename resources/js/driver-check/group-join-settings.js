/*
|--------------------------------------------------------------------------
| Group join: the settings page
|--------------------------------------------------------------------------
|
| On or off, which accounts join (each gets its own worker under the group
| join watchdog), what a group has to be, how fast, how long after a flood,
| how many people are read. Read and written through
| GroupJoinSettingsController; the workers read them every round, so a
| change applies within a minute.
|
*/

import { requestJson } from './monitoring';

const notify = (message, success = true) => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, success } }));
};

/** The number fields; the form hands them back as strings. */
const NUMBERS = [
    'min_members',
    'min_messages_week',
    'max_silence_hours',
    'joins_per_day',
    'accounts_per_group',
    'max_groups_per_account',
    'join_delay_min',
    'join_delay_max',
    'flood_extra_min',
    'flood_extra_max',
    'search_every_hours',
    'recheck_days',
    'max_members_read',
    'members_every_days',
];

const FIELDS = ['enabled', 'read_members', 'accounts', ...NUMBERS];

export function groupJoinSettingsPage(config) {
    const s = config.translations;

    return {
        s,

        endpoint: config.endpoint,

        form: null,

        stored: null,

        limits: {},

        accounts: [],

        savedInPanel: false,

        loading: false,

        busy: false,

        error: null,

        init() {
            this.load();
        },

        async load() {
            this.loading = true;
            this.error = null;

            try {
                this.apply(await requestJson(this.endpoint));
            } catch (error) {
                this.error = error?.message || s.load_failed;
            } finally {
                this.loading = false;
            }
        },

        apply(json) {
            this.stored = JSON.parse(JSON.stringify(json.data));
            this.form = JSON.parse(JSON.stringify(json.data));
            this.limits = json.limits ?? {};
            this.accounts = json.accounts ?? [];
            this.savedInPanel = !!json.saved;
        },

        get dirty() {
            return this.form !== null
                && FIELDS.some((key) => JSON.stringify(this.normal(this.form[key])) !== JSON.stringify(this.normal(this.stored[key])));
        },

        normal(value) {
            return Array.isArray(value) ? [...value].map(String).sort() : String(value);
        },

        /** The listener accounts can never be marked: their sessions are the listeners'. */
        isMarked(account) {
            return !account.is_primary && this.form.accounts.includes(account.id);
        },

        toggleMarked(account) {
            if (account.is_primary) {
                return;
            }

            const set = new Set(this.form.accounts);

            set.has(account.id) ? set.delete(account.id) : set.add(account.id);

            this.form.accounts = [...set].sort((a, b) => a - b);
        },

        markedCount() {
            return this.accounts.filter((account) => this.isMarked(account)).length;
        },

        /** Roughly how many groups a day all the marked accounts together join. */
        perDay() {
            return this.markedCount() * Number(this.form.joins_per_day || 0);
        },

        async save() {
            if (this.busy) {
                return;
            }

            this.busy = true;

            const body = { ...this.form };

            NUMBERS.forEach((key) => {
                body[key] = Number(body[key]);
            });

            try {
                const json = await requestJson(this.endpoint, 'PUT', body);

                this.apply(json);
                notify(json.message);
            } catch (error) {
                notify(error?.message || s.load_failed, false);
            } finally {
                this.busy = false;
            }
        },

        async reset() {
            if (this.busy || !window.confirm(s.reset_confirm)) {
                return;
            }

            this.busy = true;

            try {
                const json = await requestJson(this.endpoint, 'DELETE');

                this.apply(json);
                notify(json.message);
            } catch (error) {
                notify(error?.message || s.load_failed, false);
            } finally {
                this.busy = false;
            }
        },
    };
}

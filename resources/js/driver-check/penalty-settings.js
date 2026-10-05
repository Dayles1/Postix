/*
|--------------------------------------------------------------------------
| Penalty settings
|--------------------------------------------------------------------------
|
| One form over the whole rule set (ClientCheckRules): the levels, what
| reaches each one, their phrases and the timings. Edited locally, saved in
| one go from the bar at the bottom; the two delivery switches beside it
| save on their own, at once, because they are the "stop now" buttons.
|
*/

import { escapeHtml, telegramHtml } from './telegram-html';
import { formatNumber } from './format';

const CONDITIONS = ['repeat_from', 'hour', 'today', 'week', 'repeat_within'];

/*
 * Written out in full: Tailwind only sees literals. Calm to harsh, spread
 * over however many levels there are.
 */
const TONES = [
    {
        badge: 'bg-gray-100 text-gray-700 dark:bg-white/[0.08] dark:text-gray-200',
        rail: 'bg-gray-300 dark:bg-gray-600',
    },
    {
        badge: 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
        rail: 'bg-warning-400',
    },
    {
        badge: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
        rail: 'bg-orange-500',
    },
    {
        badge: 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
        rail: 'bg-error-500',
    },
];

let keySeed = 0;

const nextKey = () => {
    keySeed += 1;

    return `k${keySeed}`;
};

const toNumber = (value) => {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? Math.round(number) : null;
};

/** Server shape -> form shape: phrases get stable keys for x-for. */
function toForm(rules) {
    return {
        levels: (rules.levels || []).map((level) => ({
            key: nextKey(),
            /* Folded by default: the ladder reads at a glance, one level opens to edit. */
            open: false,
            name: level.name ?? '',
            ...Object.fromEntries(CONDITIONS.map((c) => [c, level[c] ?? ''])),
            phrases: (level.phrases || []).map((text) => ({ key: nextKey(), text })),
        })),
        batch_quiet_seconds: rules.batch_quiet_seconds ?? 20,
        history_days: rules.history_days ?? 7,
        max_attempts: rules.max_attempts ?? 3,
        retry_minutes: rules.retry_minutes ?? 30,
        batch_line: rules.batch_line ?? '',
    };
}

/** Form shape -> what the API takes. */
function toPayload(form) {
    return {
        levels: form.levels.map((level, index) => ({
            name: String(level.name || '').trim() || null,
            ...Object.fromEntries(CONDITIONS.map((c) => [c, index === 0 ? null : toNumber(level[c])])),
            phrases: level.phrases.map((p) => String(p.text || '').trim()).filter((p) => p !== ''),
        })),
        batch_quiet_seconds: toNumber(form.batch_quiet_seconds),
        history_days: toNumber(form.history_days),
        max_attempts: toNumber(form.max_attempts),
        retry_minutes: toNumber(form.retry_minutes),
        batch_line: String(form.batch_line || '').trim(),
    };
}

export function penaltySettingsPage(config) {
    const t = config.translations;
    const ui = config.ui;

    return {
        ui,

        translations: t,

        endpoints: config.endpoints,

        maxLevels: config.maxLevels,

        placeholders: config.placeholders,

        conditions: CONDITIONS,

        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        loading: true,

        loadError: null,

        form: toForm({ levels: [] }),

        /** What the server holds, to tell a change from a no-op. */
        snapshot: '',

        customised: false,

        saving: false,

        resetArmed: false,

        errors: {},

        formError: null,

        /** Where a placeholder chip inserts: the field edited last. */
        target: { level: 0, phrase: 0 },

        settings: { ...config.settings },

        settingsBusy: null,

        init() {
            this.load();

            window.addEventListener('beforeunload', (event) => {
                if (this.dirty()) {
                    event.preventDefault();
                    event.returnValue = t.messages.leave;
                }
            });
        },

        /*
        |----------------------------------------------------------------
        | Requests
        |----------------------------------------------------------------
        */

        async send(url, method, body = null) {
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
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

            return json;
        },

        accept(json) {
            this.form = toForm(json.data || {});
            this.snapshot = JSON.stringify(toPayload(this.form));
            this.customised = !!json.customised;
            this.errors = {};
            this.formError = null;
            this.resetArmed = false;
        },

        async load() {
            this.loading = true;
            this.loadError = null;

            try {
                this.accept(await this.send(this.endpoints.rules, 'GET'));
            } catch (error) {
                this.loadError = error?.message || t.messages.failed;
            } finally {
                this.loading = false;
            }
        },

        dirty() {
            return !this.loading && JSON.stringify(toPayload(this.form)) !== this.snapshot;
        },

        async save() {
            if (this.saving || !this.dirty()) {
                return;
            }

            this.saving = true;
            this.errors = {};
            this.formError = null;

            try {
                const json = await this.send(this.endpoints.rules, 'PUT', toPayload(this.form));

                this.accept(json);
                this.notify(json.message || t.messages.saved);
            } catch (error) {
                if (error.status === 422) {
                    this.errors = error.errors;
                    this.formError = t.validation.fix;

                    /* Unfold whatever the server pointed at. */
                    Object.keys(this.errors).forEach((key) => {
                        const match = key.match(/^levels\.(\d+)\./);

                        if (match && this.form.levels[Number(match[1])]) {
                            this.form.levels[Number(match[1])].open = true;
                        }
                    });
                } else {
                    this.formError = error?.message || t.messages.failed;
                }
            } finally {
                this.saving = false;
            }
        },

        discard() {
            this.form = toForm(JSON.parse(this.snapshot || '{"levels":[]}'));
            this.errors = {};
            this.formError = null;
        },

        async reset() {
            if (!this.resetArmed) {
                this.resetArmed = true;

                return;
            }

            this.saving = true;

            try {
                const json = await this.send(this.endpoints.rules, 'DELETE');

                this.accept(json);
                this.notify(json.message || t.messages.reset);
            } catch (error) {
                this.formError = error?.message || t.messages.failed;
            } finally {
                this.saving = false;
                this.resetArmed = false;
            }
        },

        async toggleSetting(key) {
            if (this.settingsBusy) {
                return;
            }

            const previous = this.settings[key];

            this.settingsBusy = key;
            this.settings[key] = !previous;

            try {
                const json = await this.send(this.endpoints.settings, 'PUT', { [key]: !previous });

                this.settings = {
                    enabled: !!json.data?.enabled,
                    comments_enabled: !!json.data?.comments_enabled,
                };

                this.notify(json.message || config.settingsSaved);
            } catch (error) {
                this.settings[key] = previous;
                this.notify(error?.message || t.messages.failed, false);
            } finally {
                this.settingsBusy = null;
            }
        },

        notify(message, success = true) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, success } }));
        },

        /*
        |----------------------------------------------------------------
        | Levels and phrases
        |----------------------------------------------------------------
        */

        addLevel() {
            if (this.form.levels.length >= this.maxLevels) {
                return;
            }

            const previous = this.form.levels[this.form.levels.length - 1];

            /*
             * A step up from the level below, so a new level is reachable
             * from the first save.
             */
            const step = (value, by) => (toNumber(value) ? toNumber(value) + by : '');

            this.form.levels.push({
                key: nextKey(),
                open: true,
                name: '',
                repeat_from: step(previous?.repeat_from, 2) || (this.form.levels.length + 1),
                hour: '',
                today: step(previous?.today, 2),
                week: '',
                repeat_within: '',
                phrases: [{ key: nextKey(), text: '' }],
            });

            this.$nextTick(() => {
                this.$refs.levelsEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' });
            });
        },

        toggleLevel(index) {
            const level = this.form.levels[index];

            level.open = !level.open;
        },

        /** For a folded card: how many phrases, and the first one. */
        phraseCount(index) {
            return this.form.levels[index].phrases.filter((p) => String(p.text || '').trim() !== '').length;
        },

        firstPhrase(index) {
            const first = this.form.levels[index].phrases.find((p) => String(p.text || '').trim() !== '');

            return first ? this.preview(first.text, index) : '';
        },

        removeLevel(index) {
            if (index === 0) {
                return;
            }

            this.form.levels.splice(index, 1);
            this.target = { level: 0, phrase: 0 };
        },

        addPhrase(index) {
            const level = this.form.levels[index];

            level.phrases.push({ key: nextKey(), text: '' });
            this.target = { level: index, phrase: level.phrases.length - 1 };

            this.$nextTick(() => {
                document.getElementById(`phrase-${index}-${level.phrases.length - 1}`)?.focus();
            });
        },

        removePhrase(index, phrase) {
            this.form.levels[index].phrases.splice(phrase, 1);
            this.target = { level: index, phrase: Math.max(0, phrase - 1) };
        },

        focusPhrase(index, phrase) {
            this.target = { level: index, phrase };
        },

        focusBatchLine() {
            this.target = { level: null, phrase: 'batch_line' };
        },

        /**
         * Puts {name} where the cursor is in the field edited last.
         */
        insertPlaceholder(name) {
            const token = `{${name}}`;

            const isBatchLine = this.target.phrase === 'batch_line';

            const id = isBatchLine
                ? 'batch-line'
                : `phrase-${this.target.level}-${this.target.phrase}`;

            const field = document.getElementById(id);

            const read = () => (isBatchLine
                ? this.form.batch_line
                : this.form.levels[this.target.level]?.phrases[this.target.phrase]?.text);

            const write = (value) => {
                if (isBatchLine) {
                    this.form.batch_line = value;
                } else if (this.form.levels[this.target.level]?.phrases[this.target.phrase]) {
                    this.form.levels[this.target.level].phrases[this.target.phrase].text = value;
                }
            };

            const text = String(read() ?? '');
            const start = field ? field.selectionStart : text.length;
            const end = field ? field.selectionEnd : text.length;

            write(text.slice(0, start) + token + text.slice(end));

            this.$nextTick(() => {
                if (field) {
                    field.focus();
                    field.setSelectionRange(start + token.length, start + token.length);
                }
            });
        },

        /*
        |----------------------------------------------------------------
        | Presentation
        |----------------------------------------------------------------
        */

        number: formatNumber,

        tone(index) {
            const top = this.form.levels.length - 1;

            const step = top <= 0 ? 0 : Math.round((index / top) * (TONES.length - 1));

            return TONES[Math.max(0, Math.min(TONES.length - 1, step))];
        },

        levelTitle(index) {
            return t.level.title.replace(':n', index);
        },

        levelName(index) {
            const name = String(this.form.levels[index]?.name || '').trim();

            if (name !== '') {
                return name;
            }

            return config.defaultNames[index] ?? (index === 0 ? t.level.base : '');
        },

        /** "повтор №4+ или 5+ сегодня" - the conditions in one line. */
        summary(index) {
            if (index === 0) {
                return t.level.always;
            }

            const level = this.form.levels[index];

            const parts = CONDITIONS
                .filter((c) => toNumber(level[c]) !== null)
                .map((c) => t.conditions[`summary_${c}`].replace(':value', toNumber(level[c])));

            return parts.length > 0 ? parts.join(` ${t.conditions.or} `) : t.level.never;
        },

        reachable(index) {
            return index === 0 || CONDITIONS.some((c) => toNumber(this.form.levels[index][c]) !== null);
        },

        /** A phrase as a person at this level would get it. */
        preview(text, index) {
            const level = this.form.levels[index] || {};

            const sample = {
                name: 'PULATOV AFZAL',
                request: 'TLS04834',
                repeat_number: toNumber(level.repeat_from) ?? 1,
                batch_count: 3,
                hour_count: toNumber(level.hour) ?? 1,
                today_count: toNumber(level.today) ?? 2,
                week_count: toNumber(level.week) ?? 4,
            };

            const filled = String(text || '').replace(
                /\{([a-z_]+)\}/g,
                (match, key) => (key in sample ? escapeHtml(sample[key]) : match),
            );

            return telegramHtml(filled);
        },

        fieldError(path) {
            const messages = this.errors[path];

            return Array.isArray(messages) ? messages[0] : null;
        },

        levelErrors(index) {
            return Object.keys(this.errors)
                .filter((key) => key === `levels.${index}.conditions` || key === `levels.${index}.phrases`)
                .map((key) => this.errors[key][0]);
        },
    };
}

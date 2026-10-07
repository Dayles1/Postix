/*
|--------------------------------------------------------------------------
| Penalty settings
|--------------------------------------------------------------------------
|
| One form over the whole rule set (ClientCheckRules): a level per penalty
| number (the bot's own "№N"), each with four phrase sets - Uzbek and
| Russian, plain and respectful. One language is edited at a time, plain
| and respectful side by side. Edited locally, saved in one go from the
| bar at the bottom; the two delivery switches save on their own, at once,
| because they are the "stop now" buttons.
|
*/

import { escapeHtml, telegramHtml } from './telegram-html';
import { formatNumber } from './format';

const LANGUAGES = ['uz', 'ru'];

const TONES = ['plain', 'respectful'];

const ROLES = ['operation', 'sales'];

/** What a level sends: the forward and a comment, the forward only, nothing. */
const MODES = ['all', 'forward', 'off'];

/*
 * Written out in full: Tailwind only sees literals. Calm to harsh, spread
 * over however many levels there are.
 */
const LEVEL_TONES = [
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

/** What a preview fills the placeholders with, in each language. */
const SAMPLE = {
    uz: { status_limit: '2 soat', time_in_status: '3 soat 8 daqiqa', address: 'Ali aka' },
    ru: { status_limit: '2 ч', time_in_status: '3 ч 8 мин', address: 'Али ака' },
};

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

const emptySets = () => Object.fromEntries(
    LANGUAGES.map((lang) => [lang, Object.fromEntries(TONES.map((tone) => [tone, []]))]),
);

/** One level, server shape -> form shape: phrases get stable keys for x-for. */
function levelToForm(level, index) {
    const sets = emptySets();

    LANGUAGES.forEach((lang) => TONES.forEach((tone) => {
        sets[lang][tone] = (level.phrases?.[lang]?.[tone] || []).map((text) => ({ key: nextKey(), text }));
    }));

    return {
        key: nextKey(),
        /* Folded by default: the ladder reads at a glance, one level opens to edit. */
        open: false,
        name: level.name ?? '',
        from: index === 0 ? 1 : (level.from ?? ''),
        mode: MODES.includes(level.mode) ? level.mode : 'all',
        phrases: sets,
    };
}

/** One level, form shape -> what the API takes. */
function levelToPayload(level, index) {
    return {
        name: String(level.name || '').trim() || null,
        from: index === 0 ? 1 : toNumber(level.from),
        mode: MODES.includes(level.mode) ? level.mode : 'all',
        phrases: Object.fromEntries(LANGUAGES.map((lang) => [
            lang,
            Object.fromEntries(TONES.map((tone) => [
                tone,
                level.phrases[lang][tone].map((p) => String(p.text || '').trim()).filter((p) => p !== ''),
            ])),
        ])),
    };
}

/**
 * Server shape -> form shape. A ladder per role; rules saved before the
 * roles were split (one `levels` list) give both roles a copy.
 */
function toForm(rules) {
    return {
        roles: Object.fromEntries(ROLES.map((role) => [role, {
            levels: (rules.roles?.[role]?.levels ?? rules.levels ?? []).map(levelToForm),
        }])),
        batch_quiet_seconds: rules.batch_quiet_seconds ?? 5,
        max_attempts: rules.max_attempts ?? 3,
        retry_minutes: rules.retry_minutes ?? 30,
        /* '' and '' = no limit; rules saved before hours existed come back with the default. */
        work_from: rules.working_hours ? (rules.working_hours.from ?? '') : '09:00',
        work_to: rules.working_hours ? (rules.working_hours.to ?? '') : '18:00',
    };
}

/** Form shape -> what the API takes. */
function toPayload(form) {
    return {
        roles: Object.fromEntries(ROLES.map((role) => [role, {
            levels: form.roles[role].levels.map(levelToPayload),
        }])),
        batch_quiet_seconds: toNumber(form.batch_quiet_seconds),
        max_attempts: toNumber(form.max_attempts),
        retry_minutes: toNumber(form.retry_minutes),
        working_hours: {
            from: form.work_from || null,
            to: form.work_to || null,
        },
    };
}

const filled = (list) => list.filter((p) => String(p.text || '').trim() !== '');

export function penaltySettingsPage(config) {
    const t = config.translations;
    const ui = config.ui;

    return {
        ui,

        translations: t,

        endpoints: config.endpoints,

        maxLevels: config.maxLevels,

        placeholders: config.placeholders,

        languages: LANGUAGES,

        tones: TONES,

        /** The language being edited; plain and respectful sit side by side. */
        language: 'uz',

        /** Whose ladder is being edited: operators and sales have their own. */
        role: 'operation',

        roles: ROLES,

        modes: MODES,

        /** "Copy from the other role" takes two clicks: the first one only asks. */
        copyArmed: false,

        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        loading: true,

        loadError: null,

        form: toForm({}),

        /** What the server holds, to tell a change from a no-op. */
        snapshot: '',

        customised: false,

        saving: false,

        resetArmed: false,

        errors: {},

        formError: null,

        /** Where a placeholder chip inserts: the phrase edited last. */
        target: null,

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
            this.target = null;
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

                    /*
                     * Unfold whatever the server pointed at, and show the
                     * role it is in.
                     */
                    let first = null;

                    Object.keys(this.errors).forEach((key) => {
                        const match = key.match(/^roles\.(\w+)\.levels\.(\d+)\./);
                        const level = match && this.form.roles[match[1]]?.levels[Number(match[2])];

                        if (level) {
                            level.open = true;
                            first ??= match[1];
                        }
                    });

                    if (first && !Object.keys(this.errors).some((key) => key.startsWith(`roles.${this.role}.`))) {
                        this.role = first;
                    }
                } else {
                    this.formError = error?.message || t.messages.failed;
                }
            } finally {
                this.saving = false;
            }
        },

        discard() {
            this.form = toForm(JSON.parse(this.snapshot || '{}'));
            this.errors = {};
            this.formError = null;
            this.target = null;
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

                /* Every switch the server knows, the role ones included. */
                this.settings = Object.fromEntries(
                    Object.entries({ ...this.settings, ...(json.data || {}) }).map(([k, v]) => [k, !!v]),
                );

                this.notify(json.message || config.settingsSaved);
            } catch (error) {
                this.settings[key] = previous;
                this.notify(error?.message || t.messages.failed, false);
            } finally {
                this.settingsBusy = null;
            }
        },

        /** Hours off: every hour counts. */
        clearHours() {
            this.form.work_from = '';
            this.form.work_to = '';
        },

        hoursSet() {
            return !!(this.form.work_from && this.form.work_to);
        },

        notify(message, success = true) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, success } }));
        },

        /*
        |----------------------------------------------------------------
        | Levels and phrases
        |----------------------------------------------------------------
        */

        /** The ladder of the role being edited. */
        ladder() {
            return this.form.roles[this.role].levels;
        },

        setRole(role) {
            this.role = role;
            this.target = null;
            this.copyArmed = false;
        },

        otherRole() {
            return this.role === 'sales' ? 'operation' : 'sales';
        },

        /** A dot on a role tab that holds a validation error. */
        roleHasErrors(role) {
            return Object.keys(this.errors).some((key) => key.startsWith(`roles.${role}.`));
        },

        /**
         * The other role's levels, copied over this one's (two clicks).
         * Saved only with the rest, from the bar at the bottom.
         */
        copyFromOther() {
            if (!this.copyArmed) {
                this.copyArmed = true;

                return;
            }

            const from = this.otherRole();

            this.form.roles[this.role].levels = toPayload(this.form).roles[from].levels.map(levelToForm);
            this.copyArmed = false;
            this.target = null;

            this.notify(t.copied.replace(':role', t.roles[from]));
        },

        setMode(index, mode) {
            this.ladder()[index].mode = mode;
        },

        modeClass(mode) {
            return {
                all: 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
                forward: 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-400',
                off: 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
            }[mode] ?? '';
        },

        toggleLevel(index) {
            this.ladder()[index].open = !this.ladder()[index].open;
        },

        addLevel() {
            if (this.ladder().length >= this.maxLevels) {
                return;
            }

            const last = this.ladder()[this.ladder().length - 1];
            const sets = emptySets();

            sets[this.language].plain.push({ key: nextKey(), text: '' });

            this.ladder().push({
                key: nextKey(),
                open: true,
                name: '',
                mode: 'all',
                /* One past the level before, so the new one is reachable at once. */
                from: (toNumber(last?.from) ?? this.ladder().length) + 1,
                phrases: sets,
            });

            this.$nextTick(() => {
                this.$refs.levelsEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' });
            });
        },

        removeLevel(index) {
            if (index === 0) {
                return;
            }

            this.ladder().splice(index, 1);
            this.target = null;
        },

        phrases(index, tone) {
            return this.ladder()[index].phrases[this.language][tone];
        },

        phraseId(index, tone, p) {
            return `phrase-${this.role}-${index}-${this.language}-${tone}-${p}`;
        },

        addPhrase(index, tone) {
            const list = this.phrases(index, tone);

            list.push({ key: nextKey(), text: '' });

            const p = list.length - 1;

            this.target = { role: this.role, level: index, language: this.language, tone, phrase: p };

            this.$nextTick(() => document.getElementById(this.phraseId(index, tone, p))?.focus());
        },

        removePhrase(index, tone, p) {
            this.phrases(index, tone).splice(p, 1);
            this.target = null;
        },

        focusPhrase(index, tone, p) {
            this.target = { role: this.role, level: index, language: this.language, tone, phrase: p };
        },

        /**
         * Puts {name} where the cursor is in the phrase edited last.
         */
        insertPlaceholder(name) {
            const target = this.target;

            if (!target || target.language !== this.language || target.role !== this.role) {
                return;
            }

            const item = this.ladder()[target.level]?.phrases[target.language][target.tone][target.phrase];

            if (!item) {
                return;
            }

            const token = `{${name}}`;
            const field = document.getElementById(this.phraseId(target.level, target.tone, target.phrase));
            const text = String(item.text ?? '');
            const start = field ? field.selectionStart : text.length;
            const end = field ? field.selectionEnd : text.length;

            item.text = text.slice(0, start) + token + text.slice(end);

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
            const top = this.ladder().length - 1;

            const step = top <= 0 ? 0 : Math.round((index / top) * (LEVEL_TONES.length - 1));

            return LEVEL_TONES[Math.max(0, Math.min(LEVEL_TONES.length - 1, step))];
        },

        levelTitle(index) {
            return t.level.title.replace(':n', index + 1);
        },

        /** "Штраф №2", or "Штраф №4 и дальше" for the last level. */
        summary(index) {
            const from = index === 0 ? 1 : (toNumber(this.ladder()[index].from) ?? '?');

            if (index === 0 && this.ladder().length > 1) {
                return t.level.first;
            }

            return (index === this.ladder().length - 1 ? t.level.from_summary_last : t.level.from_summary)
                .replace(':n', from);
        },

        /** How many phrases a level has in each language - "UZ 2 · RU 1". */
        counts(index) {
            const level = this.ladder()[index];

            return LANGUAGES
                .map((lang) => `${lang.toUpperCase()} ${TONES.reduce((n, tone) => n + filled(level.phrases[lang][tone]).length, 0)}`)
                .join(' · ');
        },

        /** For a folded card: the first phrase of a tone in the language being edited. */
        firstPhrase(index, tone) {
            const first = filled(this.phrases(index, tone))[0];

            return first ? this.preview(first.text, index) : '';
        },

        /** What an empty set falls back to, in words. */
        emptyText(index, tone) {
            const fallback = tone === 'respectful' ? t.level.fallback_tone : t.level.fallback_lower;

            return t.level.empty.replace(':fallback', fallback);
        },

        /** A phrase as the person would get it at this level. */
        preview(text, index) {
            const level = this.ladder()[index] || {};

            const sample = {
                name: 'ABDUKARIM TOSHMUQUMOV',
                request: 'TLS04851',
                repeat_number: index === 0 ? 1 : (toNumber(level.from) ?? 1),
                crm_status: 'В поиске перевозчика',
                ...SAMPLE[this.language],
            };

            const html = String(text || '').replace(
                /\{([a-z_]+)\}/g,
                (match, key) => (key in sample ? escapeHtml(sample[key]) : match),
            );

            return telegramHtml(html);
        },

        fieldError(path) {
            const messages = this.errors[path];

            return Array.isArray(messages) ? messages[0] : null;
        },

        levelErrors(index) {
            const prefix = `roles.${this.role}.levels.${index}.`;

            return Object.keys(this.errors)
                .filter((key) => key === `${prefix}from` || key === `${prefix}phrases`)
                .map((key) => this.errors[key][0]);
        },
    };
}

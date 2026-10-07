/*
|--------------------------------------------------------------------------
| Auto replies
|--------------------------------------------------------------------------
|
| One form over the auto replies file (AutoReplyRules): the kinds - what an
| operator or a sales manager writes, what they are told back - and when
| to answer at all. One language is edited at a time, plain and respectful
| side by side. Edited locally, saved in one go from the bar at the bottom.
|
| The tester runs the same matching as AutoReplyMatcher, here in the
| browser, so a keyword can be tried before it is saved.
|
*/

import { escapeHtml, telegramHtml } from './telegram-html';

const LANGUAGES = ['uz', 'ru'];

const TONES = ['plain', 'respectful'];

/** What a preview fills the placeholders with, in each language. */
const SAMPLE = {
    uz: { address: 'Ali aka', name: 'ALIYEV ALI', request: 'TLS04851' },
    ru: { address: 'Али ака', name: 'ALIYEV ALI', request: 'TLS04851' },
};

let keySeed = 0;

const nextKey = () => {
    keySeed += 1;

    return `k${keySeed}`;
};

const toNumber = (value, min = 1) => {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) && number >= min ? Math.round(number) : null;
};

/** Keywords are typed as one list: commas or new lines between them. */
const splitKeywords = (text) => String(text || '')
    .split(/[,\n]+/)
    .map((keyword) => keyword.trim())
    .filter((keyword) => keyword !== '');

/** Letters people type either way: ё/е and Uzbek Cyrillic without its marks. */
const LOOSE = { ё: 'е', ў: 'у', қ: 'к', ғ: 'г', ҳ: 'х' };

/**
 * AutoReplyMatcher::words(): letters and digits make a word, any other
 * symbol ("+", "👍") is a word of its own, punctuation is dropped; case,
 * apostrophes, skin tones, LOOSE letters and stretched letters do not
 * count.
 */
export function words(text) {
    const cleaned = String(text || '')
        .toLowerCase()
        .replace(/[\u{1F3FB}-\u{1F3FF}\u{FE0F}\u{200D}]/gu, '')
        .replace(/[ёўқғҳ]/g, (letter) => LOOSE[letter]);

    const found = cleaned.match(/[\p{L}\p{N}\p{M}'‘’ʻʼ`]+|[^\s\p{L}\p{N}\p{M}\p{P}]/gu) || [];

    return found
        .map((word) => word.replace(/['‘’ʻʼ`]/g, '').replace(/([^\p{N}])\1+/gu, '$1'))
        .filter((word) => word !== '');
}

/** With `prefix`, the needle's last word only has to start a word ("клиент*"). */
const contains = (haystack, needle, prefix) => {
    const last = needle.length - 1;

    for (let i = 0; i + needle.length <= haystack.length; i += 1) {
        if (needle.every((word, j) => (prefix && j === last
            ? haystack[i + j].startsWith(word)
            : haystack[i + j] === word))) {
            return true;
        }
    }

    return false;
};

/** A keyword against a message's words, "*" at its end included. */
const keywordIn = (message, keyword) => {
    const trimmed = String(keyword).trim();
    const prefix = trimmed.endsWith('*');
    const needle = words(prefix ? trimmed.replace(/\*+$/, '') : trimmed);

    return needle.length > 0 && contains(message, needle, prefix);
};

const emptySets = () => Object.fromEntries(
    LANGUAGES.map((lang) => [lang, Object.fromEntries(TONES.map((tone) => [tone, []]))]),
);

/** Server answer sets -> form ones: answers get stable keys for x-for. */
function setsToForm(sets) {
    const answers = emptySets();

    LANGUAGES.forEach((lang) => TONES.forEach((tone) => {
        answers[lang][tone] = (sets?.[lang]?.[tone] || []).map((text) => ({ key: nextKey(), text }));
    }));

    return answers;
}

function setsToPayload(sets) {
    return Object.fromEntries(LANGUAGES.map((lang) => [
        lang,
        Object.fromEntries(TONES.map((tone) => [
            tone,
            sets[lang][tone].map((a) => String(a.text || '').trim()).filter((a) => a !== ''),
        ])),
    ]));
}

function kindToForm(kind) {
    return {
        key: nextKey(),
        name: kind.name ?? '',
        keywords: (kind.keywords || []).join(', '),
        /* '' = the file's own max_words */
        max_words: kind.max_words ?? '',
        answers: setsToForm(kind.answers),
    };
}

function kindToPayload(kind) {
    return {
        name: String(kind.name || '').trim() || null,
        keywords: splitKeywords(kind.keywords),
        max_words: toNumber(kind.max_words),
        answers: setsToPayload(kind.answers),
    };
}

function toForm(data) {
    return {
        enabled: data.enabled ?? true,
        only_after_penalty: !!data.only_after_penalty,
        penalty_window_minutes: data.penalty_window_minutes ?? 60,
        cooldown_minutes: data.cooldown_minutes ?? 10,
        max_words: data.max_words ?? 5,
        replies: (data.replies || []).map(kindToForm),
        silence: {
            enabled: !!data.silence?.enabled,
            after_minutes: data.silence?.after_minutes ?? 15,
            answers: setsToForm(data.silence?.answers),
        },
    };
}

function toPayload(form) {
    return {
        enabled: !!form.enabled,
        only_after_penalty: !!form.only_after_penalty,
        penalty_window_minutes: toNumber(form.penalty_window_minutes),
        cooldown_minutes: toNumber(form.cooldown_minutes, 0),
        max_words: toNumber(form.max_words),
        replies: form.replies.map(kindToPayload),
        silence: {
            enabled: !!form.silence.enabled,
            after_minutes: toNumber(form.silence.after_minutes),
            answers: setsToPayload(form.silence.answers),
        },
    };
}

const filled = (list) => list.filter((a) => String(a.text || '').trim() !== '');

export function autoRepliesPage(config) {
    const t = config.translations;

    return {
        translations: t,

        endpoints: config.endpoints,

        maxReplies: config.maxReplies,

        placeholders: config.placeholders,

        languages: LANGUAGES,

        /** "O'zbekcha", "Русский". */
        languageNames: config.languages,

        tones: TONES,

        /** The language being edited; plain and respectful sit side by side. */
        language: 'uz',

        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        loading: true,

        loadError: null,

        form: toForm({}),

        /** What the file holds, to tell a change from a no-op. */
        snapshot: '',

        customised: false,

        fileError: null,

        path: '',

        saving: false,

        resetArmed: false,

        errors: {},

        formError: null,

        /** Where a placeholder chip inserts: the answer edited last. */
        target: null,

        /** The tester: a message, and the person it is tried as. */
        test: { text: '', language: 'uz', tone: 'plain' },

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

        async send(method, body = null) {
            const response = await fetch(this.endpoints.rules, {
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
            this.fileError = json.file_error || null;
            this.path = json.path || '';
            this.errors = {};
            this.formError = null;
            this.resetArmed = false;
            this.target = null;
        },

        async load() {
            this.loading = true;
            this.loadError = null;

            try {
                this.accept(await this.send('GET'));
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
                const json = await this.send('PUT', toPayload(this.form));

                this.accept(json);
                this.notify(json.message || t.messages.saved);
            } catch (error) {
                if (error.status === 422) {
                    this.errors = error.errors;
                    this.formError = t.validation.fix;
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
                const json = await this.send('DELETE');

                this.accept(json);
                this.notify(json.message || t.messages.reset);
            } catch (error) {
                this.formError = error?.message || t.messages.failed;
            } finally {
                this.saving = false;
                this.resetArmed = false;
            }
        },

        notify(message, success = true) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, success } }));
        },

        /*
        |----------------------------------------------------------------
        | Kinds and answers
        |----------------------------------------------------------------
        */

        addKind() {
            if (this.form.replies.length >= this.maxReplies) {
                return;
            }

            const answers = emptySets();

            answers[this.language].plain.push({ key: nextKey(), text: '' });

            this.form.replies.push({ key: nextKey(), name: '', keywords: '', max_words: '', answers });

            this.$nextTick(() => {
                this.$refs.kindsEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' });
            });
        },

        removeKind(k) {
            this.form.replies.splice(k, 1);
            this.target = null;
        },

        /** The order matters: the first kind that matches wins. */
        moveKind(k, step) {
            const to = k + step;

            if (to < 0 || to >= this.form.replies.length) {
                return;
            }

            const [kind] = this.form.replies.splice(k, 1);

            this.form.replies.splice(to, 0, kind);
            this.target = null;
        },

        kindTitle(k) {
            return t.kind.title.replace(':n', k + 1);
        },

        /** The answers of kind k - or of the nudge, k = 'silence'. */
        answers(k, tone) {
            const owner = k === 'silence' ? this.form.silence : this.form.replies[k];

            return owner.answers[this.language][tone];
        },

        answerId(k, tone, a) {
            return `answer-${k}-${this.language}-${tone}-${a}`;
        },

        addAnswer(k, tone) {
            const list = this.answers(k, tone);

            list.push({ key: nextKey(), text: '' });

            const a = list.length - 1;

            this.target = { kind: k, language: this.language, tone, answer: a };

            this.$nextTick(() => document.getElementById(this.answerId(k, tone, a))?.focus());
        },

        removeAnswer(k, tone, a) {
            this.answers(k, tone).splice(a, 1);
            this.target = null;
        },

        focusAnswer(k, tone, a) {
            this.target = { kind: k, language: this.language, tone, answer: a };
        },

        /** Puts {name} where the cursor is in the answer edited last. */
        insertPlaceholder(name) {
            const target = this.target;

            if (!target || target.language !== this.language) {
                return;
            }

            const owner = target.kind === 'silence' ? this.form.silence : this.form.replies[target.kind];
            const item = owner?.answers[target.language][target.tone][target.answer];

            if (!item) {
                return;
            }

            const token = `{${name}}`;
            const field = document.getElementById(this.answerId(target.kind, target.tone, target.answer));
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

        keywordCount(k) {
            return splitKeywords(this.form.replies[k].keywords).length;
        },

        maxWordsHint() {
            return t.kind.max_words_hint.replace(':n', toNumber(this.form.max_words) ?? 5);
        },

        /**
         * An answer as the person would get it: ClientCheckEscalation::render(),
         * {address} included.
         */
        preview(text, language = this.language) {
            const sample = SAMPLE[language];

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

        kindErrors(k) {
            return ['keywords', 'answers', 'max_words']
                .map((field) => this.fieldError(`replies.${k}.${field}`))
                .filter((message) => message);
        },

        /*
        |----------------------------------------------------------------
        | Tester
        |----------------------------------------------------------------
        */

        /**
         * What the listener would do with the test message, from the form
         * as it is now - saved or not.
         *
         * @returns {{state: 'empty'|'long'|'none'|'match', kind?: string, answer?: string}}
         */
        tried() {
            const message = words(this.test.text);

            if (message.length === 0) {
                return { state: 'empty' };
            }

            const max = toNumber(this.form.max_words) ?? 5;

            const index = this.form.replies.findIndex((kind) => message.length <= (toNumber(kind.max_words) ?? max)
                && splitKeywords(kind.keywords).some((keyword) => keywordIn(message, keyword)));

            if (index < 0) {
                return { state: message.length > max ? 'long' : 'none' };
            }

            const kind = this.form.replies[index];
            const otherLanguage = LANGUAGES.find((lang) => lang !== this.test.language);
            const otherTone = TONES.find((tone) => tone !== this.test.tone);

            /* The same fallback as AutoReplyRules::answers(). */
            let answer = null;

            for (const lang of [this.test.language, otherLanguage]) {
                for (const tone of [this.test.tone, otherTone]) {
                    const first = filled(kind.answers[lang][tone])[0];

                    if (!answer && first) {
                        answer = this.preview(first.text, lang);
                    }
                }
            }

            return {
                state: 'match',
                kind: String(kind.name || '').trim() || this.kindTitle(index),
                answer,
            };
        },

        triedText() {
            const result = this.tried();

            return {
                long: t.test.too_long.replace(':n', toNumber(this.form.max_words) ?? 5),
                none: t.test.none,
                match: t.test.match.replace(':kind', result.kind ?? ''),
            }[result.state] ?? '';
        },
    };
}

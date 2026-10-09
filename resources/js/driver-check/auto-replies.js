/*
|--------------------------------------------------------------------------
| Auto replies
|--------------------------------------------------------------------------
|
| One form over the auto replies file (AutoReplyRules): the kinds - what an
| operator or a sales manager writes, what they are told back - the
| greetings answered before them, and when to answer at all. One language is edited at a time, plain and respectful
| side by side. Edited locally, saved in one go from the bar at the bottom.
|
| The tester runs the same matching as AutoReplyMatcher, here in the
| browser, so a keyword can be tried before it is saved.
|
*/

import { escapeHtml, telegramHtml } from './telegram-html';
import { telegramGifPicker, uploadAnswerMedia } from './media-picker';

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

/** A kind's or a greeting's lasting id (AutoReplyRules::id()): personal answers point at it. */
const newId = () => Array.from(
    crypto.getRandomValues(new Uint8Array(8)),
    (byte) => byte.toString(16).padStart(2, '0'),
).join('');

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

/**
 * Where the needle starts in the haystack, -1 when it is not there. With
 * `prefix`, the needle's last word only has to start a word ("клиент*").
 */
const position = (haystack, needle, prefix) => {
    const last = needle.length - 1;

    for (let i = 0; i + needle.length <= haystack.length; i += 1) {
        if (needle.every((word, j) => (prefix && j === last
            ? haystack[i + j].startsWith(word)
            : haystack[i + j] === word))) {
            return i;
        }
    }

    return -1;
};

/** A keyword as AutoReplyMatcher reads it: its words, and whether "*" ends it. */
const needleOf = (keyword) => {
    const trimmed = String(keyword).trim();
    const prefix = trimmed.endsWith('*');

    return { needle: words(prefix ? trimmed.replace(/\*+$/, '') : trimmed), prefix };
};

/** A keyword against a message's words, "*" at its end included. */
const keywordIn = (message, keyword) => {
    const { needle, prefix } = needleOf(keyword);

    return needle.length > 0 && position(message, needle, prefix) >= 0;
};

/**
 * AutoReplyMatcher::greeting(): the first greeting found, the words left
 * once every greeting is taken out, and whether only fillers are left.
 */
const findGreeting = (greetings, message) => {
    const rest = [...message];
    let found = -1;

    greetings.list.forEach((greeting, index) => {
        splitKeywords(greeting.keywords).forEach((keyword) => {
            const { needle, prefix } = needleOf(keyword);

            if (needle.length === 0) {
                return;
            }

            for (let at = position(rest, needle, prefix); at >= 0; at = position(rest, needle, prefix)) {
                rest.splice(at, needle.length);

                if (found < 0) {
                    found = index;
                }
            }
        });
    });

    if (found < 0) {
        return null;
    }

    const fillers = splitKeywords(greetings.fillers).flatMap((filler) => words(filler));

    return { index: found, rest, alone: rest.every((word) => fillers.includes(word)) };
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

/**
 * GIFs for every language, voices per language: {file, name} each; a GIF
 * found in Telegram has its document too ({id, access_hash, file_reference}).
 */
function mediaToForm(media) {
    return {
        gifs: (media?.gifs || []).map((item) => ({
            file: item.file,
            name: item.name,
            ...(item.telegram ? { telegram: { ...item.telegram } } : {}),
        })),
        voices: Object.fromEntries(LANGUAGES.map((lang) => [
            lang,
            (media?.voices?.[lang] || []).map((item) => ({ file: item.file, name: item.name })),
        ])),
    };
}

const mediaToPayload = mediaToForm;

function kindToForm(kind) {
    return {
        key: nextKey(),
        id: kind.id || newId(),
        name: kind.name ?? '',
        keywords: (kind.keywords || []).join(', '),
        /* '' = the file's own max_words */
        max_words: kind.max_words ?? '',
        answers: setsToForm(kind.answers),
        media: mediaToForm(kind.media),
    };
}

function kindToPayload(kind) {
    return {
        id: kind.id,
        name: String(kind.name || '').trim() || null,
        keywords: splitKeywords(kind.keywords),
        max_words: toNumber(kind.max_words),
        answers: setsToPayload(kind.answers),
        media: mediaToPayload(kind.media),
    };
}

/** Greetings answer with texts only. */
function greetingToForm(greeting) {
    return {
        key: nextKey(),
        id: greeting.id || newId(),
        name: greeting.name ?? '',
        keywords: (greeting.keywords || []).join(', '),
        answers: setsToForm(greeting.answers),
    };
}

function greetingToPayload(greeting) {
    return {
        id: greeting.id,
        name: String(greeting.name || '').trim() || null,
        keywords: splitKeywords(greeting.keywords),
        answers: setsToPayload(greeting.answers),
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
            after_penalties: data.silence?.after_penalties ?? 5,
            answers: setsToForm(data.silence?.answers),
            media: mediaToForm(data.silence?.media),
        },
        greetings: {
            enabled: !!data.greetings?.enabled,
            fillers: (data.greetings?.fillers || []).join(', '),
            list: (data.greetings?.list || []).map(greetingToForm),
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
            after_penalties: toNumber(form.silence.after_penalties),
            answers: setsToPayload(form.silence.answers),
            media: mediaToPayload(form.silence.media),
        },
        greetings: {
            enabled: !!form.greetings.enabled,
            fillers: splitKeywords(form.greetings.fillers),
            list: form.greetings.list.map(greetingToPayload),
        },
    };
}

const filled = (list) => list.filter((a) => String(a.text || '').trim() !== '');

/** The answers that hold texts, as AutoReplyRules::pick() falls back: the other tone, then the other language. */
const pickSet = (sets, language, tone) => {
    const otherLanguage = LANGUAGES.find((lang) => lang !== language);
    const otherTone = TONES.find((t) => t !== tone);

    for (const lang of [language, otherLanguage]) {
        for (const t of [tone, otherTone]) {
            const set = filled(sets[lang][t]);

            if (set.length > 0) {
                return { lang, set };
            }
        }
    }

    return { lang: language, set: [] };
};

export function autoRepliesPage(config) {
    const t = config.translations;

    return {
        ...telegramGifPicker(t.media),

        translations: t,

        endpoints: config.endpoints,

        maxReplies: config.maxReplies,

        maxGreetings: config.maxGreetings,

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

        /** Uploads in flight, by uploadKey(). */
        uploading: {},


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

            this.form.replies.push({
                key: nextKey(), id: newId(), name: '', keywords: '', max_words: '', answers, media: mediaToForm({}),
            });

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

        /*
        |----------------------------------------------------------------
        | Greetings: answered before the kind, texts only
        |----------------------------------------------------------------
        */

        addGreeting() {
            if (this.form.greetings.list.length >= this.maxGreetings) {
                return;
            }

            const answers = emptySets();

            answers[this.language].plain.push({ key: nextKey(), text: '' });

            this.form.greetings.list.push({ key: nextKey(), id: newId(), name: '', keywords: '', answers });
        },

        removeGreeting(g) {
            this.form.greetings.list.splice(g, 1);
            this.target = null;
        },

        /** The order matters: the first greeting found picks the answer. */
        moveGreeting(g, step) {
            const list = this.form.greetings.list;
            const to = g + step;

            if (to < 0 || to >= list.length) {
                return;
            }

            const [greeting] = list.splice(g, 1);

            list.splice(to, 0, greeting);
            this.target = null;
        },

        greetingTitle(g) {
            return t.greetings.title.replace(':n', g + 1);
        },

        greetingKeywordCount(g) {
            return splitKeywords(this.form.greetings.list[g].keywords).length;
        },

        greetingErrors(g) {
            return ['keywords', 'answers']
                .map((field) => this.fieldError(`greetings.list.${g}.${field}`))
                .filter((message) => message);
        },

        /**
         * Kind k, the nudge (k = 'silence') or greeting g (k = 'g' + g):
         * whatever has answers.
         */
        owner(k) {
            if (k === 'silence') {
                return this.form.silence;
            }

            if (typeof k === 'string' && k.startsWith('g')) {
                return this.form.greetings.list[Number(k.slice(1))];
            }

            return this.form.replies[k];
        },

        answers(k, tone) {
            return this.owner(k).answers[this.language][tone];
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

            const item = this.owner(target.kind)?.answers[target.language][target.tone][target.answer];

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
        | GIFs and voice messages
        |----------------------------------------------------------------
        */

        /** GIFs of kind k (or 'silence'), or its voices in the language being edited. */
        mediaList(k, type) {
            const owner = this.owner(k);

            return type === 'gif' ? owner.media.gifs : owner.media.voices[this.language];
        },

        uploadKey(k, type) {
            return `${k}-${type}-${type === 'gif' ? '' : this.language}`;
        },

        mediaUrl(file) {
            return `${this.endpoints.media}/${encodeURIComponent(file)}`;
        },

        /**
         * Stored on the server at once, put into the rules with the next
         * save - like any other change on the page.
         */
        async uploadMedia(k, type, event) {
            const input = event.target;
            const file = input.files?.[0];

            input.value = '';

            if (!file) {
                return;
            }

            const key = this.uploadKey(k, type);
            const list = this.mediaList(k, type);

            this.uploading = { ...this.uploading, [key]: true };

            try {
                list.push(await uploadAnswerMedia(this.endpoints.upload, this.csrf, type, file));
            } catch (error) {
                this.notify(error?.message || t.media.failed, false);
            } finally {
                this.uploading = { ...this.uploading, [key]: false };
            }
        },

        removeMedia(k, type, m) {
            this.mediaList(k, type).splice(m, 1);
        },

        /** Where the Telegram GIF search (media-picker.js) puts a GIF. */
        telegramGifPicked(k, gif) {
            this.mediaList(k, 'gif').push(gif);
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
         * A greeting is read apart, as ProcessAutoReply does: greeted back
         * when the rest is a kind or nothing but fillers.
         *
         * @returns {{state: 'empty'|'long'|'none'|'greeting'|'match', kind?: string, answers?: string[], greeting?: string, greetingAnswers?: string[]}}
         */
        tried() {
            const all = words(this.test.text);

            if (all.length === 0) {
                return { state: 'empty' };
            }

            const found = this.form.greetings.enabled ? findGreeting(this.form.greetings, all) : null;
            const message = found ? found.rest : all;
            const max = toNumber(this.form.max_words) ?? 5;

            const index = message.length === 0 ? -1 : this.form.replies.findIndex((kind) => message.length <= (toNumber(kind.max_words) ?? max)
                && splitKeywords(kind.keywords).some((keyword) => keywordIn(message, keyword)));

            let hello = null;

            if (found && (index >= 0 || found.alone)) {
                const greeting = this.form.greetings.list[found.index];
                const { lang, set } = pickSet(greeting.answers, this.test.language, this.test.tone);

                hello = {
                    greeting: String(greeting.name || '').trim() || this.greetingTitle(found.index),
                    greetingAnswers: set.map((answer) => this.preview(answer.text, lang)),
                };
            }

            if (index < 0) {
                if (hello) {
                    return { state: 'greeting', answers: [], ...hello };
                }

                return { state: message.length > max ? 'long' : 'none' };
            }

            const kind = this.form.replies[index];

            /* The same fallback as AutoReplyRules::answers(): the first set that has texts. */
            const { lang, set } = pickSet(kind.answers, this.test.language, this.test.tone);

            return {
                state: 'match',
                kind: String(kind.name || '').trim() || this.kindTitle(index),
                answers: set.map((answer) => this.preview(answer.text, lang)),
                gifs: kind.media.gifs.length,
                voices: (kind.media.voices[this.test.language] || []).length,
                ...(hello || {}),
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

        triedGreeting() {
            const result = this.tried();

            if (!result.greeting) {
                return '';
            }

            return (result.state === 'greeting' ? t.test.greeting_only : t.test.greeting)
                .replace(':greeting', result.greeting);
        },
    };
}

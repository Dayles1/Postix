/*
|--------------------------------------------------------------------------
| Uzbek Latin -> Cyrillic, for the previews
|--------------------------------------------------------------------------
|
| The same rules as App\Application\Telegram\Services\UzbekScript, which is
| what really converts the bot's Uzbek on the way out. The panel keeps the
| phrases in Latin; its previews ("as the person would get it") show what
| goes out, so they run the same conversion. Keep the two in step.
|
| Plain letters, as typed on a Russian keyboard: no ў, ғ, ҳ, қ.
|
*/

const APOSTROPHES = ["'", '’', 'ʻ', 'ʼ', '‘', '`'];

const LETTERS = {
    a: 'а', b: 'б', c: 'ц', d: 'д', f: 'ф', g: 'г', h: 'х', i: 'и', j: 'ж',
    k: 'к', l: 'л', m: 'м', n: 'н', o: 'о', p: 'п', q: 'к', r: 'р', s: 'с',
    t: 'т', u: 'у', v: 'в', w: 'в', x: 'х', y: 'й', z: 'з',
};

/** Two letters that are one: the second is what follows the first. */
const PAIRS = {
    s: { h: 'ш' },
    c: { h: 'ч' },
    y: { o: 'ё', u: 'ю', a: 'я', e: 'е' },
};

/** o' and g' are letters of their own, written as у and г. */
const MARKED = { o: 'у', g: 'г' };

const CLOSING = { '{': '}', '<': '>', '&': ';' };

const isLetter = (char) => char !== '' && char !== undefined && /^\p{L}$/u.test(char);

const cased = (letter, upper) => (upper ? letter.toUpperCase() : letter);

export function toCyrillic(text) {
    const chars = Array.from(String(text ?? ''));
    let out = '';

    for (let i = 0; i < chars.length; i += 1) {
        const char = chars[i];
        const closing = CLOSING[char];

        /* {placeholder}, <tag>, &entity; - copied as they are. */
        if (closing !== undefined) {
            const end = chars.slice(i + 1, i + 41).indexOf(closing);

            if (end !== -1) {
                const segment = chars.slice(i, i + end + 2).join('');

                if (char !== '&' || /^&#?\w+;$/.test(segment)) {
                    out += segment;
                    i += end + 1;

                    continue;
                }
            }
        }

        const lower = char.toLowerCase();
        const upper = char !== lower;
        const next = chars[i + 1] ?? '';
        const nextLower = next.toLowerCase();

        if (MARKED[lower] && APOSTROPHES.includes(next)) {
            out += cased(MARKED[lower], upper);
            i += 1;

            continue;
        }

        /* yo'q is йук: the o belongs to the apostrophe after it, not to the y. */
        const yoMarked = lower === 'y' && nextLower === 'o' && APOSTROPHES.includes(chars[i + 2] ?? '');

        if (!yoMarked && PAIRS[lower]?.[nextLower]) {
            out += cased(PAIRS[lower][nextLower], upper);
            i += 1;

            continue;
        }

        if (lower === 'e') {
            /* "Ertalab" is Эрталаб, "kelishdik" is келишдик. */
            out += cased(isLetter(chars[i - 1]) ? 'е' : 'э', upper);

            continue;
        }

        /* a'lo, ma'lumot: the hamza between two letters is ъ. */
        if (APOSTROPHES.includes(char)) {
            out += isLetter(chars[i - 1]) && isLetter(next) ? 'ъ' : char;

            continue;
        }

        out += LETTERS[lower] ? cased(LETTERS[lower], upper) : char;
    }

    return out;
}

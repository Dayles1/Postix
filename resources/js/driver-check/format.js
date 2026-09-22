/*
|--------------------------------------------------------------------------
| Formatting helpers shared by every driver-check page
|--------------------------------------------------------------------------
|
| Each page used to carry its own copy of these, which is how the same
| value ended up rendered three different ways across the panel.
|
*/

const EM_DASH = '—';

/**
 * The document language drives every number/date format, so switching the
 * app locale switches the panel without touching a single page.
 */
function locale() {
    return document.documentElement.lang || 'uz-UZ';
}

export function formatNumber(value) {
    const number = Number(value ?? 0);

    if (Number.isNaN(number)) {
        return '0';
    }

    return number.toLocaleString(locale());
}

/**
 * Large counters are shortened on narrow screens: "12 480" does not fit a
 * stat tile on a 360px phone, "12.5K" does.
 */
export function formatCompact(value) {
    const number = Number(value ?? 0);

    if (Number.isNaN(number)) {
        return '0';
    }

    if (Math.abs(number) < 10000) {
        return number.toLocaleString(locale());
    }

    try {
        return new Intl.NumberFormat(locale(), {
            notation: 'compact',
            maximumFractionDigits: 1,
        }).format(number);
    } catch (_) {
        return number.toLocaleString(locale());
    }
}

export function formatPercent(value, digits = 1) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const number = Number(value);

    return Number.isNaN(number) ? '0' : number.toFixed(digits);
}

export function formatScore(value, digits = 1) {
    if (value === null || value === undefined || value === '') {
        return EM_DASH;
    }

    const number = Number(value);

    return Number.isNaN(number) ? EM_DASH : number.toFixed(digits);
}

export function toDate(value) {
    if (!value) {
        return null;
    }

    const date = new Date(String(value).replace(' ', 'T'));

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value, withTime = true) {
    const date = toDate(value);

    if (!date) {
        return value ? String(value) : EM_DASH;
    }

    return new Intl.DateTimeFormat(locale(), {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(date);
}

/**
 * "3 soat oldin" answers "is this fresh?" at a glance, which is the only
 * question a "last check" column is ever asked. The absolute date stays
 * available as the element title.
 */
export function formatRelative(value) {
    const date = toDate(value);

    if (!date) {
        return EM_DASH;
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const absolute = Math.abs(seconds);

    const units = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    try {
        const formatter = new Intl.RelativeTimeFormat(locale(), {
            numeric: 'auto',
        });

        for (const [unit, size] of units) {
            if (absolute >= size) {
                return formatter.format(Math.round(seconds / size), unit);
            }
        }

        return formatter.format(Math.round(seconds / 60), 'minute');
    } catch (_) {
        return formatDate(value);
    }
}

export function initials(name) {
    if (!name) {
        return '?';
    }

    return String(name)
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

export function telegramHandle(username) {
    if (!username) {
        return null;
    }

    return '@' + String(username).replace(/^@/, '');
}

/**
 * A t.me address for anything the panel shows as a Telegram handle: a
 * @username, a t.me link in any spelling, or a tg:// deep link.
 *
 * Returns null when the value cannot be opened - a numeric chat or user id
 * is not addressable by URL - and the caller then renders plain text,
 * because an <a> whose href is null is exactly that.
 */
export function telegramUrl(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const raw = String(value).trim();

    if (raw === '') {
        return null;
    }

    /* -100123... and 123...: an id, not an address. */
    if (/^-?\d+$/.test(raw)) {
        return null;
    }

    const resolve = raw.match(/^tg:\/\/resolve\?domain=([A-Za-z0-9_]+)/i);

    if (resolve) {
        return `https://t.me/${resolve[1]}`;
    }

    const join = raw.match(/^tg:\/\/join\?invite=([A-Za-z0-9_-]+)/i);

    if (join) {
        return `https://t.me/+${join[1]}`;
    }

    const bare = raw
        .replace(/^(https?:\/\/)?(www\.)?/i, '')
        .replace(/\/+$/, '');

    /* Invite hashes keep the "+" form; joinchat/ is its older spelling. */
    const invite = bare.match(/^t\.me\/(?:joinchat\/|\+)([A-Za-z0-9_-]+)$/i);

    if (invite) {
        return `https://t.me/+${invite[1]}`;
    }

    /* A public link, optionally pointing at one message. */
    const path = bare.match(/^t\.me\/([A-Za-z0-9_]{4,32}(?:\/\d+)?)$/i);

    if (path) {
        return `https://t.me/${path[1]}`;
    }

    const username = raw.match(/^@?([A-Za-z0-9_]{4,32})$/);

    return username ? `https://t.me/${username[1]}` : null;
}

/**
 * Uzbek numbers arrive as a bare +998XXXXXXXXX string; grouping them makes
 * them scannable in a list without changing the value that gets copied.
 */
export function formatPhone(value) {
    if (!value) {
        return EM_DASH;
    }

    const raw = String(value).trim();
    const digits = raw.replace(/\D/g, '');

    if (digits.length === 12 && digits.startsWith('998')) {
        return `+${digits.slice(0, 3)} ${digits.slice(3, 5)} ${digits.slice(5, 8)} ${digits.slice(8, 10)} ${digits.slice(10)}`;
    }

    return raw.startsWith('+') ? raw : `+${digits || raw}`;
}

export { EM_DASH };

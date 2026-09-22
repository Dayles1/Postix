/*
|--------------------------------------------------------------------------
| Check / driver status vocabulary
|--------------------------------------------------------------------------
|
| One place decides what a status is called and what colour it wears, so a
| "confirmed" driver looks the same on the list, the detail page and the
| operator card.
|
| Every class string below is written out in full: Tailwind scans the js
| files under resources (see the @source lines in app.css) and can only
| see literals.
|
*/

const TONES = {
    confirmed: {
        badge: 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20',
        soft: 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
        dot: 'bg-success-500',
        bar: 'bg-success-500',
        text: 'text-success-600 dark:text-success-400',
        stripe: 'bg-success-500',
        ring: 'ring-success-500/30',
    },

    not_confirmed: {
        badge: 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400 dark:ring-error-500/20',
        soft: 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
        dot: 'bg-error-500',
        bar: 'bg-error-500',
        text: 'text-error-600 dark:text-error-400',
        stripe: 'bg-error-500',
        ring: 'ring-error-500/30',
    },

    pending: {
        badge: 'bg-warning-50 text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20',
        soft: 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
        dot: 'bg-warning-500',
        bar: 'bg-warning-500',
        text: 'text-warning-600 dark:text-warning-400',
        stripe: 'bg-warning-500',
        ring: 'ring-warning-500/30',
    },

    processing: {
        badge: 'bg-blue-light-50 text-blue-light-700 ring-1 ring-inset ring-blue-light-600/20 dark:bg-blue-light-500/10 dark:text-blue-light-400 dark:ring-blue-light-500/20',
        soft: 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-400',
        dot: 'bg-blue-light-500',
        bar: 'bg-blue-light-500',
        text: 'text-blue-light-600 dark:text-blue-light-400',
        stripe: 'bg-blue-light-500',
        ring: 'ring-blue-light-500/30',
    },

    unknown: {
        badge: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/60 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/10',
        soft: 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300',
        dot: 'bg-gray-400',
        bar: 'bg-gray-300 dark:bg-gray-700',
        text: 'text-gray-500 dark:text-gray-400',
        stripe: 'bg-gray-300 dark:bg-gray-700',
        ring: 'ring-gray-400/30',
    },
};

const ALIASES = {
    not_confirmed: ['not_confirmed', 'not-confirmed', 'not confirmed', 'rejected', 'failed', 'unmatched', 'not_verified', 'not-verified', 'invalid'],
    confirmed: ['confirmed', 'confirm', 'verified', 'success', 'matched', 'match', 'valid'],
    processing: ['processing', 'in_progress', 'in-progress', 'running'],
    pending: ['pending', 'waiting', 'queued'],
};

/**
 * Normalises whatever the API reports into one of the four known statuses.
 *
 * `not_confirmed` is tested first on purpose: it contains the word
 * "confirmed" and would otherwise be swallowed by the confirmed branch.
 */
export function statusType(value) {
    const raw = String(value ?? '').trim().toLowerCase();

    if (raw === '') {
        return 'unknown';
    }

    for (const key of ['not_confirmed', 'confirmed', 'processing', 'pending']) {
        if (ALIASES[key].includes(raw)) {
            return key;
        }
    }

    return 'unknown';
}

export function statusTone(value, variant = 'badge') {
    const tone = TONES[statusType(value)] ?? TONES.unknown;

    return tone[variant] ?? tone.badge;
}

/**
 * @param {object} labels  telegram.* status labels, keyed by status
 */
export function statusLabel(value, labels = {}) {
    const type = statusType(value);

    return labels[type] ?? labels.unknown ?? type;
}

/*
|--------------------------------------------------------------------------
| GIFs and voice messages for the answers
|--------------------------------------------------------------------------
|
| Shared by the auto replies and the personal answers pages: a file goes
| up through the auto replies upload (AutoReplyController::upload()), a GIF
| out of Telegram is searched by the listener (AutoReplyTelegramGifs) - the
| search is left on the server and asked after until it is done.
|
| The page provides `endpoints` (upload, telegramGifs), `csrf`, `notify()`
| and `telegramGifPicked(owner, gif)` - where a picked GIF goes.
|
*/

async function request(url, csrf, method = 'GET', body = null) {
    const isForm = body instanceof FormData;

    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
        cache: 'no-store',
        body: body === null || isForm ? body : JSON.stringify(body),
    });

    let json = {};

    try {
        json = await response.json();
    } catch (_) {
        json = {};
    }

    if (!response.ok) {
        throw new Error(Object.values(json.errors || {}).flat()[0] || json.message || `HTTP ${response.status}`);
    }

    return json.data;
}

/**
 * A GIF or a voice message stored on the server: {file, name}. Put into
 * the answers with the page's next save.
 */
export function uploadAnswerMedia(endpoint, csrf, type, file) {
    const body = new FormData();

    body.append('type', type);
    body.append('file', file);

    return request(endpoint, csrf, 'POST', body);
}

/**
 * The Telegram GIF search, mixed into a page: state under `tg`, and the
 * dialog of partials/telegram-gif-picker.blade.php.
 *
 * @param {object} t the auto replies media translations
 */
export function telegramGifPicker(t) {
    return {
        /**
         * Whose GIFs it adds to, what is typed, what was found. `seq`
         * drops the answer of a search that a newer one replaced.
         */
        tg: {
            open: false, owner: null, query: '', loading: false, error: null, items: [], next: null, seq: 0, picking: null,
        },

        /** What was found last time stays: picking a second GIF needs no new search. */
        openTelegramGifs(owner) {
            const again = this.tg.items.length === 0 || this.tg.error;

            this.tg = { ...this.tg, open: true, owner };

            if (again) {
                this.searchTelegramGifs();
            }

            this.$nextTick(() => document.getElementById('tg-gif-search')?.focus());
        },

        closeTelegramGifs() {
            this.tg.open = false;
            this.tg.seq += 1;
            this.tg.loading = false;
        },

        tgPreviewUrl(file) {
            return `${this.endpoints.telegramGifs}/preview/${encodeURIComponent(file)}`;
        },

        /** A new search, or the next page of this one (`more`). */
        async searchTelegramGifs(more = false) {
            this.tg.seq += 1;

            const seq = this.tg.seq;
            const stale = () => seq !== this.tg.seq;

            this.tg.loading = true;
            this.tg.error = null;

            if (!more) {
                this.tg.items = [];
                this.tg.next = null;
            }

            try {
                const { id } = await request(this.endpoints.telegramGifs, this.csrf, 'POST', {
                    query: this.tg.query,
                    offset: more ? this.tg.next : '',
                });

                /* The listener looks every second; downloading the previews takes a few more. */
                const until = Date.now() + 30000;
                let answer = null;

                while (Date.now() < until) {
                    await new Promise((resolve) => { setTimeout(resolve, 700); });

                    if (stale()) {
                        return;
                    }

                    answer = await request(`${this.endpoints.telegramGifs}/${id}`, this.csrf);

                    if (answer.status !== 'pending') {
                        break;
                    }
                }

                if (stale()) {
                    return;
                }

                if (!answer || answer.status === 'pending') {
                    throw new Error(t.telegram_timeout);
                }

                if (answer.status === 'failed') {
                    throw new Error(t.telegram_failed.replace(':error', answer.error || ''));
                }

                const items = answer.items.map((item) => ({ ...item, request: id }));

                this.tg.items = more ? [...this.tg.items, ...items] : items;
                this.tg.next = answer.next_offset;
            } catch (error) {
                if (!stale()) {
                    this.tg.error = error?.message || t.failed;
                }
            } finally {
                if (!stale()) {
                    this.tg.loading = false;
                }
            }
        },

        async pickTelegramGif(item) {
            if (this.tg.picking) {
                return;
            }

            const { owner } = this.tg;

            this.tg.picking = item.id;

            try {
                const gif = await request(`${this.endpoints.telegramGifs}/${item.request}/pick`, this.csrf, 'POST', {
                    document: item.id,
                });

                this.telegramGifPicked(owner, { file: gif.file, name: gif.name, telegram: gif.telegram });
                this.closeTelegramGifs();
            } catch (error) {
                this.notify(error?.message || t.failed, false);
            } finally {
                this.tg.picking = null;
            }
        },
    };
}

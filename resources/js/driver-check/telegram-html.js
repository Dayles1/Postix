/*
|--------------------------------------------------------------------------
| Telegram HTML, for previews
|--------------------------------------------------------------------------
|
| Telegram renders a small set of tags; the panel previews a text the way
| the person will see it. Everything outside that set is shown as text, and
| a link only keeps an http(s) href - so a phrase typed in the panel can
| never run anything in the page that previews it.
|
*/

const TAGS = ['B', 'STRONG', 'I', 'EM', 'U', 'INS', 'S', 'STRIKE', 'DEL', 'CODE', 'PRE', 'A', 'BLOCKQUOTE'];

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export function telegramHtml(html) {
    const source = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html').body;
    const out = document.createElement('div');

    const copy = (from, to) => {
        from.childNodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                to.appendChild(document.createTextNode(node.textContent));

                return;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            if (!TAGS.includes(node.tagName)) {
                copy(node, to);

                return;
            }

            const el = document.createElement(node.tagName);

            if (node.tagName === 'A') {
                const href = node.getAttribute('href') || '';

                if (/^https?:\/\//i.test(href)) {
                    el.setAttribute('href', href);
                    el.setAttribute('target', '_blank');
                    el.setAttribute('rel', 'noopener noreferrer');
                }

                el.className = 'text-brand-600 underline dark:text-brand-400';
            }

            copy(node, el);
            to.appendChild(el);
        });
    };

    copy(source, out);

    return out.innerHTML;
}

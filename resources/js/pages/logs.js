import axios from 'axios';

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('logModal');
    const modalBody = document.getElementById('logModalBody');
    const modalTitle = document.getElementById('logModalTitle');
    const modalSubtitle = document.getElementById('logModalSubtitle');
    const labels = window.LOG_MODAL_LABELS || {};

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value == null ? '—' : String(value);
        return div.innerHTML;
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    const renderValue = (value) => {
        if (value === null || value === undefined || value === '') {
            return `<span class="text-gray-400 dark:text-gray-500">—</span>`;
        }

        if (typeof value === 'boolean') {
            return `<span>${value ? 'Ha' : 'Yo‘q'}</span>`;
        }

        if (Array.isArray(value)) {
            if (!value.length) {
                return `<span class="text-gray-400 dark:text-gray-500">—</span>`;
            }

            return `
                <div class="space-y-2">
                    ${value.map((item) => `
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            ${renderValue(item)}
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (typeof value === 'object') {
            const entries = Object.entries(value);

            if (!entries.length) {
                return `<span class="text-gray-400 dark:text-gray-500">—</span>`;
            }

            return `
                <div class="space-y-2">
                    ${entries.map(([key, val]) => `
                        <div class="flex flex-col gap-1 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                ${escapeHtml(key)}
                            </div>
                            <div class="text-sm text-gray-900 dark:text-white">
                                ${renderValue(val)}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        return `<span>${escapeHtml(value)}</span>`;
    };

    const renderFieldCard = (label, value) => `
        <div class="rounded-2xl border border-gray-200 p-3 dark:border-gray-700">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                ${escapeHtml(label)}
            </div>
            <div class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                ${renderValue(value)}
            </div>
        </div>
    `;

    const renderMeta = (meta) => `
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                ${renderFieldCard(labels.date, meta.created_at || '—')}
                ${renderFieldCard(labels.type || 'Type', meta.type_label || '—')}
                ${renderFieldCard(labels.event || 'Event', meta.action_label || '—')}
                ${renderFieldCard(labels.model || 'Model', meta.subject_type || '—')}
                ${renderFieldCard(labels.subject || 'Subject name', meta.subject_name || '—')}
                ${renderFieldCard(labels.subject_id || 'Model ID', meta.subject_id || '—')}
                ${renderFieldCard(labels.causer || 'Causer', `${meta.causer_name || '—'}${meta.causer_id ? ` (#${meta.causer_id})` : ''}`)}
            </div>
        </div>
    `;

    const renderSection = (title, payload) => {
        if (!payload) return '';

        return `
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                        ${escapeHtml(title)}
                    </h4>
                </div>

                <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            ${escapeHtml(labels.old)}
                        </div>
                        <div class="mt-2 text-sm text-gray-900 dark:text-white">
                            ${renderValue(payload.old)}
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            ${escapeHtml(labels.new)}
                        </div>
                        <div class="mt-2 text-sm text-gray-900 dark:text-white">
                            ${renderValue(payload.new)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    };

    const renderChanges = (changes) => {
        if (!changes || !Object.keys(changes).length) {
            return `
                <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    ${escapeHtml(labels.empty)}
                </div>
            `;
        }

        return `
            <div class="space-y-4">
                ${Object.entries(changes).map(([key, value]) => renderSection(key, value)).join('')}
            </div>
        `;
    };

    const renderModal = (data) => {
        modalTitle.textContent = data.title || 'Log';
        modalSubtitle.textContent = `${data.meta?.created_at || '—'}`;

        modalBody.innerHTML = `
            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        ${escapeHtml(labels.summary)}
                    </div>
                    <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        ${escapeHtml(data.summary || '—')}
                    </div>
                </div>

                ${renderMeta(data.meta || {})}

                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                            ${escapeHtml(labels.changes || 'Changes')}
                        </h4>
                    </div>

                    <div class="p-4">
                        ${renderChanges(data.changes)}
                    </div>
                </div>
            </div>
        `;
    };

    document.querySelectorAll('[data-log-show]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                button.disabled = true;
                button.classList.add('opacity-60');

                const response = await axios.get(button.dataset.url);

                if (response.data?.status === 'success') {
                    renderModal(response.data.data);
                    openModal();
                }
            } catch (error) {
                console.error(error);
            } finally {
                button.disabled = false;
                button.classList.remove('opacity-60');
            }
        });
    });

    document.querySelectorAll('[data-close-log-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.classList.contains('bg-black/50')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
});
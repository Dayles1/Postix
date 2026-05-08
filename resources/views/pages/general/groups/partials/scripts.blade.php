<script>
(function () {
    function qs(sel, root = document) {
        return root.querySelector(sel);
    }

    function qsa(sel, root = document) {
        return Array.from(root.querySelectorAll(sel));
    }

    function parseHtml(html) {
        return new DOMParser().parseFromString(html || '', 'text/html');
    }

    function appendBlock(container, html, listSelector, paginationSelector) {
        const doc = parseHtml(html);

        const newList = doc.querySelector(listSelector);
        const currentList = container.querySelector(listSelector);

        if (newList && currentList) {
            Array.from(newList.children).forEach(child => currentList.appendChild(child));
        } else {
            container.innerHTML = html || '';
            return;
        }

        const currentPagination = container.querySelector(paginationSelector);
        const newPagination = doc.querySelector(paginationSelector);

        if (currentPagination) currentPagination.remove();
        if (newPagination) container.appendChild(newPagination);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const peersContainer = qs('#peers-container');
        const peersUrl = peersContainer?.dataset.peersUrl;
        const peerMessagesUrl = peersContainer?.dataset.peerMessagesUrl;

        const statusFilter = qs('#status-filter');
        const peerSearch = qs('#peer-search');

        const copyBtn = qs('#copyMessageBtn');
        const toggleBtn = qs('#toggleFullMessageBtn');
        const fullMessagePre = qs('#fullMessageText');
        const overlay = qs('#fullMessageOverlay');

        const modal = qs('#updateModal');
        const openBtn = qs('#openUpdateModalBtn');
        const closeBtn = qs('#closeUpdateModalBtn');
        const cancelBtn = qs('#cancelUpdateBtn');
        const textarea = qs('#updateMessageInput');
        const charCount = qs('#charCount');

        const removeSelectedBtn = qs('#removeSelectedPeersBtn');
        const selectedPeersCount = qs('#selectedPeersCount');
        const peerSelectionHint = qs('#peerSelectionHint');

        const loadingText = @json(__('messages.loading'));
        const copiedText = @json(__('messages.copied'));
        const copyFailedText = @json(__('messages.copy_failed'));

        const selectHintText = @json(__('messages.op_show2.select_hint'));
        const selectedCountTemplate = @json(__('messages.op_show2.selected_count', ['count' => '__COUNT__']));
        const removeSelectedText = @json(__('messages.op_show2.remove_selected'));

        const csrfMissingText = @json(__('messages.remove_peer.csrf_missing'));
        const serverErrorText = @json(__('messages.remove_peer.server_error'));
        const removingText = @json(__('messages.remove_peer.removing'));
        const removedText = @json(__('messages.remove_peer.removed'));
        const removeFailedText = @json(__('messages.remove_peer.remove_failed'));
        const peersLoadFailedText = @json(__('messages.remove_peer.peers_load_failed'));
        const messageLoadFailedText = @json(__('messages.remove_peer.message_load_failed'));

        const bulkRemoveTitle = @json(__('messages.op_show2.bulk_remove_title'));
        const bulkRemoveDescription = @json(__('messages.op_show2.bulk_remove_description'));
        const singleRemoveTitle = @json(__('messages.remove_peer.modal_title'));
        const singleRemoveDescription = @json(__('messages.remove_peer.modal_description'));

        const PER_PAGE_PEERS = 50;
        const PER_PAGE_MESSAGES = 24;

        let searchTimer = null;
        let loadingPeers = false;

        const removeModal = qs('#peerRemoveModal');
        const removeTitleEl = qs('#peerRemoveTitle');
        const removeDescriptionEl = qs('#peerRemoveDescription');
        const removeSummaryEl = qs('#peerRemoveSummary');
        const removeNameEl = qs('#peerRemoveName');
        const removeCancelBtn = qs('#peerRemoveCancel');
        const removeConfirmBtn = qs('#peerRemoveConfirm');

        let bodyLockCount = 0;
        let savedScrollY = 0;

        let removeMode = 'single'; // single | bulk
        let removePayload = null;
        let removeTargetCards = [];
        let selectedPeersMap = new Map();

        function lockBodyScroll() {
            bodyLockCount += 1;
            if (bodyLockCount !== 1) return;

            savedScrollY = window.scrollY || document.documentElement.scrollTop || 0;

            document.body.style.position = 'fixed';
            document.body.style.top = `-${savedScrollY}px`;
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
            document.body.classList.add('overflow-hidden');
        }

        function unlockBodyScroll() {
            if (bodyLockCount > 0) bodyLockCount -= 1;
            if (bodyLockCount !== 0) return;

            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            document.body.classList.remove('overflow-hidden');
            window.scrollTo(0, savedScrollY || 0);
        }

        function showToast(message, type = 'success') {
            let host = document.getElementById('toast-host');

            if (!host) {
                host = document.createElement('div');
                host.id = 'toast-host';
                host.style.position = 'fixed';
                host.style.top = '20px';
                host.style.right = '20px';
                host.style.zIndex = '999999';
                host.style.display = 'flex';
                host.style.flexDirection = 'column';
                host.style.gap = '10px';
                document.body.appendChild(host);
            }

            const toast = document.createElement('div');

            toast.style.padding = '10px 14px';
            toast.style.borderRadius = '10px';
            toast.style.color = '#fff';
            toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
            toast.style.fontSize = '14px';
            toast.style.maxWidth = '340px';
            toast.style.wordBreak = 'break-word';
            toast.style.background = type === 'success' ? '#16a34a' : '#dc2626';
            toast.textContent = message;

            host.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                toast.style.transition = 'all .2s ease';
                setTimeout(() => toast.remove(), 200);
            }, 3000);
        }

        async function copyText(text) {
            try {
                await navigator.clipboard.writeText(text);
                showToast(copiedText, 'success');
            } catch (e) {
                showToast(copyFailedText, 'error');
            }
        }

        function updateCharCounter() {
            if (!textarea || !charCount) return;
            charCount.textContent = String((textarea.value || '').length);
        }

        function openUpdateModal() {
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            lockBodyScroll();
            updateCharCounter();
            setTimeout(() => textarea?.focus(), 50);
        }

        function closeUpdateModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            unlockBodyScroll();
        }

        function selectionKey(peer, catalogId) {
            return `${catalogId || ''}::${peer || ''}`;
        }

        function getSelectedEntries() {
            return Array.from(selectedPeersMap.values());
        }

        function updateSelectedUI() {
            const count = selectedPeersMap.size;

            if (selectedPeersCount) {
                selectedPeersCount.textContent = String(count);
            }

            if (removeSelectedBtn) {
                removeSelectedBtn.classList.toggle('hidden', count === 0);
                removeSelectedBtn.disabled = count === 0;
                const label = removeSelectedBtn.querySelector('span:nth-of-type(2)');
                if (label) label.textContent = removeSelectedText;
            }

            if (peerSelectionHint) {
                peerSelectionHint.textContent = count > 0
                    ? selectedCountTemplate.replace('__COUNT__', String(count))
                    : selectHintText;
            }
        }

        function setCardSelectedState(card, selected) {
            if (!card) return;

            card.classList.toggle('ring-2', selected);
            card.classList.toggle('ring-red-500/30', selected);
            card.classList.toggle('bg-red-50/30', selected);
            card.classList.toggle('dark:bg-red-950/10', selected);
            card.classList.toggle('border-red-200', selected);
            card.classList.toggle('dark:border-red-900/40', selected);
        }

        function setSelectVisual(cb, selected, removed = false) {
            const label = cb.closest('.peer-select-label');
            const pill = label?.querySelector('[data-peer-select-pill]');
            const icon = label?.querySelector('[data-peer-select-icon]');
            const text = label?.querySelector('[data-peer-select-text]');

            if (!pill || !icon || !text) return;

            if (removed) {
                pill.className = [
                    'peer-select-pill',
                    'inline-flex items-center gap-2 px-3 py-2 rounded-xl border',
                    'border-gray-200 dark:border-gray-700',
                    'bg-gray-100 dark:bg-gray-700',
                    'text-gray-500 dark:text-gray-300',
                    'shadow-sm transition cursor-not-allowed opacity-80'
                ].join(' ');

                icon.className = [
                    'peer-select-icon',
                    'inline-flex h-4 w-4 items-center justify-center rounded-md border border-current bg-transparent text-gray-400'
                ].join(' ');

                text.className = 'peer-select-text text-xs font-medium text-gray-500 dark:text-gray-300';
                text.textContent = @json(__('messages.remove_peer.removed'));

                return;
            }

            if (selected) {
                pill.className = [
                    'peer-select-pill',
                    'inline-flex items-center gap-2 px-3 py-2 rounded-xl border',
                    'border-red-600 bg-red-600 text-white shadow-md transition'
                ].join(' ');

                icon.className = [
                    'peer-select-icon',
                    'inline-flex h-4 w-4 items-center justify-center rounded-md border border-white bg-transparent text-white'
                ].join(' ');

                text.className = 'peer-select-text text-xs font-medium text-white';
            } else {
                pill.className = [
                    'peer-select-pill',
                    'inline-flex items-center gap-2 px-3 py-2 rounded-xl border',
                    'border-gray-200 dark:border-gray-700',
                    'bg-white dark:bg-gray-900',
                    'text-gray-600 dark:text-gray-300',
                    'shadow-sm transition',
                    'hover:border-red-300 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30'
                ].join(' ');

                icon.className = [
                    'peer-select-icon',
                    'inline-flex h-4 w-4 items-center justify-center rounded-md border border-current bg-transparent text-transparent'
                ].join(' ');

                text.className = 'peer-select-text text-xs font-medium text-gray-600 dark:text-gray-300';
                text.textContent = @json(__('messages.op_show2.select'));
            }
        }

        function syncSelectionFromCheckbox(cb) {
            const peer = cb.dataset.peer || '';
            const catalogId = cb.dataset.catalogId || '';
            const card = cb.closest('.peer-item');
            const key = selectionKey(peer, catalogId);

            if (cb.checked) {
                selectedPeersMap.set(key, { peer, catalogId, card });
            } else {
                selectedPeersMap.delete(key);
            }

            setSelectVisual(cb, cb.checked, false);
            setCardSelectedState(card, cb.checked);
            updateSelectedUI();
        }

        function clearSelections() {
            selectedPeersMap.clear();

            qsa('.peer-select-checkbox').forEach(cb => {
                if (cb.disabled) return;
                cb.checked = false;
                setSelectVisual(cb, false, false);
            });

            qsa('.peer-item').forEach(card => {
                setCardSelectedState(card, false);
            });

            updateSelectedUI();
        }

        function bindPeerSelection(root = document) {
            qsa('.peer-select-checkbox', root).forEach(cb => {
                if (cb.dataset.bound === '1') return;
                cb.dataset.bound = '1';

                cb.addEventListener('click', function (e) {
                    e.stopPropagation();
                });

                cb.addEventListener('change', function () {
                    syncSelectionFromCheckbox(cb);
                });

                const peer = cb.dataset.peer || '';
                const catalogId = cb.dataset.catalogId || '';
                const card = cb.closest('.peer-item');
                const key = selectionKey(peer, catalogId);

                if (cb.disabled) {
                    setSelectVisual(cb, false, true);
                    return;
                }

                if (selectedPeersMap.has(key)) {
                    cb.checked = true;
                    setSelectVisual(cb, true, false);
                    setCardSelectedState(card, true);
                } else {
                    setSelectVisual(cb, false, false);
                    setCardSelectedState(card, false);
                }
            });

            updateSelectedUI();
        }

        function openRemoveModal({ mode, peer = '', peers = [], payload = {}, cards = [] }) {
            removeMode = mode;
            removePayload = payload;
            removeTargetCards = cards;

            if (removeTitleEl) {
                removeTitleEl.textContent = mode === 'bulk' ? bulkRemoveTitle : singleRemoveTitle;
            }

            if (removeDescriptionEl) {
                removeDescriptionEl.textContent = mode === 'bulk' ? bulkRemoveDescription : singleRemoveDescription;
            }

            if (removeSummaryEl) {
                if (mode === 'bulk') {
                    const cleanPeers = peers.filter(Boolean);
                    const preview = cleanPeers.slice(0, 3).join(', ');
                    const moreText = cleanPeers.length > 3 ? ` va yana ${cleanPeers.length - 3} ta` : '';

                    removeSummaryEl.innerHTML = `
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">${cleanPeers.length} ta peer tanlandi</span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 break-words">
                            ${preview}${moreText}
                        </div>
                    `;
                } else {
                    removeSummaryEl.innerHTML = `
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            {!! __('messages.remove_peer.selected_text', [
                                'peer' => '<span id="peerRemoveName" class="font-semibold text-gray-900 dark:text-gray-100"></span>'
                            ]) !!}
                        </div>
                    `;

                    requestAnimationFrame(() => {
                        const nameEl = qs('#peerRemoveName', removeSummaryEl);
                        if (nameEl) nameEl.textContent = peer || '-';
                    });
                }
            }

            if (removeModal) {
                removeModal.classList.remove('hidden');
                removeModal.setAttribute('aria-hidden', 'false');
            }

            lockBodyScroll();
        }

        function closeRemoveModal() {
            if (removeModal) {
                removeModal.classList.add('hidden');
                removeModal.setAttribute('aria-hidden', 'true');
            }

            unlockBodyScroll();
            removeMode = 'single';
            removePayload = null;
            removeTargetCards = [];
        }

        function unselectCardFromMap(card) {
            if (!card) return;

            const peer = card.dataset.peer || '';
            const catalogId = card.dataset.catalogId || '';
            const key = selectionKey(peer, catalogId);
            selectedPeersMap.delete(key);
        }

        function markPeerAsRemoved(card) {
            if (!card) return;

            const removeBtn = card.querySelector('.peer-remove-btn');
            const statusBadge = card.querySelector('[data-peer-catalog-status]');
            const checkbox = card.querySelector('.peer-select-checkbox');
            const pill = card.querySelector('[data-peer-select-pill]');
            const icon = card.querySelector('[data-peer-select-icon]');
            const text = card.querySelector('[data-peer-select-text]');

            if (removeBtn) {
                removeBtn.disabled = true;
                removeBtn.classList.add('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
                removeBtn.classList.remove('hover:bg-red-100', 'dark:hover:bg-red-950/50');
                removeBtn.innerHTML = '<span>✓</span><span>' + removedText + '</span>';
                removeBtn.title = removedText;
            }

            if (statusBadge) {
                statusBadge.dataset.inCatalog = '0';
                statusBadge.textContent = removedText;
                statusBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
            }

            if (checkbox) {
                checkbox.checked = false;
                checkbox.disabled = true;
            }

            if (pill) {
                pill.className = 'peer-select-pill inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 shadow-sm transition cursor-not-allowed opacity-80';
            }

            if (icon) {
                icon.className = 'peer-select-icon inline-flex h-4 w-4 items-center justify-center rounded-md border border-current bg-transparent text-gray-400';
            }

            if (text) {
                text.className = 'peer-select-text text-xs font-medium text-gray-500 dark:text-gray-300';
                text.textContent = removedText;
            }

            unselectCardFromMap(card);
            setCardSelectedState(card, false);
            updateSelectedUI();
        }

        openBtn?.addEventListener('click', openUpdateModal);
        closeBtn?.addEventListener('click', closeUpdateModal);
        cancelBtn?.addEventListener('click', closeUpdateModal);

        modal?.addEventListener('click', function (e) {
            if (e.target === modal || e.target?.hasAttribute('data-close-modal')) {
                closeUpdateModal();
            }
        });

        textarea?.addEventListener('input', updateCharCounter);
        updateCharCounter();

        removeModal?.addEventListener('click', function (e) {
            if (e.target === removeModal) closeRemoveModal();
        });

        removeCancelBtn?.addEventListener('click', closeRemoveModal);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            if (modal && !modal.classList.contains('hidden')) {
                closeUpdateModal();
            }

            if (removeModal && !removeModal.classList.contains('hidden')) {
                closeRemoveModal();
            }
        });

        copyBtn?.addEventListener('click', async function () {
            const text = fullMessagePre?.innerText?.trim() || '';
            await copyText(text);
        });

        toggleBtn?.addEventListener('click', function () {
            const expanded = this.getAttribute('data-expanded') === 'true';

            if (!expanded) {
                fullMessagePre.style.maxHeight = 'none';
                if (overlay) overlay.style.display = 'none';
                this.setAttribute('data-expanded', 'true');
                this.innerHTML = '<span>🔎</span><span>{{ __('messages.op_show.collapse') }}</span>';
            } else {
                fullMessagePre.style.maxHeight = '11rem';
                if (overlay) overlay.style.display = 'block';
                this.setAttribute('data-expanded', 'false');
                this.innerHTML = '<span>🔍</span><span>{{ __('messages.op_show.expand') }}</span>';
            }
        });

        async function loadPeerMessages(peerItem) {
            const wrapper = peerItem.querySelector('.peer-messages-wrapper');
            if (!wrapper || wrapper.dataset.loaded === '1') return;

            const peer = wrapper.dataset.peer || peerItem.dataset.peer || '';
            if (!peer || !peerMessagesUrl) return;

            wrapper.innerHTML = `
                <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
                    ${loadingText}
                </div>
            `;

            const url = new URL(peerMessagesUrl, window.location.origin);
            url.searchParams.set('peer', peer);
            url.searchParams.set('page', 1);
            url.searchParams.set('per_page', PER_PAGE_MESSAGES);

            const status = statusFilter?.value || '';
            if (status) url.searchParams.set('status', status);

            try {
                const res = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await res.json();
                wrapper.innerHTML = data.html || '';
                wrapper.dataset.loaded = '1';

                bindMessagePagination(wrapper);
            } catch (err) {
                console.error(err);
                wrapper.innerHTML = `
                    <div class="p-4 text-sm text-red-600 dark:text-red-300">
                        ${messageLoadFailedText}
                    </div>
                `;
            }
        }

        async function loadPeers(page = 1, append = false) {
            if (!peersContainer || !peersUrl || loadingPeers) return;

            loadingPeers = true;

            const url = new URL(peersUrl, window.location.origin);
            url.searchParams.set('page', page);
            url.searchParams.set('per_page', PER_PAGE_PEERS);

            const status = statusFilter?.value || '';
            const search = peerSearch?.value || '';

            if (status) url.searchParams.set('status', status);
            if (search.trim()) url.searchParams.set('search', search.trim());

            if (!append) {
                peersContainer.innerHTML = `
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 text-sm text-gray-500 dark:text-gray-400">
                        ${loadingText}
                    </div>
                `;
                clearSelections();
            } else {
                const moreBtn = peersContainer.querySelector('.peer-page-btn');
                if (moreBtn) {
                    moreBtn.disabled = true;
                    moreBtn.textContent = loadingText;
                }
            }

            try {
                const res = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await res.json();

                if (append && peersContainer.querySelector('.peers-list')) {
                    appendBlock(peersContainer, data.html || '', '.peers-list', '.peers-pagination');
                } else {
                    peersContainer.innerHTML = data.html || '';
                }

                bindPeerToggles();
                bindPeerActions();
                bindPeerSelection();
                bindPeerPagination();
            } catch (err) {
                console.error(err);
                if (!append) {
                    peersContainer.innerHTML = `
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-red-200 dark:border-red-900/50 p-6 text-sm text-red-600 dark:text-red-300">
                            ${peersLoadFailedText}
                        </div>
                    `;
                }
            } finally {
                loadingPeers = false;
            }
        }

        function bindPeerToggles() {
            qsa('.peer-toggle').forEach(btn => {
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';

                btn.addEventListener('click', async function (e) {
                    if (e.target.closest('a, button, [data-no-toggle="1"], [data-peer-select-wrap]')) return;

                    const card = btn.closest('.peer-item');
                    const wrapper = card?.querySelector('.peer-messages-wrapper');
                    if (!card || !wrapper) return;

                    const isHidden = wrapper.classList.contains('hidden');

                    if (isHidden) {
                        wrapper.classList.remove('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                        await loadPeerMessages(card);
                    } else {
                        wrapper.classList.add('hidden');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });

                btn.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    if (e.target.closest('a, button, [data-no-toggle="1"], [data-peer-select-wrap]')) return;
                    e.preventDefault();
                    btn.click();
                });
            });
        }

        function bindPeerActions() {
            qsa('.peer-copy-btn').forEach(btn => {
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';

                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    await copyText(btn.dataset.copyText || '');
                });
            });

            qsa('.peer-remove-btn').forEach(btn => {
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const peer = btn.dataset.peer || '';
                    const catalogId = btn.dataset.catalogId || '';
                    const card = btn.closest('.peer-item');

                    openRemoveModal({
                        mode: 'single',
                        peer,
                        payload: {
                            catalog_id: catalogId,
                            peer: peer
                        },
                        cards: card ? [card] : []
                    });
                });
            });
        }

        function bindPeerPagination(root = document) {
            qsa('.peer-page-btn', root).forEach(btn => {
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const page = parseInt(btn.dataset.page || '1', 10);
                    loadPeers(page, true);
                });
            });
        }

        function bindMessagePagination(root) {
            qsa('.message-page-btn', root).forEach(btn => {
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';

                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const peer = btn.dataset.peer || '';
                    const page = parseInt(btn.dataset.page || '1', 10);
                    const wrapper = btn.closest('.peer-messages-wrapper');
                    if (!peer || !wrapper || !peerMessagesUrl) return;

                    const url = new URL(peerMessagesUrl, window.location.origin);
                    url.searchParams.set('peer', peer);
                    url.searchParams.set('page', page);
                    url.searchParams.set('per_page', PER_PAGE_MESSAGES);

                    const status = statusFilter?.value || '';
                    if (status) url.searchParams.set('status', status);

                    const list = wrapper.querySelector('.messages-list');
                    const pagination = wrapper.querySelector('.messages-pagination');

                    if (pagination) {
                        const oldBtn = pagination.querySelector('.message-page-btn');
                        if (oldBtn) {
                            oldBtn.disabled = true;
                            oldBtn.textContent = loadingText;
                        }
                    }

                    try {
                        const res = await fetch(url.toString(), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        const data = await res.json();

                        if (list) {
                            appendBlock(wrapper, data.html || '', '.messages-list', '.messages-pagination');
                        } else {
                            wrapper.innerHTML = data.html || '';
                        }

                        wrapper.dataset.loaded = '1';
                        bindMessagePagination(wrapper);
                    } catch (err) {
                        console.error(err);
                        wrapper.innerHTML = `
                            <div class="p-4 text-sm text-red-600 dark:text-red-300">
                                ${messageLoadFailedText}
                            </div>
                        `;
                    }
                });
            });
        }

        removeSelectedBtn?.addEventListener('click', function () {
            const selected = getSelectedEntries().filter(item => item.card);

            if (!selected.length) {
                showToast(@json(__('messages.op_show2.select_at_least_one')), 'error');
                return;
            }

            const firstCatalogId = selected[0].catalogId || '';
            if (!firstCatalogId) {
                showToast(@json(__('messages.op_show2.catalog_not_found')), 'error');
                return;
            }

            const mismatch = selected.some(item => String(item.catalogId || '') !== String(firstCatalogId));
            if (mismatch) {
                showToast(@json(__('messages.op_show2.same_catalog_only')), 'error');
                return;
            }

            const peers = selected.map(item => item.peer).filter(Boolean);

            openRemoveModal({
                mode: 'bulk',
                peers,
                payload: {
                    catalog_id: firstCatalogId,
                    peers: peers
                },
                cards: selected.map(item => item.card).filter(Boolean)
            });
        });

        removeConfirmBtn?.addEventListener('click', async function () {
            if (!removePayload) {
                closeRemoveModal();
                return;
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!token) {
                showToast(csrfMissingText, 'error');
                return;
            }

            removeConfirmBtn.disabled = true;
            const originalText = removeConfirmBtn.textContent;
            removeConfirmBtn.textContent = removingText;

            const endpoint = removeMode === 'bulk'
                ? @json(route('admin.catalogs.remove-peers'))
                : @json(route('admin.catalogs.remove-peer'));

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(removePayload)
                });

                let data = {};

                try {
                    data = await response.json();
                } catch (e) {}

                if (!response.ok) {
                    const msg =
                        data?.message ||
                        data?.error ||
                        (data?.errors ? Object.values(data.errors).flat().join(', ') : null) ||
                        serverErrorText;

                    throw new Error(msg);
                }

                if (data.success === false) {
                    throw new Error(data.message || removeFailedText);
                }

                showToast(data.message || removedText, 'success');

                if (removeMode === 'bulk') {
                    removeTargetCards.forEach(markPeerAsRemoved);
                    clearSelections();
                } else {
                    markPeerAsRemoved(removeTargetCards[0]);
                }

                closeRemoveModal();
            } catch (err) {
                console.error(err);
                showToast(err.message || removeFailedText, 'error');
            } finally {
                removeConfirmBtn.disabled = false;
                removeConfirmBtn.textContent = originalText;
            }
        });

        statusFilter?.addEventListener('change', function () {
            loadPeers(1, false);
        });

        peerSearch?.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadPeers(1, false), 250);
        });

        loadPeers(1, false);
    });
})();
</script>
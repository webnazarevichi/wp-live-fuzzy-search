document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('wplfs-input');
    const dropdownResults = document.getElementById('wplfs-results');
    const wrapper = document.getElementById('wplfs-wrapper');
    const clearBtn = wrapper?.querySelector('[data-wplfs-clear]');
    const searchBtn = wrapper?.querySelector('[data-wplfs-search]');
    if (!input) return;

    const ui = {
        emptyText: wrapper?.dataset.emptyText || 'Введите хотя бы 2 символа для поиска',
        popularTitle: wrapper?.dataset.popularTitle || 'Часто ищут',
        allResultsText: wrapper?.dataset.allResultsText || 'Все результаты',
        noResultsText: wrapper?.dataset.noResultsText || 'Ничего не найдено',
        hasPopularQueries: wrapper?.dataset.hasPopularQueries === '1',
        popularQueries: (wrapper?.dataset.popularQueries || '').split('|').map((s) => s.trim()).filter(Boolean),
    };

    let fuse = null;
    let indexData = null;
    let isLoading = false;
    
    // Получаем URL страницы результатов из локализованных данных
    const searchPageUrl = wplfs.search_page_url || wplfs.results_page_url || wplfs.results_page || '/?s=';

    const buildSearchUrl = (query, ids = []) => {
        const url = new URL(searchPageUrl, window.location.origin);
        url.searchParams.set('s', query);

        if (ids.length) {
            url.searchParams.set('wplfs_ids', ids.join(','));
        }

        return url.toString();
    };

    const goToSearchPage = () => {
        const query = input.value.trim();
        if (!query) return;

        const items = performSearch(query);
        window.location.href = buildSearchUrl(query, getPostIds(items));
    };

    const syncActionButtons = () => {
        if (!clearBtn) return;
        clearBtn.hidden = input.value.trim().length === 0;
    };

    const getPostIds = (items) => {
        return items
            .map((item) => parseInt(item.id, 10))
            .filter((id) => Number.isInteger(id) && id > 0);
    };

    const loadIndex = async () => {
        if (indexData || isLoading) return indexData;
        isLoading = true;

        try {
            const response = await fetch(wplfs.rest_url, {
                headers: {
                    'X-WP-Nonce': wplfs.nonce
                }
            });
            if (!response.ok) throw new Error('Failed to load index');

            indexData = await response.json();

            fuse = new Fuse(indexData, {
                keys: [
                    { name: 'title', weight: 0.5 },
                    { name: 'terms', weight: 0.07 },
                    { name: 'phrases', weight: 0.03 },
                ],
                threshold: 0.4,
                distance: 100,
                minMatchCharLength: 2,
                includeScore: true,
                ignoreLocation: true,
                shouldSort: false // Отключаем внутреннюю сортировку Fuse.js, чтобы сохранить порядок из JSON
            });
        } catch (e) {
            console.error('Ошибка загрузки индекса', e);
        } finally {
            isLoading = false;
        }
        return indexData;
    };

    const displayDropdownResults = (results) => {
        if (!dropdownResults) return;
        
        dropdownResults.innerHTML = '';
        if (wrapper) wrapper.classList.add('is-open');

        if (results.length === 0) {
            dropdownResults.innerHTML = `<div class="wplfs-no-results">${escapeHtml(ui.noResultsText)}</div>`;
            return;
        }

        const container = document.createElement('div');
        container.className = 'wplfs-results-container';

        const list = document.createElement('div');
        list.className = 'wplfs-results-list';

        results.slice(0, 10).forEach(item => {  
            const thumbHtml = item.thumb ? `
                <div class="wplfs-result-content-img">
                    <img src="${item.thumb}" 
                        alt="${escapeHtml(item.title || '')}" 
                        decoding="async" 
                        loading="lazy">
                </div>
            ` : '';
            const typeBadge = item.type ? `<span class="wplfs-result-type wplfs-result-type-${item.type}">${escapeHtml(item.type)}</span>` : '';

            const a = document.createElement('a');
            a.href = item.url;
            a.className = 'wplfs-result-item';
            a.innerHTML = `
                <div class="wplfs-result-content">
                    ${thumbHtml}
                    <div class="wplfs-result-content-txt">
                        <span class="wplfs-result-content-title">${escapeHtml(item.title)}</span>
                        ${typeBadge}
                    </div>
                </div>
            `;
            list.appendChild(a);

        });

        container.appendChild(list);

        if (results.length > 10) {
            const moreLink = document.createElement('a');
            // Используем полный набор ID для search page.
            moreLink.href = buildSearchUrl(input.value, getPostIds(results));
            moreLink.className = 'wplfs-more-btn';
            moreLink.textContent = `${ui.allResultsText} (${results.length}) →`;
            container.appendChild(moreLink);
        }

        dropdownResults.appendChild(container);
    };

    const performSearch = (query) => {
        if (!fuse) return [];

        const searchResults = fuse.search(query);
        return searchResults.map(r => r.item);
    };

    const renderPopularQueries = () => {
        if (!dropdownResults || !ui.hasPopularQueries || ui.popularQueries.length === 0) return;

        dropdownResults.innerHTML = '';
        if (wrapper) wrapper.classList.add('is-open');

        const box = document.createElement('div');
        box.className = 'wplfs-popular-box';

        const title = document.createElement('div');
        title.className = 'wplfs-popular-title';
        title.textContent = ui.popularTitle;
        box.appendChild(title);

        const tags = document.createElement('div');
        tags.className = 'wplfs-popular-tags';

        ui.popularQueries.forEach((phrase) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wplfs-popular-tag';
            btn.setAttribute('data-wplfs-preserve-open', '1');
            btn.textContent = phrase;
            btn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (wrapper) wrapper.classList.add('is-open');
            });
            btn.addEventListener('click', () => {
                input.value = phrase;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                if (wrapper) wrapper.classList.add('is-open');
                const items = performSearch(phrase);
                displayDropdownResults(items);
            });
            tags.appendChild(btn);
        });

        box.appendChild(tags);
        dropdownResults.appendChild(box);
    };
    
    // Функция для безопасности от XSS
    const escapeHtml = (str) => {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    };

    let timeout = null;

    input.addEventListener('input', () => {
        clearTimeout(timeout);
        const query = input.value.trim();

        if (query.length < 2) {
            if (ui.hasPopularQueries && query.length === 0) {
                renderPopularQueries();
                syncActionButtons();
                return;
            }

            if (dropdownResults) {
                dropdownResults.innerHTML = `<div class="wplfs-no-results">${escapeHtml(ui.emptyText)}</div>`;
                if (wrapper) wrapper.classList.add('is-open');
            }
            syncActionButtons();
            return;
        }

        if (dropdownResults) {
            timeout = setTimeout(async () => {
                if (!indexData && isLoading) {
                    await loadIndex();
                }
                const items = performSearch(query);
                displayDropdownResults(items);
            }, wplfs.debounce_ms || 350);
        }
        syncActionButtons();
    });

    if (dropdownResults) {
        document.addEventListener('click', (e) => {
            if (e.target instanceof Element && e.target.closest('[data-wplfs-preserve-open="1"]')) {
                return;
            }

            if (wrapper && !wrapper.contains(e.target)) {
                wrapper.classList.remove('is-open');
            }
        });

        input.addEventListener('focus', () => {
            loadIndex();
            if (!input.value.trim() && ui.hasPopularQueries) {
                renderPopularQueries();
            } else if (input.value.trim().length < 2) {
                dropdownResults.innerHTML = `<div class="wplfs-no-results">${escapeHtml(ui.emptyText)}</div>`;
                if (wrapper) wrapper.classList.add('is-open');
            } else {
                const items = performSearch(input.value.trim());
                displayDropdownResults(items);
            }
            syncActionButtons();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && wrapper) {
                wrapper.classList.remove('is-open');
            }
        });

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && input.value.trim().length >= 2) {
                goToSearchPage();
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                input.focus();
                syncActionButtons();
                if (ui.hasPopularQueries) {
                    renderPopularQueries();
                } else {
                    dropdownResults.innerHTML = `<div class="wplfs-no-results">${escapeHtml(ui.emptyText)}</div>`;
                    if (wrapper) wrapper.classList.add('is-open');
                }
            });
        }

        if (searchBtn) {
            searchBtn.addEventListener('click', goToSearchPage);
        }
    }

    // Предзагрузка при наведении или касании (для мобильных)
    if (wrapper) {
        wrapper.addEventListener('mouseenter', loadIndex, { once: true });
        wrapper.addEventListener('touchstart', loadIndex, { once: true, passive: true });
    }
});

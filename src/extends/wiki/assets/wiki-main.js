document.addEventListener('DOMContentLoaded', function() {
    const categoryList = document.getElementById('category-list');
    const wikiGrid = document.getElementById('wiki-grid');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadingIndicator = document.getElementById('loading');
    const searchInput = document.getElementById('wiki-search-input');

    let currentTag = null;
    let currentPage = 1;
    let currentQuery = '';
    let debounceTimer = null;

    // Extract q parameter from current URI
    const urlParams = new URLSearchParams(window.location.search);
    currentQuery = urlParams.get('q') || '';
    if (currentQuery) searchInput.value = currentQuery;

    // Helper to set active category
    function setActiveCategory(tagId) {
        categoryList.querySelectorAll('a').forEach(link => {
            link.classList.toggle('active', link.dataset.tag === tagId);
        });
    }

    // Function to highlight query in text
    function highlightText(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    // Load entries (for category click, search, or load more)
    function loadEntries(tagId, page = 1, append = false, q = currentQuery) {
        loadingIndicator.style.display = 'block';
        loadMoreBtn.style.display = 'none';

        let url;
        if (q) {
            url = new URL('/wiki/search', window.location.origin);
            url.searchParams.append('q', q);
            if (tagId) url.searchParams.append('tid', tagId);
            url.searchParams.append('page', page);
        } else {
            url = new URL(`/wiki/${tagId}`, window.location.origin);
            url.searchParams.append('page', page);
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                loadingIndicator.style.display = 'none';
                if (!append) wikiGrid.innerHTML = '';

                data.entries.forEach(wiki => {
                    const authors = wiki.authors.map(a => a.name).join(', ');
                    const title = highlightText(wiki.title, q);
                    const summary = highlightText(wiki.summary, q);
                    const card = document.createElement('div');
                    card.className = 'wiki-card';
                    card.innerHTML = `
                        <h3>${title}</h3>
                        <p>${summary}</p>
                        <p class="authors"><small>By: ${authors}</small></p>
                        <a href="/wiki/${wiki.slug}">Read More</a>
                    `;
                    wikiGrid.appendChild(card);
                });

                loadMoreBtn.style.display = data.hasMore ? 'block' : 'none';
                loadMoreBtn.dataset.page = page;
            })
            .catch(err => {
                loadingIndicator.style.display = 'none';
                console.error(err);
                wikiGrid.innerHTML = `<p style="color:red;">Failed to load entries. Please try again.</p>`;
            });
    }

    // Category click
    categoryList.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            currentTag = this.dataset.tag;
            currentPage = 1;
            wikiGrid.innerHTML = '';
            setActiveCategory(currentTag);
            loadEntries(currentTag, currentPage);

            wikiGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Clear search
            currentQuery = '';
            searchInput.value = '';
            const params = new URLSearchParams(window.location.search);
            params.delete('q');
            window.history.replaceState({}, '', `${window.location.pathname}?${params}`);
        });
    });

    // Debounced live search
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        currentQuery = this.value.trim();
        currentPage = 1;

        debounceTimer = setTimeout(() => {
            wikiGrid.innerHTML = '';
            loadEntries(currentTag, currentPage, false, currentQuery);

            // Update URL
            const params = new URLSearchParams(window.location.search);
            if (currentQuery) {
                params.set('q', currentQuery);
            } else {
                params.delete('q');
            }
            window.history.replaceState({}, '', `${window.location.pathname}?${params}`);
        }, 300); // 300ms debounce
    });

    // Load more
    loadMoreBtn.addEventListener('click', function() {
        currentPage++;
        loadEntries(currentTag, currentPage, true, currentQuery);
    });

    // Auto-click first category on page load
    setTimeout(() => {
        const firstCat = categoryList.querySelector('a');
        if (firstCat) firstCat.click();
    }, 500);
});

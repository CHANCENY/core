function initPageSearch(wrapper) {
    const searchInput = wrapper.querySelector('input[type="text"]');
    const hiddenPid = wrapper.querySelector('input[type="hidden"]');
    const resultsList = wrapper.querySelector('.search-results');
    const spinner = wrapper.querySelector('.search-spinner');
    let debounceTimeout = null;

    async function fetchPages(query) {
        try {
            spinner.style.display = 'block'; // show spinner
            const res = await fetch(`/page-builder/search?name=${encodeURIComponent(query)}`);
            if (!res.ok) throw new Error(`HTTP error ${res.status}`);
            return await res.json();
        } catch (err) {
            console.error('Search error:', err);
            return [];
        } finally {
            spinner.style.display = 'none'; // always hide spinner
        }
    }

    function renderResults(data) {
        resultsList.innerHTML = '';
        if (!data || data.length === 0) {
            resultsList.style.display = 'none';
            return;
        }

        data.forEach(item => {
            const li = document.createElement('li');
            li.style.padding = '6px 10px';
            li.style.cursor = 'pointer';
            li.style.borderBottom = '1px solid #eee';
            li.textContent = `${item.title} (${item.name}) v${item.version} status: active ${item.active == 1 ? 'Yes' : 'No'}`;

            li.addEventListener('click', () => {
                searchInput.value = li.textContent;
                hiddenPid.value = item.pid;
                resultsList.style.display = 'none';
            });

            resultsList.appendChild(li);
        });
        resultsList.style.display = 'block';
    }

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();
        hiddenPid.value = '';

        if (debounceTimeout) clearTimeout(debounceTimeout);

        if (query.length < 2) {
            resultsList.style.display = 'none';
            return;
        }

        debounceTimeout = setTimeout(async () => {
            const data = await fetchPages(query);
            renderResults(data);
        }, 300);
    });

    document.addEventListener('click', e => {
        if (!wrapper.contains(e.target)) {
            resultsList.style.display = 'none';
        }
    });
}
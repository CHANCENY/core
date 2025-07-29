(function (){
    function setupSearch(search_key) {
        const input = document.querySelector('#default-admin-search');

        if (input) {
            input.addEventListener('keydown',(e) =>{
                if (e.key === 'Enter') {
                    const value = e.target.value.trim();
                    if (value.length > 1) {
                        const url = `/search/${search_key}?q=${encodeURIComponent(value)}`;
                        history.pushState(null, '', url);
                        location.replace(url);
                    }
                }
            });
        }
    }

    window.search_api = setupSearch;
})();

export function initFoodSearch({ input, hidden, datalist, url }) {
    const cache = new Map();
    let timer = null;

    const setHiddenFromInput = () => {
        const id = cache.get(input.value);
        hidden.value = id ?? '';
    };

    const fetchSuggestions = async (q) => {
        const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) return [];
        return res.json();
    };

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);
        setHiddenFromInput();
        if (q.length < 2) {
            datalist.innerHTML = '';
            return;
        }
        timer = setTimeout(async () => {
            const items = await fetchSuggestions(q);
            cache.clear();
            datalist.innerHTML = '';
            for (const item of items) {
                cache.set(item.label, item.id);
                const opt = document.createElement('option');
                opt.value = item.label;
                datalist.appendChild(opt);
            }
            setHiddenFromInput();
        }, 200);
    });

    input.addEventListener('change', setHiddenFromInput);
    input.form?.addEventListener('submit', setHiddenFromInput);
}

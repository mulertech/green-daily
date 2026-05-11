function init({ input, hidden, dropdown, url }) {
    const state = { items: [], active: -1, abort: null };
    let timer = null;

    const clear = () => {
        state.items = [];
        state.active = -1;
        dropdown.innerHTML = '';
        dropdown.classList.add('hidden');
    };

    const render = () => {
        dropdown.innerHTML = '';
        if (state.items.length === 0) {
            dropdown.classList.add('hidden');
            return;
        }
        for (let i = 0; i < state.items.length; i++) {
            const item = state.items[i];
            const li = document.createElement('li');
            li.textContent = item.label;
            li.dataset.index = String(i);
            li.className =
                'cursor-pointer px-3 py-2 text-sm ' +
                (i === state.active ? 'bg-emerald-100 text-emerald-900' : 'hover:bg-slate-100 text-slate-800');
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                select(i);
            });
            dropdown.appendChild(li);
        }
        dropdown.classList.remove('hidden');
    };

    const select = (i) => {
        const item = state.items[i];
        if (!item) return;
        input.value = item.label;
        hidden.value = String(item.id);
        clear();
    };

    const fetchSuggestions = async (q) => {
        if (state.abort) state.abort.abort();
        state.abort = new AbortController();
        try {
            const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
                signal: state.abort.signal,
            });
            if (!res.ok) return [];
            return await res.json();
        } catch {
            return [];
        }
    };

    input.addEventListener('input', () => {
        const q = input.value.trim();
        hidden.value = '';
        clearTimeout(timer);
        if (q.length < 2) {
            clear();
            return;
        }
        timer = setTimeout(async () => {
            state.items = await fetchSuggestions(q);
            state.active = state.items.length > 0 ? 0 : -1;
            render();
        }, 200);
    });

    input.addEventListener('keydown', (e) => {
        if (dropdown.classList.contains('hidden')) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            state.active = Math.min(state.items.length - 1, state.active + 1);
            render();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            state.active = Math.max(0, state.active - 1);
            render();
        } else if (e.key === 'Enter') {
            if (state.active >= 0) {
                e.preventDefault();
                select(state.active);
            }
        } else if (e.key === 'Escape') {
            clear();
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target !== input && !dropdown.contains(e.target)) clear();
    });
}

for (const input of document.querySelectorAll('input[data-foods-search]')) {
    const dropdownId = input.dataset.dropdownId;
    const dropdown = dropdownId ? document.getElementById(dropdownId) : null;
    const hiddenName = input.dataset.hiddenName ?? 'food_id';
    const hidden = input.form?.elements.namedItem(hiddenName);
    const url = input.dataset.foodsSearch;

    if (!dropdown || !hidden || !url) continue;

    init({ input, hidden, dropdown, url });
}

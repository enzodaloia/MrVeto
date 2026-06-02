import { Controller } from '@hotwired/stimulus';

/**
 * SearchableSelect — composant de sélection avec recherche, catégories, multi-sélection et valeurs custom.
 *
 * Valeurs Stimulus :
 *   allOptions       : [{value, label, flag?, category?}]  — toutes les options disponibles
 *   selected         : [string]                             — valeurs présélectionnées
 *   multiple         : bool
 *   allowCustom      : bool
 *   name             : string   — nom du champ <input hidden>
 *   placeholderText  : string
 *
 * Targets :
 *   trigger, tags, placeholderEl, dropdown, search, optionsList, customHint, customLabel, hiddenInputs
 */
export default class extends Controller {
    static targets = [
        'trigger', 'tags', 'placeholderEl',
        'dropdown', 'search', 'optionsList',
        'customHint', 'customLabel',
        'hiddenInputs',
    ];
    static values = {
        multiple: Boolean,
        allowCustom: Boolean,
        selected: Array,
        allOptions: Array,
        name: String,
        placeholderText: String,
    };

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    connect() {
        // Reconstruit les objets sélectionnés depuis les valeurs (strings)
        this._selected = this.selectedValue.map((val) => this.#findOrBuild(val));
        this.#renderTags();
        this.#renderOptions('');
        this.#renderHiddenInputs();
        document.addEventListener('click', this.#onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this.#onDocumentClick);
    }

    // ─── Actions publiques ────────────────────────────────────────────────────

    toggle(event) {
        event.stopPropagation();
        if (!this.dropdownTarget.classList.contains('d-none')) {
            this.#close();
        } else {
            this.#open();
        }
    }

    onSearch(event) {
        const q = event.target.value.trim();
        this.#renderOptions(q);
        this.#updateCustomHint(q);
    }

    onKeydown(event) {
        if (event.key === 'Escape') {
            this.#close();
            return;
        }
        if (event.key === 'Enter' && this.allowCustomValue) {
            event.preventDefault();
            const raw = this.hasSearchTarget ? this.searchTarget.value.trim() : '';
            if (!raw) return;
            const alreadyIn = this._selected.some((s) => s.value === raw);
            const inOptions = this.allOptionsValue.some(
                (o) => o.label.toLowerCase() === raw.toLowerCase()
            );
            if (!alreadyIn && !inOptions) {
                this._selected.push({ value: raw, label: raw, flag: '' });
                this.#commit();
                if (this.hasSearchTarget) this.searchTarget.value = '';
                this.#updateCustomHint('');
            }
        }
    }

    // Délégation de clic depuis optionsList (data-opt-value)
    optionsListTargetConnected(el) {
        el.addEventListener('click', this.#onOptionsClick);
    }

    optionsListTargetDisconnected(el) {
        el.removeEventListener('click', this.#onOptionsClick);
    }

    // Délégation de clic depuis tags (data-remove-value)
    tagsTargetConnected(el) {
        el.addEventListener('click', this.#onTagsClick);
    }

    tagsTargetDisconnected(el) {
        el.removeEventListener('click', this.#onTagsClick);
    }

    // ─── API publique ─────────────────────────────────────────────────────────

    /** Retourne les valeurs sélectionnées (array de strings). */
    get values() {
        return this._selected.map((s) => s.value);
    }

    /** Retourne les items sélectionnés complets. */
    get items() {
        return [...this._selected];
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    _selected = [];

    #open() {
        this.dropdownTarget.classList.remove('d-none');
        this.#positionDropdown();
        if (this.hasSearchTarget) {
            this.searchTarget.value = '';
            this.#renderOptions('');
            this.#updateCustomHint('');
            requestAnimationFrame(() => this.searchTarget.focus());
        }
    }

    #close() {
        this.dropdownTarget.classList.add('d-none');
    }

    #positionDropdown() {
        const triggerRect = this.element.getBoundingClientRect();
        const dropdownHeight = this.dropdownTarget.offsetHeight || 280; // estimation si encore hidden
        const spaceBelow = window.innerHeight - triggerRect.bottom;
        const spaceAbove = triggerRect.top;

        const openUp = spaceBelow < dropdownHeight && spaceAbove > spaceBelow;

        if (openUp) {
            this.dropdownTarget.style.top = '';
            this.dropdownTarget.style.bottom = '100%';
            this.dropdownTarget.style.marginTop = '0';
            this.dropdownTarget.style.marginBottom = '4px';
        } else {
            this.dropdownTarget.style.top = '100%';
            this.dropdownTarget.style.bottom = '';
            this.dropdownTarget.style.marginTop = '4px';
            this.dropdownTarget.style.marginBottom = '0';
        }
    }

    #commit() {
        this.#renderTags();
        this.#renderOptions(this.hasSearchTarget ? this.searchTarget.value : '');
        this.#renderHiddenInputs();
    }

    // ── Délégation de clics ────────────────────────────────────────────────

    #onDocumentClick = (e) => {
        if (!this.element.contains(e.target)) this.#close();
    };

    #onOptionsClick = (e) => {
        const btn = e.target.closest('[data-opt-value]');
        if (!btn) return;
        e.stopPropagation();
        const { optValue: value, optLabel: label, optFlag: flag } = btn.dataset;

        if (this.multipleValue) {
            const idx = this._selected.findIndex((s) => s.value === value);
            if (idx >= 0) {
                this._selected.splice(idx, 1);
            } else {
                this._selected.push({ value, label, flag: flag || '' });
            }
            this.#commit();
        } else {
            this._selected = [{ value, label, flag: flag || '' }];
            this.#commit();
            this.#close();
        }
    };

    #onTagsClick = (e) => {
        const btn = e.target.closest('[data-remove-value]');
        if (!btn) return;
        e.stopPropagation();
        this._selected = this._selected.filter((s) => s.value !== btn.dataset.removeValue);
        this.#commit();
    };

    // ── Rendu ─────────────────────────────────────────────────────────────────

    #renderTags() {
        const hasSel = this._selected.length > 0;

        if (this.multipleValue) {
            this.tagsTarget.innerHTML = this._selected
                .map(
                    (s) =>
                        `<span class="badge rounded-pill fw-medium px-2 py-1 border border-primary-border bg-primary-bg text-primary d-inline-flex align-items-center gap-1" style="font-size:0.78rem;">` +
                        (s.flag ? `<span>${s.flag}</span>` : '') +
                        `<span>${this.#esc(s.label)}</span>` +
                        `<button type="button" class="border-0 bg-transparent p-0 lh-1 ms-1 text-primary opacity-75" style="font-size:0.7rem;" data-remove-value="${this.#esc(s.value)}" title="Retirer">` +
                        `<i class="bi bi-x-lg"></i></button></span>`
                )
                .join('');
        } else {
            this.tagsTarget.innerHTML = hasSel
                ? `<span class="text-dark">${this._selected[0].flag ? this._selected[0].flag + ' ' : ''}${this.#esc(this._selected[0].label)}</span>`
                : '';
        }

        if (this.hasPlaceholderElTarget) {
            this.placeholderElTarget.style.display = hasSel ? 'none' : '';
        }
    }

    #renderOptions(query) {
        const q = query.toLowerCase();
        const all = this.allOptionsValue;

        // Grouper par catégorie (préserver l'ordre d'apparition)
        const groups = new Map(); // Map<string, opt[]>  '' = sans catégorie
        for (const opt of all) {
            if (q && !opt.label.toLowerCase().includes(q)) continue;
            const cat = opt.category || '';
            if (!groups.has(cat)) groups.set(cat, []);
            groups.get(cat).push(opt);
        }

        let html = '';
        for (const [cat, opts] of groups) {
            if (cat) {
                html += `<div class="px-3 pt-2 pb-1 text-muted fw-semibold text-uppercase" style="font-size:0.68rem;letter-spacing:0.06em;">${this.#esc(cat)}</div>`;
            }
            for (const opt of opts) {
                const isSelected = this._selected.some((s) => s.value === opt.value);
                html +=
                    `<button type="button" class="btn btn-sm w-100 text-start px-3 py-2 rounded-2 border-0 d-flex align-items-center gap-2 ${isSelected ? 'bg-primary-bg text-primary fw-medium' : 'text-dark'}"` +
                    ` data-opt-value="${this.#esc(opt.value)}" data-opt-label="${this.#esc(opt.label)}" data-opt-flag="${this.#esc(opt.flag || '')}">` +
                    (opt.flag ? `<span>${opt.flag}</span>` : '') +
                    `<span>${this.#esc(opt.label)}</span>` +
                    (isSelected ? `<i class="bi bi-check-lg ms-auto text-primary"></i>` : '') +
                    `</button>`;
            }
        }

        if (!html) {
            html = `<div class="text-muted small px-3 py-2">Aucun résultat</div>`;
        }

        this.optionsListTarget.innerHTML = html;
    }

    #renderHiddenInputs() {
        const name = this.nameValue;
        const suffix = this.multipleValue ? '[]' : '';
        this.hiddenInputsTarget.innerHTML = this._selected
            .map((s) => `<input type="hidden" name="${name}${suffix}" value="${this.#esc(s.value)}">`)
            .join('');
    }

    #updateCustomHint(q) {
        if (!this.allowCustomValue || !this.hasCustomHintTarget) return;
        const alreadySelected = this._selected.some((s) => s.value === q);
        const inOptions = this.allOptionsValue.some(
            (o) => o.label.toLowerCase() === q.toLowerCase()
        );
        const show = q.length > 0 && !alreadySelected && !inOptions;
        this.customHintTarget.classList.toggle('d-none', !show);
        if (show && this.hasCustomLabelTarget) this.customLabelTarget.textContent = q;
    }

    #findOrBuild(val) {
        const opt = this.allOptionsValue.find((o) => o.value === val);
        return opt
            ? { value: opt.value, label: opt.label, flag: opt.flag || '' }
            : { value: val, label: val, flag: '' }; // valeur custom
    }

    #esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

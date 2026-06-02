import { Controller } from '@hotwired/stimulus';

/**
 * vet-profile-edit — édition inline des sections de la fiche vétérinaire.
 *
 * Chaque section éditable a la structure :
 *   <div data-vet-profile-edit-target="section" data-field="aPropos">
 *     <div data-vet-profile-edit-target="display">…affichage…</div>
 *     <div data-vet-profile-edit-target="form" class="d-none">…formulaire…</div>
 *   </div>
 *
 * Bouton crayon :  data-action="click->vet-profile-edit#startEdit" data-field="aPropos"
 * Bouton annuler : data-action="click->vet-profile-edit#cancel"   data-field="aPropos"
 * Bouton sauver  : data-action="click->vet-profile-edit#save"     data-field="aPropos"
 *
 * data-vet-profile-edit-patch-url-value : URL du endpoint PATCH (générée en Twig)
 */
export default class extends Controller {
    static targets = ['section', 'display', 'form'];
    static values  = { patchUrl: String };

    // ─── Actions ─────────────────────────────────────────────────────────────

    startEdit(event) {
        const field = event.currentTarget.dataset.field;
        const section = this.#sectionFor(field);
        if (!section) return;

        section.querySelector('[data-vet-profile-edit-target="display"]')?.classList.add('d-none');
        section.querySelector('[data-vet-profile-edit-target="form"]')?.classList.remove('d-none');
    }

    cancel(event) {
        const field = event.currentTarget.dataset.field;
        this.#closeSection(field);
    }

    async save(event) {
        const field  = event.currentTarget.dataset.field;
        const section = this.#sectionFor(field);
        if (!section) return;

        const value = this.#collectValue(section, field);
        const items = this.#collectItems(section, field);

        const btn = event.currentTarget;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch(this.patchUrlValue, {
                method : 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ field, value }),
            });

            const json = await res.json();

            if (!res.ok || json.error) {
                this.#showToast(json.error ?? 'Erreur serveur.', 'danger');
                return;
            }

            // Mettre à jour l'affichage
            this.#updateDisplay(section, field, value, items);
            this.#closeSection(field);
            this.#showToast('Modifications enregistrées.', 'success');
        } catch {
            this.#showToast('Impossible de contacter le serveur.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Enregistrer';
        }
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    #sectionFor(field) {
        return this.sectionTargets.find((s) => s.dataset.field === field) ?? null;
    }

    #closeSection(field) {
        const section = this.#sectionFor(field);
        if (!section) return;
        section.querySelector('[data-vet-profile-edit-target="display"]')?.classList.remove('d-none');
        section.querySelector('[data-vet-profile-edit-target="form"]')?.classList.add('d-none');
    }

    /**
     * Collecte la valeur depuis le formulaire de la section.
     * - textarea / input text → string
     * - SearchableSelect (data-controller="searchable-select") → array via controller
     */
    #collectValue(section, field) {
        // Champ texte simple (textarea ou input)
        const textarea = section.querySelector('textarea[data-edit-field]');
        if (textarea) return textarea.value;

        const input = section.querySelector('input[data-edit-field]');
        if (input) return input.value;

        // SearchableSelect : lit les hidden inputs générés
        const hiddenInputs = section.querySelectorAll(`input[type="hidden"][name="${field}[]"]`);
        if (hiddenInputs.length > 0) {
            return Array.from(hiddenInputs).map((i) => i.value);
        }

        // Cas single SearchableSelect (sans [])
        const singleHidden = section.querySelector(`input[type="hidden"][name="${field}"]`);
        if (singleHidden) return singleHidden.value;

        return null;
    }

    /**
     * Récupère les items complets ({value, label, flag}) depuis le SearchableSelect
     * de la section, si présent.
     */
    #collectItems(section, field) {
        const selectEl = section.querySelector('[data-controller="searchable-select"]');
        if (!selectEl) return null;
        const ctrl = this.application.getControllerForElementAndIdentifier(selectEl, 'searchable-select');
        return ctrl?.items ?? null;
    }

    /**
     * Met à jour le bloc [display] après une sauvegarde réussie.
     * Toujours des badges pour les champs multi-select (labels + flags).
     */
    #updateDisplay(section, field, value, items = null) {
        const display = section.querySelector('[data-vet-profile-edit-target="display"]');
        if (!display) return;

        const container = display.querySelector('[data-display-content]');
        if (!container) return;

        if (Array.isArray(value)) {
            if (value.length === 0) {
                container.innerHTML = '<span class="text-muted fst-italic">Non renseigné</span>';
            } else {
                // Utilise les items du SearchableSelect (avec labels et flags) si disponibles
                const renderItems = items ?? value.map((v) => ({ value: v, label: v, flag: '' }));
                container.innerHTML = renderItems
                    .map((item) => {
                        const label = typeof item === 'string' ? item : (item.label ?? item.value);
                        const flag  = typeof item === 'string' ? '' : (item.flag ?? '');
                        return `<span class="badge rounded-pill fw-medium px-3 py-2 border border-primary-border bg-primary-bg text-primary d-inline-flex align-items-center gap-1">` +
                            (flag ? `<span>${flag}</span>` : '') +
                            `<span>${this.#esc(label)}</span></span>`;
                    })
                    .join('');
            }
        } else {
            container.textContent = value || 'Non renseigné';
        }
    }

    #esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    #showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-4`;
        toast.style.zIndex = '9999';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body fw-medium">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        document.body.appendChild(toast);
        const bsToast = new window.bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
}

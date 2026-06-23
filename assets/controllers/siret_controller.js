import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller — champ SIRET
 * - Chiffres uniquement (bloque lettres/symboles)
 * - Séparation visuelle automatique : XXXXXXXXX XXXXX (SIREN 9 + NIC 5)
 * - Synchronise la valeur brute (14 chiffres) dans le champ hidden soumis au serveur
 */
export default class extends Controller {
    static targets = ['combined', 'display'];

    connect() {
        // Pré-remplissage si le formulaire est re-rendu après une erreur de validation
        const raw = this.combinedTarget.value.replace(/\D/g, '');
        if (raw) this.displayTarget.value = this.#fmt(raw);
    }

    // Bloquer les touches non-chiffres
    block(e) {
        if (e.key.length === 1 && !/\d/.test(e.key) && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            return;
        }
        // Backspace exactement après le séparateur (position 10) → supprimer le 9e chiffre
        if (
            e.key === 'Backspace' &&
            this.displayTarget.selectionStart === this.displayTarget.selectionEnd &&
            this.displayTarget.selectionStart === 10
        ) {
            e.preventDefault();
            const d    = this.#digits(this.displayTarget.value);
            const newD = d.slice(0, 8) + d.slice(9); // retire le 9e chiffre
            this.displayTarget.value = this.#fmt(newD);
            this.combinedTarget.value = newD;
            this.displayTarget.setSelectionRange(8, 8);
        }
    }

    // Formater à chaque saisie en conservant la position du curseur
    format() {
        const sel       = this.displayTarget.selectionStart;
        const rawBefore = this.displayTarget.value.slice(0, sel).replace(/\D/g, '').length;
        const digits    = this.#digits(this.displayTarget.value);

        this.displayTarget.value  = this.#fmt(digits);
        this.combinedTarget.value = digits;

        // Replacer le curseur au bon endroit dans la chaîne formatée
        let pos = 0, count = 0;
        while (count < rawBefore && pos < this.displayTarget.value.length) {
            if (this.displayTarget.value[pos] !== '\u00a0') count++;
            pos++;
        }
        this.displayTarget.setSelectionRange(pos, pos);
    }

    // Coller un SIRET complet (retire espaces/tirets automatiquement)
    paste(e) {
        e.preventDefault();
        const digits = this.#digits(
            (e.clipboardData || window.clipboardData).getData('text')
        );
        this.displayTarget.value  = this.#fmt(digits);
        this.combinedTarget.value = digits;
    }

    // --- Privé ---

    #digits(v) {
        return v.replace(/\D/g, '').slice(0, 14);
    }

    // Insère une espace insécable comme séparateur après le 9e chiffre
    #fmt(d) {
        return d.length > 9 ? d.slice(0, 9) + '\u00a0' + d.slice(9) : d;
    }
}

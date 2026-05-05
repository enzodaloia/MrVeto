import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results', 'userId', 'role', 'selectedLabel'];
    static values = {
        searchUrl: String,
    };

    connect() {
        this.timeout = null;
        this.element.dataset.userAutocompleteBound = '1';
        this.resetSelection();

        this.onDocumentClick = (event) => {
            if (!this.element.contains(event.target)) {
                this.resultsTarget.innerHTML = '';
            }
        };

        document.addEventListener('click', this.onDocumentClick);
    }

    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }

        document.removeEventListener('click', this.onDocumentClick);
        delete this.element.dataset.userAutocompleteBound;
    }

    search() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }

        const query = this.inputTarget.value.trim();
        this.resetSelection();

        if (query.length < 3) {
            this.resultsTarget.innerHTML = '';
            return;
        }

        this.timeout = setTimeout(async () => {
            try {
                const response = await fetch(`${this.searchUrlValue}?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Search request failed');
                }

                const data = await response.json();
                this.renderResults(data.items || []);
            } catch (error) {
                console.error(error);
                this.renderEmpty('Erreur pendant la recherche.');
            }
        }, 250);
    }

    select(event) {
        const payload = event.currentTarget.dataset.userPayload;
        if (!payload) {
            return;
        }

        const user = JSON.parse(payload);

        if (this.timeout) {
            clearTimeout(this.timeout);
            this.timeout = null;
        }

        this.resultsTarget.innerHTML = '';
        this.inputTarget.value = user.email;
        this.inputTarget.blur();

        this.userIdTarget.value = String(user.id);
        this.selectedLabelTarget.textContent = `Sélectionné: ${user.email}`;

        this.roleTarget.innerHTML = '';
        if (user.isVeto) {
            this.roleTarget.appendChild(new Option('Vétérinaire', 'VETERINAIRE', true, true));
            this.roleTarget.disabled = false;
            return;
        }

        if (user.isSecretary) {
            this.roleTarget.appendChild(new Option('Secrétaire', 'SECRETAIRE', true, true));
            this.roleTarget.disabled = false;
            return;
        }

        this.roleTarget.appendChild(new Option('Aucun rôle disponible', '', true, true));
        this.roleTarget.disabled = true;
    }

    renderResults(items) {
        if (items.length === 0) {
            this.resultsTarget.innerHTML = '';
            return;
        }

        this.resultsTarget.innerHTML = '';
        const wrapper = document.createElement('div');
        wrapper.className = 'position-absolute w-100 z-3 mt-1 rounded shadow overflow-hidden bg-white border';
        wrapper.style.top = '100%';

        const list = document.createElement('ul');
        list.className = 'list-group list-group-flush';
        list.style.maxHeight = '260px';
        list.style.overflowY = 'auto';

        items.forEach((item) => {
            const listItem = document.createElement('li');
            listItem.className = 'list-group-item list-group-item-action border-0';
            listItem.style.cursor = 'pointer';
            listItem.dataset.action = 'mousedown->user-autocomplete#select';
            listItem.dataset.userPayload = JSON.stringify(item);

            const fullName = `${item.prenom || ''} ${item.nom || ''}`.trim();
            listItem.textContent = fullName !== '' ? `${item.email} - ${fullName}` : item.email;

            list.appendChild(listItem);
        });

        wrapper.appendChild(list);
        this.resultsTarget.appendChild(wrapper);
    }

    renderEmpty(message) {
        this.resultsTarget.innerHTML = `<small class="text-muted">${message}</small>`;
    }

    resetSelection() {
        this.userIdTarget.value = '';
        this.selectedLabelTarget.textContent = 'Aucun utilisateur sélectionné.';
        this.roleTarget.innerHTML = '';
        this.roleTarget.appendChild(new Option('Sélectionnez d\'abord un utilisateur', '', true, true));
        this.roleTarget.disabled = true;
    }
}

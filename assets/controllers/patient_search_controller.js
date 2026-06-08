import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results'];
    static values = {
        url: String
    };

    connect() {
        this.timeoutId = null;
    }

    search(event) {
        clearTimeout(this.timeoutId);
        const q = this.inputTarget.value.trim();
        if (q.length < 2) {
            this.resultsTarget.style.display = 'none';
            return;
        }

        this.timeoutId = setTimeout(() => {
            fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`)
                .then(response => response.json())
                .then(data => {
                    this.resultsTarget.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action py-2';
                            a.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-primary">${item.animal_nom}</strong> <span class="text-muted small">(${item.animal_espece || 'Espèce inconnue'})</span>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-person"></i> ${item.owner_prenom} ${item.owner_nom}
                                    </div>
                                </div>
                            `;
                            a.addEventListener('click', (e) => {
                                e.preventDefault();
                                // Remplir le formulaire
                                const form = this.element.closest('form');
                                form.querySelector('input[name="existing_animal_id"]').value = item.id;
                                form.querySelector('input[name="animal_nom"]').value = item.animal_nom || '';
                                form.querySelector('select[name="animal_espece"]').value = item.animal_espece || '';
                                form.querySelector('input[name="animal_race"]').value = item.animal_race || '';
                                form.querySelector('input[name="animal_naissance"]').value = item.animal_naissance || '';
                                form.querySelector('input[name="animal_poids"]').value = item.animal_poids || '';
                                
                                form.querySelector('input[name="owner_nom"]').value = item.owner_nom || '';
                                form.querySelector('input[name="owner_prenom"]').value = item.owner_prenom || '';
                                form.querySelector('input[name="owner_telephone"]').value = item.owner_telephone || '';
                                form.querySelector('input[name="owner_email"]').value = item.owner_email || '';
                                
                                this.resultsTarget.style.display = 'none';
                                this.inputTarget.value = '';
                            });
                            this.resultsTarget.appendChild(a);
                        });
                        this.resultsTarget.style.display = 'block';
                    } else {
                        this.resultsTarget.innerHTML = '<div class="list-group-item text-muted small">Aucun patient trouvé.</div>';
                        this.resultsTarget.style.display = 'block';
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    }

    clickOutside(event) {
        if (!this.inputTarget.contains(event.target) && !this.resultsTarget.contains(event.target)) {
            this.resultsTarget.style.display = 'none';
        }
    }
}

import { Controller } from '@hotwired/stimulus';
import * as bootstrap from 'bootstrap';

export default class extends Controller {
    static targets = [
        'btn', 'spinner', 'icon',
        'vetName', 'vetAddress', 'vetDistance', 'vetPhone', 'vetPhoneWrapper', 'vetProfileLink', 'vetProfileWrapper',
        'errorMsg', 'errorWrapper', 'resultWrapper',
    ];
    static values = { nearestUrl: String };

    find() {
        if (!navigator.geolocation) {
            this._showError("La géolocalisation n'est pas disponible sur votre navigateur.");
            return;
        }
        this._setLoading(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => this._onPosition(pos),
            () => {
                this._setLoading(false);
                this._showError("Impossible d'obtenir votre position. Veuillez autoriser la géolocalisation.");
            },
            { timeout: 10000, maximumAge: 60000 }
        );
    }

    async _onPosition(pos) {
        const { latitude, longitude } = pos.coords;
        try {
            const url = new URL(this.nearestUrlValue, window.location.origin);
            url.searchParams.set('lat', latitude);
            url.searchParams.set('lon', longitude);

            const resp = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();
            this._setLoading(false);

            if (!resp.ok || data.error) {
                this._showError(data.error || 'Aucun vétérinaire urgentiste trouvé.');
            } else {
                this._showResult(data);
            }
        } catch {
            this._setLoading(false);
            this._showError('Une erreur est survenue. Veuillez réessayer.');
        }
    }

    _showResult(data) {
        this.vetNameTarget.textContent = `Dr. ${data.prenom} ${data.nom}`;
        this.vetAddressTarget.textContent = data.adresse || 'Adresse non renseignée';
        this.vetDistanceTarget.textContent = data.distance !== null ? `À ${data.distance} km` : '';

        if (data.telephone) {
            this.vetPhoneTarget.href = `tel:${data.telephone}`;
            this.vetPhoneTarget.textContent = data.telephone;
            this.vetPhoneWrapperTarget.classList.remove('d-none');
        } else {
            this.vetPhoneWrapperTarget.classList.add('d-none');
        }

        this.vetProfileLinkTarget.href = data.profileUrl;
        this.vetProfileWrapperTarget.classList.remove('d-none');
        this.errorWrapperTarget.classList.add('d-none');
        this.resultWrapperTarget.classList.remove('d-none');

        this._openModal();
    }

    _showError(message) {
        this.errorMsgTarget.textContent = message;
        this.errorWrapperTarget.classList.remove('d-none');
        this.resultWrapperTarget.classList.add('d-none');
        this._openModal();
    }

    _openModal() {
        const modalEl = document.getElementById('urgenceModal');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    _setLoading(loading) {
        if (this.hasBtnTarget) this.btnTarget.disabled = loading;
        if (this.hasSpinnerTarget) this.spinnerTarget.classList.toggle('d-none', !loading);
        if (this.hasIconTarget) this.iconTarget.classList.toggle('d-none', loading);
    }
}

import { Controller } from '@hotwired/stimulus';
import * as bootstrap from 'bootstrap';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = [
        "btnUpcoming", "btnHistory", "btnSeeAll", 
        "sectionUpcoming", "sectionHistory", "historyTitle", "sectionSeeAll",
        "searchInput", "btnSort", "sortIcon",
        "upcomingItem", "historyItem", 
        "upcomingContainer", "historyContainer",
        "cancelModal", "modalDate", "modalTime", "confirmCancelBtn",
        "editModal", "editModalVetImg", "editModalVetName", "editModalVetSubtitle", "editModalMotif", "editModalBadge", "editModalDateSelect", "editModalTimeSelect", "confirmEditBtn"
    ]

    static values = {
        endpointPrefix: String
    }

    connect() {
        this.rdvToCancel = null;
        this.rdvToEdit = null;
        this.disponibilites = {};
    }

    getEndpointPrefix() {
        if (this.hasEndpointPrefixValue && this.endpointPrefixValue) {
            return this.endpointPrefixValue;
        }

        return '/mes-rendez-vous';
    }

    showUpcoming() {
        this.btnUpcomingTarget.className = "btn btn-white bg-white text-primary fw-bold rounded-pill px-4 py-2 shadow-sm border-0";
        this.btnHistoryTarget.className = "btn text-muted fw-medium rounded-pill px-4 py-2 border-0 hover-bg-white";
        
        this.sectionUpcomingTarget.style.display = "block";
        this.historyTitleTarget.innerHTML = '<i class="bi bi-clock-history text-muted"></i> Historique récent';
        this.sectionSeeAllTarget.style.display = "block";
    }

    showHistory(event) {
        if(event) {
            event.preventDefault();
        }

        this.btnHistoryTarget.className = "btn btn-white bg-white text-primary fw-bold rounded-pill px-4 py-2 shadow-sm border-0";
        this.btnUpcomingTarget.className = "btn text-muted fw-medium rounded-pill px-4 py-2 border-0 hover-bg-white";
        
        this.sectionUpcomingTarget.style.display = "none";
        this.historyTitleTarget.innerHTML = '<i class="bi bi-clock-history text-muted"></i> Historique des consultations';
        this.sectionSeeAllTarget.style.display = "none";
    }

    search() {
        const query = this.searchInputTarget.value.toLowerCase().trim();
        
        this.upcomingItemTargets.forEach(item => {
            const motif = item.dataset.motif;
            if (motif.includes(query)) {
                item.style.display = "flex";
            } else {
                item.style.display = "none";
            }
        });

        this.historyItemTargets.forEach(item => {
            const motif = item.dataset.motif;
            if (motif.includes(query)) {
                item.style.display = "table-row";
            } else {
                item.style.display = "none";
            }
        });
    }

    sort() {
        const isAscending = this.btnSortTarget.classList.contains("sort-asc");
        
        if (isAscending) {
            this.btnSortTarget.classList.remove("sort-asc");
            this.btnSortTarget.classList.add("sort-desc");
            this.sortIconTarget.className = "bi bi-sort-down-alt text-muted fs-5";
        } else {
            this.btnSortTarget.classList.remove("sort-desc");
            this.btnSortTarget.classList.add("sort-asc");
            this.sortIconTarget.className = "bi bi-sort-down text-muted fs-5";
        }

        const upcomingItems = Array.from(this.upcomingItemTargets);
        upcomingItems.sort((a, b) => {
            const timeA = parseInt(a.dataset.timestamp);
            const timeB = parseInt(b.dataset.timestamp);
            return isAscending ? timeB - timeA : timeA - timeB;
        });
        upcomingItems.forEach(item => this.upcomingContainerTarget.appendChild(item));

        const historyItems = Array.from(this.historyItemTargets);
        historyItems.sort((a, b) => {
            const timeA = parseInt(a.dataset.timestamp);
            const timeB = parseInt(b.dataset.timestamp);
            return isAscending ? timeB - timeA : timeA - timeB; 
        });
        historyItems.forEach(item => this.historyContainerTarget.appendChild(item));
    }

    openCancelModal(event) {
        const btn = event.currentTarget;
        this.rdvToCancel = btn.dataset.id;
        
        // Use backend date/time directly to avoid timezone shifts in the modal
        const rdvDateStr = btn.dataset.date;
        const rdvTimeStr = btn.dataset.time;
        const dateObj = (rdvDateStr && rdvTimeStr)
            ? new Date(`${rdvDateStr}T${rdvTimeStr}:00`)
            : new Date(rdvDateStr);

        if (Number.isNaN(dateObj.getTime())) {
            this.modalDateTarget.innerText = "Date indisponible";
            this.modalTimeTarget.innerText = "Heure indisponible";
            const modal = new bootstrap.Modal(this.cancelModalTarget);
            modal.show();
            return;
        }
        
        // Ex: "18 Set, 2023"
        const formattedDate = dateObj.toLocaleDateString('fr-FR', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).replace('.', ''); // Fix abbreviation dot

        // Ex: "5:30 PM - 5:55 PM"
        const formattedTime = rdvTimeStr ?? dateObj.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Add 25 minutes for the end time estimation
        const endDateObj = new Date(dateObj.getTime() + 25*60000);
        const formattedEndTime = endDateObj.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });

        this.modalDateTarget.innerText = formattedDate;
        this.modalTimeTarget.innerText = `${formattedTime} - ${formattedEndTime}`;
        
        // Show modal (bootstrap way using JS)
        const modal = new bootstrap.Modal(this.cancelModalTarget);
        modal.show();
    }

    async confirmCancel(event) {
        if (!this.rdvToCancel) return;

        const btn = event.currentTarget;
        const originalText = btn.innerText;
        btn.innerText = "Annulation...";
        btn.disabled = true;

        try {
            const response = await fetch(`${this.getEndpointPrefix()}/${this.rdvToCancel}/annuler`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                // Rafraîchir la page pour mettre à jour l'UI (le RDV annulé passera dans l'historique)
                window.location.reload();
            } else {
                alert("Une erreur s'est produite lors de l'annulation.");
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            alert("Erreur réseau");
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    async confirmRdv(event) {
        const btn = event.currentTarget;
        const rdvId = btn.dataset.id;

        if (!rdvId) return;

        const originalText = btn.innerText;
        btn.innerText = "Confirmation...";
        btn.disabled = true;

        try {
            const response = await fetch(`${this.getEndpointPrefix()}/${rdvId}/confirmer`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                window.location.reload();
            } else {
                const data = await response.json().catch(() => ({}));
                alert(data.error || "Une erreur s'est produite lors de la confirmation.");
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            alert("Erreur reseau");
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    openEditModal(event) {
        const btn = event.currentTarget;
        this.rdvToEdit = btn.dataset.id;
        
        // Setup Modal visuals
        this.editModalVetNameTarget.innerText = btn.dataset.vet || '';
        this.editModalMotifTarget.innerText = btn.dataset.motif;
        this.editModalVetImgTarget.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(btn.dataset.vet || '')}&background=random`;

        if (this.hasEditModalVetSubtitleTarget) {
            this.editModalVetSubtitleTarget.innerText = btn.dataset.subtitle || 'Veterinaire';
        }
        
        const statut = btn.dataset.statut;
        let badgeClass = "bg-light text-muted";
        let badgeText = statut.toUpperCase();
        
        if (statut === 'confirme') {
            badgeClass = "bg-success text-white";
            badgeText = "CONFIRMÉ";
        } else if (statut === 'en_attente') {
            badgeClass = "bg-warning text-white";
            badgeText = "EN ATTENTE";
        }
        
        this.editModalBadgeTarget.className = `badge rounded-pill px-2 py-1 fw-bold ${badgeClass}`;
        this.editModalBadgeTarget.innerText = badgeText;
        
        // Reset and show Loading
        this.editModalDateSelectTarget.innerHTML = '<option value="">Chargement...</option>';
        this.editModalTimeSelectTarget.innerHTML = '<option value="">--:--</option>';
        
        const modal = new bootstrap.Modal(this.editModalTarget);
        modal.show();

        // Fetch availabilities
        this.fetchDisponibilites(this.rdvToEdit, btn.dataset.date, btn.dataset.time);
    }

    async fetchDisponibilites(id, currentDate, currentTime) {
        try {
            const response = await fetch(`${this.getEndpointPrefix()}/${id}/disponibilites`);
            if (response.ok) {
                this.disponibilites = await response.json();
                this.populateDateSelect(currentDate);
                this.populateTimeSelect(currentDate, currentTime);
            } else {
                this.editModalDateSelectTarget.innerHTML = '<option value="">Erreur de chargement</option>';
            }
        } catch (e) {
            console.error(e);
            this.editModalDateSelectTarget.innerHTML = '<option value="">Erreur réseau</option>';
        }
    }

    populateDateSelect(selectedDate) {
        this.editModalDateSelectTarget.innerHTML = '';
        const dates = Object.keys(this.disponibilites).sort();
        
        if (dates.length === 0) {
            this.editModalDateSelectTarget.innerHTML = '<option value="">Aucune disponibilité</option>';
            return;
        }

        dates.forEach(date => {
            const opt = document.createElement('option');
            opt.value = date;
            
            // Format nice date e.g. "18 Set, 2026"
            const dateObj = new Date(date);
            const formattedDate = dateObj.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            }).replace('.', '');
            
            opt.innerText = formattedDate;
            if (date === selectedDate) {
                opt.selected = true;
            }
            this.editModalDateSelectTarget.appendChild(opt);
        });
        
        // If the previously selected date is not in the list (e.g. it was old, though it shouldn't happen unless edge case)
        if (!this.editModalDateSelectTarget.value && dates.length > 0) {
            this.editModalDateSelectTarget.value = dates[0];
        }
    }

    onEditDateChange() {
        const selectedDate = this.editModalDateSelectTarget.value;
        this.populateTimeSelect(selectedDate, null);
    }

    populateTimeSelect(selectedDate, selectedTime) {
        this.editModalTimeSelectTarget.innerHTML = '';
        if (!selectedDate || !this.disponibilites[selectedDate]) {
            this.editModalTimeSelectTarget.innerHTML = '<option value="">--:--</option>';
            return;
        }

        const times = this.disponibilites[selectedDate];
        times.forEach(time => {
            const opt = document.createElement('option');
            opt.value = time;
            opt.innerText = time;
            if (time === selectedTime) {
                opt.selected = true;
            }
            this.editModalTimeSelectTarget.appendChild(opt);
        });
        
        if (!this.editModalTimeSelectTarget.value && times.length > 0) {
            this.editModalTimeSelectTarget.value = times[0];
        }
    }

    async confirmEdit(event) {
        if (!this.rdvToEdit) return;

        const btn = event.currentTarget;
        const originalText = btn.innerText;
        
        const selectedDate = this.editModalDateSelectTarget.value;
        const selectedTime = this.editModalTimeSelectTarget.value;
        
        if (!selectedDate || !selectedTime) {
            alert("Veuillez sélectionner une date et une heure valides.");
            return;
        }

        btn.innerText = "Modification...";
        btn.disabled = true;

        try {
            const response = await fetch(`${this.getEndpointPrefix()}/${this.rdvToEdit}/modifier`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    newDate: selectedDate,
                    newTime: selectedTime
                })
            });

            if (response.ok) {
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.error || "Une erreur s'est produite lors de la modification.");
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            alert("Erreur réseau");
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
}

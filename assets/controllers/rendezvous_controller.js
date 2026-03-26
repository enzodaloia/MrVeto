import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = [
        "btnUpcoming", "btnHistory", "btnSeeAll", 
        "sectionUpcoming", "sectionHistory", "historyTitle", "sectionSeeAll",
        "searchInput", "btnSort", "sortIcon",
        "upcomingItem", "historyItem", 
        "upcomingContainer", "historyContainer"
    ]

    connect() {
        // Initial setup if needed
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
}
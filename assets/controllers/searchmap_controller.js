import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import '../styles/pages/searchvet.scss';

// Fix Leaflet default icon paths broken by webpack
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
    iconUrl: require('leaflet/dist/images/marker-icon.png'),
    shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
});

export default class extends Controller {
    static targets = ['map', 'mapWrapper', 'distanceInput', 'distanceLabel', 'modal', 'modalMap'];
    static values = {
        userLat: Number,
        userLon: Number,
        vets: Array,
    };

    connect() {
        this.#initMap(this.mapTarget, this.#map, (m) => { this.#map = m; });
    }

    // ─── Distance dropdown ──────────────────────────────────────────────────────

    updateDistance(event) {
        event.preventDefault();
        const { distanceValue, distanceLabel } = event.params;
        this.distanceInputTarget.value = distanceValue;
        this.distanceLabelTarget.textContent = distanceLabel;
    }

    // ─── Map expand toggle (Bootstrap modal) ───────────────────────────────────

    toggleMapExpand() {
        if (!this.#bsModal) {
            this.#bsModal = new Modal(this.modalTarget);
            this.modalTarget.addEventListener('shown.bs.modal', () => {
                if (!this.#modalMap) {
                    this.#initMap(this.modalMapTarget, this.#modalMap, (m) => { this.#modalMap = m; });
                } else {
                    this.#modalMap.invalidateSize();
                }
            });
        }
        this.#bsModal.show();
    }

    // ─── Private ────────────────────────────────────────────────────────────────

    #map = null;
    #modalMap = null;
    #bsModal = null;

    #initMap(container, _ref, setRef) {
        if (!container) return;

        const vets = this.vetsValue || [];
        let lat = 46.6, lon = 2.3, zoom = 6;

        if (this.userLatValue && this.userLonValue) {
            lat = this.userLatValue;
            lon = this.userLonValue;
            zoom = 11;
        } else if (vets.length > 0) {
            lat = vets[0].lat;
            lon = vets[0].lon;
            zoom = 10;
        }

        const map = L.map(container, { zoomControl: true }).setView([lat, lon], zoom);
        setRef(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        // Vet markers with clustering
        const vetIcon = L.divIcon({
            html: '<div style="width:18px;height:18px;background:#20c997;border:2.5px solid #fff;border-radius:50%;box-shadow:0 1px 5px rgba(0,0,0,0.35);"></div>',
            className: '',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });

        const clusterGroup = L.markerClusterGroup({
            disableClusteringAtZoom: 13,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            maxClusterRadius: 60,
            iconCreateFunction(cluster) {
                const count = cluster.getChildCount();
                return L.divIcon({
                    html: `<div style="width:36px;height:36px;background:#20c997;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.25);border:2px solid #fff;">${count}</div>`,
                    className: '',
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                });
            },
        });

        vets.forEach((vet) => {
            const availColor = vet.isOpen ? '#198754' : '#dc3545';
            const marker = L.marker([vet.lat, vet.lon], { icon: vetIcon });
            marker.bindPopup(
                `<div style="min-width:175px;">
                    <strong class="d-block mb-1" style="font-size:13px;">${vet.nom}</strong>
                    <small class="d-block text-muted mb-1">${vet.adresse}</small>
                    <small class="d-block mb-2">
                        <span style="color:${availColor};font-weight:600;">${vet.availLabel}</span>
                        <span class="text-muted"> · ${vet.availDetail}</span>
                    </small>
                    <a href="${vet.url}" class="btn btn-sm rounded-pill px-3 py-1 fw-medium" style="font-size:12px;background-color:#36BDAF;color:#fff;">Voir / RDV</a>
                </div>`
            );
            clusterGroup.addLayer(marker);
        });

        map.addLayer(clusterGroup);

        // Fix tile rendering after first layout paint
        setTimeout(() => map.invalidateSize(), 200);
    }
}

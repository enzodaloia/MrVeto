import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["input", "results", "lat", "lon", "city", "postcode", "street"];
    static values = {
        theme: { type: String, default: 'search' } // 'search' or 'form'
    };

    connect() {
        console.log('Autocomplete controller connected!');
        // Optional: Close results when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.element.contains(e.target)) {
                this.resultsTarget.innerHTML = '';
                this.resetStyles(this.inputTarget.parentElement);
            }
        });
    }

    async search() {
        const query = this.inputTarget.value;
        const inputWrapper = this.inputTarget.parentElement;

        if (query.length < 3) {
            this.resultsTarget.innerHTML = '';
            this.resetStyles(inputWrapper);
            return;
        }

        try {
            const response = await fetch(`https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(query)}&limit=10`);
            const data = await response.json();

            this.resultsTarget.innerHTML = '';

            if (data.features && data.features.length > 0) {
                // Show results dropdown
                this.resultsTarget.classList.remove('d-none');

                if (this.themeValue === 'search') {
                    // Style input wrapper to look merged
                    inputWrapper.classList.remove('rounded-pill');
                    inputWrapper.classList.add('rounded-top-4', 'rounded-bottom-0');
                }

                // Create a Wrapper to hold the list and the overlay
                this.resultsTarget.innerHTML = '';

                const wrapper = document.createElement('div');
                if (this.themeValue === 'search') {
                    wrapper.className = 'position-absolute w-100 z-3 mt-0 rounded-bottom-4 shadow-sm overflow-hidden bg-white  border-top-0';
                    wrapper.style.top = '100%';
                    wrapper.style.marginTop = '-1px';
                } else {
                    wrapper.className = 'position-absolute w-100 z-3 mt-1 rounded shadow overflow-hidden bg-white border';
                    wrapper.style.top = '100%';
                }

                const ul = document.createElement('ul');
                ul.className = 'list-group list-group-flush section-scroll hidden-scrollbar overflow-auto';
                ul.style.maxHeight = '300px';
                ul.style.marginBottom = '0'; // Bootstrap reset

                data.features.forEach(feature => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item bg-white list-group-item-action border-0';
                    li.style.cursor = 'pointer';

                    // Format label with commas as requested
                    let displayedLabel = feature.properties.label;
                    if (feature.properties.type === 'housenumber' || feature.properties.type === 'street') {
                        displayedLabel = `${feature.properties.name}, ${feature.properties.postcode}, ${feature.properties.city}`;
                    } else if (feature.properties.type === 'municipality') {
                        displayedLabel = `${feature.properties.city}, ${feature.properties.postcode}`;
                    }

                    li.textContent = displayedLabel;
                    li.dataset.lat = feature.geometry.coordinates[1];
                    li.dataset.lon = feature.geometry.coordinates[0];
                    li.dataset.label = displayedLabel;

                    li.addEventListener('mousedown', (e) => {
                        e.preventDefault(); // Prevent focus loss on input
                        this.selectAddress(feature, displayedLabel);
                    });

                    ul.appendChild(li);
                });

                wrapper.appendChild(ul);

                // Create Overlay
                const overlay = document.createElement('div');
                let overlayClasses = 'scroll-indicator hidden';
                if (this.themeValue === 'search') {
                    overlayClasses += ' rounded-bottom-4';
                }
                overlay.className = overlayClasses;
                wrapper.appendChild(overlay);

                this.resultsTarget.appendChild(wrapper);

                // Scroll Logic
                const checkScroll = () => {
                    // Check if scrollable
                    const isScrollable = ul.scrollHeight > ul.clientHeight;
                    // Check if at bottom (with small threshold)
                    const isAtBottom = Math.ceil(ul.scrollTop + ul.clientHeight) >= ul.scrollHeight - 5;

                    if (isScrollable && !isAtBottom) {
                        overlay.classList.remove('hidden');
                    } else {
                        overlay.classList.add('hidden');
                    }
                };

                // Attach listener
                ul.addEventListener('scroll', checkScroll);
                // Initial check after render
                setTimeout(checkScroll, 0);
            } else {
                this.resultsTarget.classList.add('d-none');
                this.resetStyles(inputWrapper);
            }
        } catch (error) {
            console.error('Error fetching address:', error);
            this.resetStyles(inputWrapper);
        }
    }

    selectAddress(feature, label) {
        this.inputTarget.value = label || feature.properties.label;
        if (this.hasLatTarget) this.latTarget.value = feature.geometry.coordinates[1];
        if (this.hasLonTarget) this.lonTarget.value = feature.geometry.coordinates[0];

        if (this.hasCityTarget) this.cityTarget.value = feature.properties.city || '';
        if (this.hasPostcodeTarget) this.postcodeTarget.value = feature.properties.postcode || '';
        if (this.hasStreetTarget) {
            if (feature.properties.type === 'housenumber' || feature.properties.type === 'street') {
                this.streetTarget.value = feature.properties.name || '';
            } else {
                this.streetTarget.value = ''; // Don't put city name in street field
            }
        }

        // Clear results
        this.resultsTarget.innerHTML = '';
        this.resultsTarget.classList.add('d-none');
        this.resetStyles(this.inputTarget.parentElement);
    }

    locate(event) {
        event.preventDefault();

        if (!navigator.geolocation) {
            alert('La géolocalisation n\'est pas supportée par votre navigateur.');
            return;
        }

        // Visual feedback
        const originalText = event.currentTarget.innerHTML;
        event.currentTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Localisation...';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;

                if (this.hasLatTarget) this.latTarget.value = lat;
                if (this.hasLonTarget) this.lonTarget.value = lon;

                // Reverse geocoding to get address name (optional but nice)
                fetch(`https://api-adresse.data.gouv.fr/reverse/?lon=${lon}&lat=${lat}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.features && data.features.length > 0) {
                            this.inputTarget.value = data.features[0].properties.label;
                        } else {
                            this.inputTarget.value = "Ma position actuelle";
                        }

                        // Reset button text
                        event.currentTarget.innerHTML = originalText;

                        // Update dropdown text to show "Ma position" is selected if needed
                        const distanceLabel = document.getElementById('distanceLabel');
                        if (distanceLabel) distanceLabel.textContent = 'Autour de moi';
                        document.getElementById('distanceInput').value = 'user_loc';

                    })
                    .catch(() => {
                        this.inputTarget.value = `${lat}, ${lon}`;
                        event.currentTarget.innerHTML = originalText;
                    });
            },
            (error) => {
                console.error(error);
                alert('Impossible de vous localiser.');
                event.currentTarget.innerHTML = originalText;
            }
        );
    }

    resetStyles(wrapper) {
        if (wrapper) {
            wrapper.classList.remove('rounded-top-4', 'rounded-bottom-0');
            wrapper.classList.add('rounded-pill');
        }
    }
}

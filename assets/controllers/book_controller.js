import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        animals: Array
    }

    connect() {
        this.setupAll();
    }

    setupAll() {
        this.setupCalendarSlots();
        this.setupAnimalSelector();

        // Sync slot into confirmation form before submit
        const confirmForm = document.getElementById('confirmBookingForm');
        if (confirmForm) {
            confirmForm.addEventListener('submit', () => {
                const slotInput = document.getElementById('confirmSlotInput');
                const selectedSlotInput = document.getElementById('selectedSlotInput');
                // The animal fields are already inside the form, so no need to sync them
                if (slotInput && selectedSlotInput) slotInput.value = selectedSlotInput.value;
            });
        }
    }

    setupCalendarSlots() {
        const slotBtns = this.element.querySelectorAll('.slot-btn');
        const hiddenInput = document.getElementById('selectedSlotInput');

        // Restore previously selected slot from URL
        const params = new URLSearchParams(window.location.search);
        const savedSlot = params.get('slot');
        
        // Reset all buttons first to avoid duplicates
        slotBtns.forEach(b => {
            b.classList.remove('bg-secondary', 'text-dark', 'border-secondary');
            b.classList.add('bg-white', 'border-neutral-40', 'text-dark');
        });

        if (savedSlot) {
            slotBtns.forEach(b => {
                if (b.getAttribute('data-slot') === savedSlot) {
                    b.classList.remove('bg-white', 'border-neutral-40', 'text-dark');
                    b.classList.add('bg-secondary', 'text-dark', 'border-secondary');
                    if (hiddenInput) hiddenInput.value = savedSlot;
                }
            });
        }
        
        slotBtns.forEach(btn => {
            if (btn.dataset.boundSlot) return;
            btn.dataset.boundSlot = 'true';

            btn.addEventListener('click', () => {
                slotBtns.forEach(b => {
                    b.classList.remove('bg-secondary', 'text-dark', 'border-secondary');
                    b.classList.add('bg-white', 'border-neutral-40', 'text-dark');
                });
                btn.classList.remove('bg-white', 'border-neutral-40', 'text-dark');
                btn.classList.add('bg-secondary', 'text-dark', 'border-secondary');

                const slot = btn.getAttribute('data-slot');
                if (hiddenInput) hiddenInput.value = slot;

                const url = new URL(window.location);
                url.searchParams.set('slot', slot);
                url.hash = 'horaires';
                window.history.replaceState({}, '', url);
            });
        });
    }

    setupAnimalSelector() {
        const animalsData = this.animalsValue || [];
        const selectorBtns = this.element.querySelectorAll('.animal-selector-btn');
        const newAnimalBtn = document.getElementById('newAnimalBtn');
        const clearBtn = document.getElementById('clearAnimalFormBtn');

        const fillForm = (animal) => {
            const el = id => document.getElementById(id);
            if(el('selectedAnimalId')) el('selectedAnimalId').value = animal ? animal.id : '';
            if(el('animalNom')) el('animalNom').value = animal ? animal.nom : '';
            if(el('animalEspece')) el('animalEspece').value = animal ? (animal.espece || '') : '';
            if(el('animalRace')) el('animalRace').value = animal ? (animal.race || '') : '';
            if(el('animalAge')) el('animalAge').value = animal ? (animal.age || '') : '';
            if(el('animalPoids')) el('animalPoids').value = animal ? (animal.poids || '') : '';
            if (animal && animal.vaccinAJour) {
                if(el('vaccinOui')) el('vaccinOui').checked = true;
            } else {
                if(el('vaccinNon')) el('vaccinNon').checked = true;
            }
        };

        const setActiveBtn = (activeBtn) => {
            selectorBtns.forEach(b => {
                b.classList.remove('btn-primary', 'text-white');
                b.classList.add('bg-white', 'border-neutral-40', 'text-dark');
            });
            if (activeBtn) {
                activeBtn.classList.remove('bg-white', 'border-neutral-40', 'text-dark');
                activeBtn.classList.add('btn-primary', 'text-white');
            }
        };

        selectorBtns.forEach(btn => {
            if (btn.dataset.boundAnimal) return;
            btn.dataset.boundAnimal = 'true';

            btn.addEventListener('click', () => {
                const animalId = parseInt(btn.getAttribute('data-animal-id'));
                const animal = animalsData.find(a => a.id === animalId);
                setActiveBtn(btn);
                fillForm(animal);
            });
        });

        if (newAnimalBtn && !newAnimalBtn.dataset.bound) {
            newAnimalBtn.dataset.bound = 'true';
            newAnimalBtn.addEventListener('click', () => {
                setActiveBtn(null);
                fillForm(null);
            });
        }

        if (clearBtn && !clearBtn.dataset.bound) {
            clearBtn.dataset.bound = 'true';
            clearBtn.addEventListener('click', () => {
                setActiveBtn(null);
                fillForm(null);
            });
        }
    }
}

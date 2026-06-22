import { Controller } from '@hotwired/stimulus';
import { Toast } from 'bootstrap';

export default class extends Controller {
    static targets = ["cancelButton", "saveButton"];
    static values = { url: String };

    cancel(event) {
        event.preventDefault();
        // Dispatch cancel event to all editable fields
        const cancelEvent = new CustomEvent('profile-form:cancel', { bubbles: true, cancelable: true });
        window.dispatchEvent(cancelEvent);
    }

    async save(event) {
        event.preventDefault();

        const data = {};
        const inputs = this.element.querySelectorAll('input[data-editable-field-target="input"]');
        let hasError = false;

        inputs.forEach(input => {
            if (input.name) {
                if (input.type === 'email' && input.value) {
                    const emailRegex = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;
                    if (!emailRegex.test(input.value)) {
                        this.showNotification('L\'adresse email n\'est pas au bon format.', 'danger');
                        hasError = true;
                    }
                }
                data[input.name] = input.value;
            }
        });

        if (hasError) return;
        
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error('Erreur réseau');
            }

            // Dispatch save event to all editable fields to update the UI
            const saveEvent = new CustomEvent('profile-form:save', { bubbles: true, cancelable: true });
            window.dispatchEvent(saveEvent);

            this.showNotification('Modifications enregistrées avec succès !', 'success');

            // Refresh the page after a short delay to show the notification
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            console.error('Erreur:', error);
            this.showNotification('Une erreur est survenue lors de l\'enregistrement.', 'danger');
        }
    }

    showNotification(message, type) {
        const toastEl = document.getElementById('statusToast');
        const toastBody = toastEl.querySelector('.toast-body');
        
        if (type === 'success') {
            toastEl.classList.add('text-bg-success');
        } else if (type === 'danger') {
            toastEl.classList.add('text-bg-danger');
        } else {
            toastEl.classList.add('text-bg-primary');
        }
        
        toastBody.classList.add('text-white');

        toastBody.textContent = message;

        const toast = new Toast(toastEl);
        toast.show();
    }
}

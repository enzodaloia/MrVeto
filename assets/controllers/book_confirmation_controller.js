import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["form", "submitBtn", "modalTrigger"];

    submit(event) {
        event.preventDefault();
        
        const form = this.hasFormTarget ? this.formTarget : this.element.querySelector('#confirmSaveForm');
        const submitBtn = this.hasSubmitBtnTarget ? this.submitBtnTarget : this.element.querySelector('#confirmAjaxBtn');
        const modalTriggerBtn = this.hasModalTriggerTarget ? this.modalTriggerTarget : document.getElementById('triggerSuccessModalBtn');
        
        if (!form || !submitBtn || !modalTriggerBtn) return;

        const formData = new FormData(form);
        const url = form.getAttribute('data-action') || form.action;
        
        // Disable button during save
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enregistrement...';

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.redirected) {
                // Si Symfony fait une redirection quand même
                window.location.href = response.url;
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (!data) return; // redirected
            
            if (data.success) {
                // Click the hidden button to show the Bootstrap modal safely
                modalTriggerBtn.click();
            } else {
                alert('Erreur: impossible de sauvegarder le rendez-vous.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
}

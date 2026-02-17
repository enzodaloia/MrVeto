import { Controller } from '@hotwired/stimulus';

/*
 * This is an example Stimulus controller!
 *
 * Any element with a data-controller="hello" attribute will cause
 * this controller to be executed. The name "hello" comes from the filename:
 * hello_controller.js -> "hello"
 *
 * Delete this file or adapt it for your use!
 */
export default class extends Controller {
    static targets = ["display", "input", "editButton"];

    connect() {
        // Store original value to revert later
        this.originalValue = this.inputTarget.value;
    }

    edit(event) {
        event.preventDefault();
        this.displayTarget.classList.add('d-none');
        this.inputTarget.classList.remove('d-none');
        this.editButtonTarget.classList.add('d-none');
        this.inputTarget.focus();
    }

    save() {
        this.displayTarget.textContent = this.inputTarget.value;
        this.originalValue = this.inputTarget.value; 
        this.resetView();
    }

    cancel() {
        this.inputTarget.value = this.originalValue;
        this.resetView();
    }

    resetView() {
        this.displayTarget.classList.remove('d-none');
        this.inputTarget.classList.add('d-none');
        this.editButtonTarget.classList.remove('d-none');
    }
}

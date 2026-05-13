import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.format();
    }

    format() {
        this.element.value = this.element.value.replace(/[^\d+]/g, '');
        
        if (this.element.value.startsWith('+33')) {
            this.element.maxLength = 12;
        } else {
            this.element.maxLength = 10;
        }
    }
}

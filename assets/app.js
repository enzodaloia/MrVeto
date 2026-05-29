import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import './styles/app.scss';

import '@popperjs/core';
import 'bootstrap';
import { Toast } from 'bootstrap';
import './bootstrap.js';
import './lucide.js';
import './stimulus_bootstrap.js';
import { Calendar } from '@fullcalendar/core';
import timeGridPlugin from '@fullcalendar/timegrid';
import frLocale from '@fullcalendar/core/locales/fr';

// Expose FullCalendar for inline availability script.
window.FullCalendar = {
	Calendar,
	timeGridPlugin,
	frLocale,
};

const initToasts = () => {
	document.querySelectorAll('[data-auto-init="toast"]').forEach((element) => {
		Toast.getOrCreateInstance(element).show();
	});
};

document.addEventListener('DOMContentLoaded', initToasts);
document.addEventListener('turbo:load', initToasts);

console.log('Assets loaded!');

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import frLocale from '@fullcalendar/core/locales/fr';

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('planning-calendar');
    if (!calendarEl) return;

    const businessHours = JSON.parse(calendarEl.dataset.businessHours || '[]');
    const eventsUrl = calendarEl.dataset.eventsUrl;
    const gestionRdvUrl = calendarEl.dataset.gestionRdvUrl;

    // Popover element
    const popoverEl = document.getElementById('planning-popover');
    let activePopover = null;

    function closePopover() {
        if (popoverEl) {
            popoverEl.classList.add('d-none');
            popoverEl.classList.remove('show');
        }
        activePopover = null;
    }

    const statusLabels = {
        en_attente: { label: 'En attente', cls: 'warning' },
        termine: { label: 'Terminé', cls: 'success' },
        annule: { label: 'Annulé', cls: 'secondary' },
        deplace: { label: 'Déplacé', cls: 'warning' },
    };

    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin],
        locale: frLocale,
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
        },
        buttonText: {
            today: "Aujourd'hui",
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour',
        },
        nowIndicator: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00',
        allDaySlot: false,
        businessHours: businessHours.length > 0 ? businessHours : false,
        height: 'auto',
        stickyHeaderDates: false,
        events: {
            url: eventsUrl,
            method: 'GET',
            extraParams: function () {
                return {};
            },
            failure: function () {
                console.error('Erreur lors du chargement des rendez-vous.');
            },
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            info.jsEvent.stopPropagation();

            const props = info.event.extendedProps;
            const statut = statusLabels[props.statut] || { label: props.statut, cls: 'secondary' };
            const startStr = info.event.start
                ? info.event.start.toLocaleString('fr-FR', {
                      weekday: 'long',
                      day: '2-digit',
                      month: 'long',
                      hour: '2-digit',
                      minute: '2-digit',
                  })
                : '';

            if (popoverEl) {
                document.getElementById('pop-title').textContent = info.event.title;
                document.getElementById('pop-start').textContent = startStr;
                document.getElementById('pop-client').textContent = props.client || '–';
                document.getElementById('pop-animal').textContent = props.animal || '–';
                document.getElementById('pop-motif').textContent = props.motif || '–';

                const badgeEl = document.getElementById('pop-statut');
                badgeEl.textContent = statut.label;
                badgeEl.className = 'badge bg-' + statut.cls + ' text-' + (statut.cls === 'warning' ? 'dark' : 'white');

                const linkEl = document.getElementById('pop-link');
                if (linkEl) {
                    linkEl.href = gestionRdvUrl || '#';
                }

                // Position near the click
                const rect = info.el.getBoundingClientRect();
                const scrollY = window.scrollY || document.documentElement.scrollTop;
                const scrollX = window.scrollX || document.documentElement.scrollLeft;

                popoverEl.classList.remove('d-none');
                popoverEl.classList.add('show');

                // Adjust position after making visible (to get dimensions)
                requestAnimationFrame(() => {
                    const popW = popoverEl.offsetWidth;
                    const popH = popoverEl.offsetHeight;
                    const vpW = window.innerWidth;
                    const vpH = window.innerHeight;

                    let top = rect.top + scrollY - popH - 8;
                    let left = rect.left + scrollX + rect.width / 2 - popW / 2;

                    // Clamp to viewport
                    if (top < scrollY + 8) top = rect.bottom + scrollY + 8;
                    if (left < scrollX + 8) left = scrollX + 8;
                    if (left + popW > scrollX + vpW - 8) left = scrollX + vpW - popW - 8;

                    popoverEl.style.top = top + 'px';
                    popoverEl.style.left = left + 'px';
                });

                activePopover = info.event.id;
            }
        },
        eventMouseLeave: function () {
            // Keep popover open on mouse leave — closed by click outside
        },
    });

    calendar.render();

    // Close popover on outside click
    document.addEventListener('click', function (e) {
        if (popoverEl && !popoverEl.contains(e.target) && activePopover !== null) {
            closePopover();
        }
    });

    const closeBtnEl = document.getElementById('pop-close');
    if (closeBtnEl) {
        closeBtnEl.addEventListener('click', closePopover);
    }
});

import '../styles/pages/planning.scss';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import frLocale from '@fullcalendar/core/locales/fr';

// ─── TURBO SAFETY: close popover BEFORE the page is cached/snapshotted ──────
// Without this, the popover stays visible on back-navigation and blocks all clicks.
document.addEventListener('turbo:before-cache', function () {
    const pop = document.getElementById('planning-popover');
    if (pop) pop.classList.add('d-none');
});
document.addEventListener('turbo:visit', function () {
    const pop = document.getElementById('planning-popover');
    if (pop) pop.classList.add('d-none');
});

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('planning-calendar');
    if (!calendarEl) return;

    const businessHours    = JSON.parse(calendarEl.dataset.businessHours || '[]');
    const eventsUrl        = calendarEl.dataset.eventsUrl;
    const gestionRdvUrl    = calendarEl.dataset.gestionRdvUrl;
    const gestionRdvPrefix = calendarEl.dataset.gestionRdvPrefix || '/gestion-rdv';

    // ─── STATUS CONFIG ──────────────────────────────────────────────────────────
    const STATUS = {
        en_attente: { label: 'En attente', badgeCls: 'bg-warning text-dark',  color: '#FFCF00' },
        confirme:   { label: 'Confirmé',   badgeCls: 'bg-primary text-white', color: '#1a73e8' },
        termine:    { label: 'Terminé',    badgeCls: 'bg-success text-white', color: '#36BDAF' },
        annule:     { label: 'Annulé',     badgeCls: 'bg-secondary text-white', color: '#9e9e9e' },
        deplace:    { label: 'Déplacé',    badgeCls: 'bg-warning text-dark',  color: '#fd7e14' },
    };

    // ─── POPOVER ────────────────────────────────────────────────────────────────
    const popoverEl       = document.getElementById('planning-popover');
    let   activeEventSlug = null;

    function closePopover() {
        popoverEl.classList.add('d-none');
        activeEventSlug = null;
    }

    function positionPopover(triggerRect) {
        requestAnimationFrame(() => {
            const popW = popoverEl.offsetWidth;
            const popH = popoverEl.offsetHeight;
            const vpW  = window.innerWidth;
            const vpH  = window.innerHeight;

            let top  = triggerRect.top - popH - 8;
            let left = triggerRect.left + triggerRect.width / 2 - popW / 2;

            if (top  < 8)              top  = triggerRect.bottom + 8;
            if (left < 8)              left = 8;
            if (left + popW > vpW - 8) left = vpW - popW - 8;
            if (top  + popH > vpH - 8) top  = vpH - popH - 8;

            popoverEl.style.top  = top  + 'px';
            popoverEl.style.left = left + 'px';
        });
    }

    // ─── MINI CALENDAR ──────────────────────────────────────────────────────────
    const miniCalEl = document.getElementById('mini-cal');
    const MONTH_NAMES = ['Janvier','Février','Mars','Avril','Mai','Juin',
                         'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const DOW_LABELS  = ['L','M','M','J','V','S','D'];

    let miniCalMonth    = new Date();
    let miniCalSelected = null; // 'YYYY-MM-DD'

    function renderMiniCal() {
        if (!miniCalEl) return;
        const today  = new Date();
        const year   = miniCalMonth.getFullYear();
        const month  = miniCalMonth.getMonth();

        // ISO week starts Monday: getDay() 0=Sun → offset 6, 1=Mon → 0, …
        let startOffset = new Date(year, month, 1).getDay();
        startOffset = startOffset === 0 ? 6 : startOffset - 1;

        const daysInMonth  = new Date(year, month + 1, 0).getDate();
        const totalCells   = Math.ceil((startOffset + daysInMonth) / 7) * 7;

        let html = `
        <div class="mini-cal-header">
            <span class="mini-cal-header-title">${MONTH_NAMES[month]} ${year}</span>
            <div class="d-flex">
                <button class="mini-cal-nav" id="mini-prev"><i class="bi bi-chevron-left"></i></button>
                <button class="mini-cal-nav" id="mini-next"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
        <div class="mini-cal-grid">
        ${DOW_LABELS.map(d => `<div class="mini-cal-dow">${d}</div>`).join('')}`;

        const prevMonthDays = new Date(year, month, 0).getDate();
        for (let i = 0; i < totalCells; i++) {
            let dayNum, cls = 'mini-cal-day', dateStr = '';

            if (i < startOffset) {
                dayNum = prevMonthDays - startOffset + i + 1;
                cls += ' other-month';
            } else if (i < startOffset + daysInMonth) {
                dayNum   = i - startOffset + 1;
                const d  = String(dayNum).padStart(2, '0');
                const m  = String(month + 1).padStart(2, '0');
                dateStr  = `${year}-${m}-${d}`;
                const isToday    = today.getFullYear() === year && today.getMonth() === month && today.getDate() === dayNum;
                const isSelected = miniCalSelected === dateStr;
                if (isToday)         cls += ' today';
                else if (isSelected) cls += ' selected';
            } else {
                dayNum = i - startOffset - daysInMonth + 1;
                cls += ' other-month';
            }

            html += `<div class="${cls}" data-date="${dateStr}">${dayNum}</div>`;
        }

        html += '</div>';
        miniCalEl.innerHTML = html;

        document.getElementById('mini-prev').addEventListener('click', (e) => {
            e.stopPropagation();
            miniCalMonth = new Date(year, month - 1, 1);
            renderMiniCal();
        });
        document.getElementById('mini-next').addEventListener('click', (e) => {
            e.stopPropagation();
            miniCalMonth = new Date(year, month + 1, 1);
            renderMiniCal();
        });

        miniCalEl.querySelectorAll('.mini-cal-day:not(.other-month)').forEach(el => {
            el.addEventListener('click', () => {
                const date = el.dataset.date;
                if (!date) return;
                miniCalSelected = date;
                renderMiniCal();
                calendar.gotoDate(date);
            });
        });
    }

    // ─── TODAY EVENTS SIDEBAR ───────────────────────────────────────────────────
    async function fetchAndRenderToday() {
        const todayEl = document.getElementById('today-events-list');
        if (!todayEl) return;

        const now      = new Date();
        const startStr = now.toISOString().split('T')[0] + 'T00:00:00';
        const endDate  = new Date(now.getTime() + 86400000);
        const endStr   = endDate.toISOString().split('T')[0] + 'T00:00:00';

        try {
            const res    = await fetch(`${eventsUrl}?start=${startStr}&end=${endStr}`);
            const events = res.ok ? await res.json() : [];

            if (events.length === 0) {
                todayEl.innerHTML = '<div class="text-muted" style="font-size:0.78rem;padding:4px 8px;">Aucun RDV aujourd\'hui</div>';
                return;
            }

            todayEl.innerHTML = events.map(ev => {
                const statut = ev.extendedProps?.statut || 'en_attente';
                const color  = STATUS[statut]?.color || '#36BDAF';
                const time   = new Date(ev.start).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                const label  = ev.extendedProps?.animal || ev.extendedProps?.motif || 'RDV';
                const client = ev.extendedProps?.client || '';
                return `<div class="today-event-item" data-id="${ev.id}">
                    <span class="today-event-dot" style="background:${color};"></span>
                    <div>
                        <div class="today-event-name">${label}</div>
                        <div class="today-event-time">${time}${client ? ' · ' + client : ''}</div>
                    </div>
                </div>`;
            }).join('');

            todayEl.querySelectorAll('.today-event-item').forEach(el => {
                el.addEventListener('click', () => {
                    const fcEvent = calendar.getEventById(el.dataset.id);
                    if (fcEvent?.start) {
                        calendar.changeView('timeGridDay', fcEvent.start);
                        updateViewButtons('timeGridDay');
                    }
                });
            });
        } catch (e) {
            todayEl.innerHTML = '<div class="text-muted" style="font-size:0.78rem;padding:4px 8px;">Erreur de chargement</div>';
        }
    }

    // ─── TOOLBAR ────────────────────────────────────────────────────────────────
    function updateToolbarTitle(title) {
        const el = document.getElementById('toolbar-title');
        if (el) el.textContent = title;
    }

    function updateViewButtons(viewType) {
        document.querySelectorAll('.toolbar-view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === viewType);
        });
    }

    // ─── MOBILE DETECTION ────────────────────────────────────────────────────────
    const isMobile = () => window.innerWidth < 768;

    // ─── CALENDAR ───────────────────────────────────────────────────────────────
    const calendar = new Calendar(calendarEl, {
        plugins:     [dayGridPlugin, timeGridPlugin],
        locale:      frLocale,
        initialView: isMobile() ? 'timeGridDay' : 'timeGridWeek',
        headerToolbar: false,

        nowIndicator:     true,
        slotMinTime:      '00:00:00',
        slotMaxTime:      '24:00:00',
        scrollTime:       (function () {
            // Scroll to 1h before current time so the now-indicator is visible
            const h = Math.max(0, new Date().getHours() - 1);
            return String(h).padStart(2, '0') + ':00:00';
        })(),
        slotDuration:     '00:30:00',
        slotLabelInterval:'01:00',
        allDaySlot:       false,
        businessHours:    businessHours.length > 0 ? businessHours : false,
        height:           '100%',
        stickyHeaderDates: true,

        events: {
            url:     eventsUrl,
            method:  'GET',
            failure: () => console.error('Erreur de chargement des RDV.'),
        },

        // Sync toolbar title + view buttons + mini calendar month
        datesSet(info) {
            updateToolbarTitle(info.view.title);
            updateViewButtons(info.view.type);
            // On mobile, keep "Semaine" button invisible but mark correct active
            miniCalMonth = new Date(info.view.currentStart);
            renderMiniCal();
        },

        // Click on a day cell in month view → switch to day view
        dateClick(info) {
            if (calendar.view.type === 'dayGridMonth') {
                calendar.changeView('timeGridDay', info.date);
                updateViewButtons('timeGridDay');
            }
        },

        eventClick(info) {
            info.jsEvent.preventDefault();
            info.jsEvent.stopPropagation();

            const ev    = info.event;
            const props = ev.extendedProps;
            const statut = props.statut || 'en_attente';
            const sc     = STATUS[statut] || STATUS.en_attente;

            const startStr = ev.start
                ? ev.start.toLocaleString('fr-FR', {
                    weekday: 'short', day: '2-digit',
                    month: 'short', hour: '2-digit', minute: '2-digit',
                  })
                : '';

            document.getElementById('pop-title').textContent  = ev.title;
            document.getElementById('pop-start').textContent  = startStr;
            document.getElementById('pop-client').textContent = props.client || '–';
            document.getElementById('pop-animal').textContent = props.animal || '–';
            document.getElementById('pop-motif').textContent  = props.motif  || '–';
            document.getElementById('pop-statut-wrap').innerHTML =
                `<span class="badge ${sc.badgeCls} rounded-pill px-2" style="font-size:0.68rem;">${sc.label}</span>`;

            // Action buttons
            const slug       = props.slug || null;
            const confirmBtn = document.getElementById('pop-btn-confirm');
            const cancelBtn  = document.getElementById('pop-btn-cancel');

            confirmBtn.classList.toggle('d-none', statut !== 'en_attente');
            cancelBtn.classList.toggle('d-none',  statut === 'annule' || statut === 'termine');

            // Reset handlers
            confirmBtn.replaceWith(confirmBtn.cloneNode(true));
            cancelBtn.replaceWith(cancelBtn.cloneNode(true));
            const newConfirm = document.getElementById('pop-btn-confirm');
            const newCancel  = document.getElementById('pop-btn-cancel');

            if (slug) {
                newConfirm.addEventListener('click', async () => {
                    newConfirm.disabled = true;
                    const res = await fetch(`${gestionRdvPrefix}/${slug}/confirmer`, {
                        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).catch(() => null);
                    if (res?.ok) { closePopover(); calendar.refetchEvents(); fetchAndRenderToday(); }
                    else newConfirm.disabled = false;
                });

                newCancel.addEventListener('click', async () => {
                    if (!confirm('Annuler ce rendez-vous ?')) return;
                    newCancel.disabled = true;
                    const res = await fetch(`${gestionRdvPrefix}/${slug}/annuler`, {
                        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).catch(() => null);
                    if (res?.ok) { closePopover(); calendar.refetchEvents(); fetchAndRenderToday(); }
                    else newCancel.disabled = false;
                });
            }

            document.getElementById('pop-link').href = gestionRdvUrl || '#';

            // Close popover on navigation (belt-and-suspenders alongside turbo:before-cache)
            document.getElementById('pop-link').addEventListener('click', closePopover, { once: true });

            popoverEl.classList.remove('d-none');
            activeEventSlug = slug;
            positionPopover(info.el.getBoundingClientRect());
        },
    });

    calendar.render();

    // ─── TOOLBAR BINDINGS ───────────────────────────────────────────────────────
    document.getElementById('btn-today').addEventListener('click', () => {
        calendar.today();
        miniCalMonth = new Date();
        renderMiniCal();
    });
    document.getElementById('btn-prev').addEventListener('click', () => calendar.prev());
    document.getElementById('btn-next').addEventListener('click', () => calendar.next());

    document.querySelectorAll('.toolbar-view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            calendar.changeView(btn.dataset.view);
            updateViewButtons(btn.dataset.view);
        });
    });

    // ─── POPOVER CLOSE ──────────────────────────────────────────────────────────
    document.getElementById('pop-close').addEventListener('click', closePopover);
    document.addEventListener('click', (e) => {
        if (!popoverEl.classList.contains('d-none') &&
            !popoverEl.contains(e.target) &&
            activeEventSlug !== null) {
            closePopover();
        }
    });

    // ─── RESPONSIVE: switch view on resize ──────────────────────────────────────
    let lastMobile = isMobile();
    window.addEventListener('resize', () => {
        const nowMobile = isMobile();
        if (nowMobile === lastMobile) return;
        lastMobile = nowMobile;
        if (nowMobile) {
            calendar.changeView('timeGridDay');
            updateViewButtons('timeGridDay');
        } else {
            calendar.changeView('timeGridWeek');
            updateViewButtons('timeGridWeek');
        }
    });

    // ─── INIT ───────────────────────────────────────────────────────────────────
    renderMiniCal();
    fetchAndRenderToday();
});

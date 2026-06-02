<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if ($isAdminRole ?? false): ?>
        <a href="/pasar-lista" class="btn-jp btn-jp-sm" style="background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">
            <i class="bi bi-clipboard2-check-fill me-1"></i>Pasar Lista
        </a>
        <?php endif; ?>
        <?php if ($canManage): ?>
        <a href="/clases/nueva" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva clase (avanzada)
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>
<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<!-- ── Métricas ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Esta semana</span>
                <div class="metric-icon blue"><i class="bi bi-calendar-week"></i></div>
            </div>
            <div class="metric-value"><?= $stats['this_week'] ?></div>
            <div class="metric-footer"><span class="metric-footer-label">clases programadas</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Este mes</span>
                <div class="metric-icon green"><i class="bi bi-calendar-month"></i></div>
            </div>
            <div class="metric-value"><?= $stats['this_month'] ?></div>
            <div class="metric-footer"><span class="metric-footer-label">total del mes</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Jugadores activos</span>
                <div class="metric-icon orange"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="metric-value"><?= $stats['active_players'] ?></div>
            <div class="metric-footer"><span class="metric-footer-label">en clases este mes</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Asistencia media</span>
                <div class="metric-icon purple"><i class="bi bi-bar-chart-fill"></i></div>
            </div>
            <div class="metric-value"><?= $stats['avg_attendance'] !== null ? $stats['avg_attendance'] . '%' : '—' ?></div>
            <div class="metric-footer"><span class="metric-footer-label">últimas 4 semanas</span></div>
        </div>
    </div>
</div>

<!-- ── Calendario ────────────────────────────────────────────── -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>
            Calendario
        </span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Leyenda -->
            <div class="d-flex gap-2" style="font-size:11.5px">
                <span style="display:flex;align-items:center;gap:4px">
                    <span style="width:10px;height:10px;border-radius:50%;background:#3b82f6;display:inline-block"></span>Programada
                </span>
                <span style="display:flex;align-items:center;gap:4px">
                    <span style="width:10px;height:10px;border-radius:50%;background:#10b981;display:inline-block"></span>Completada
                </span>
            </div>
            <!-- Vista tabs -->
            <div class="calendar-view-tabs">
                <button class="calendar-view-tab active" onclick="CAL.switchView('month', this)">Mes</button>
                <button class="calendar-view-tab" onclick="CAL.switchView('week', this)">Semana</button>
                <button class="calendar-view-tab" onclick="CAL.switchView('day', this)">Día</button>
            </div>
        </div>
    </div>
    <div class="card-jp-body">
        <!-- Toolbar navegación -->
        <div class="calendar-toolbar">
            <div class="calendar-nav">
                <button onclick="CAL.prev()" title="Anterior"><i class="bi bi-chevron-left"></i></button>
                <button onclick="CAL.today()" style="padding:0 12px;width:auto;font-size:12px;font-weight:600">Hoy</button>
                <span class="calendar-nav-label" id="cal-label">Cargando…</span>
                <button onclick="CAL.next()" title="Siguiente"><i class="bi bi-chevron-right"></i></button>
            </div>
            <?php if ($canManage): ?>
            <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="ClaseModal.open()">
                <i class="bi bi-plus-lg me-1"></i>Añadir sesión
            </button>
            <?php endif; ?>
        </div>

        <!-- Grid del calendario -->
        <div id="cal-grid"></div>
    </div>
</div>

<?php if ($canManage): ?>
    <?= $this->include('clases/_modal_create') ?>
<?php endif; ?>


<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
/* ── Calendario Mensual ────────────────────────────────────────── */
.cal-month-headers {
    display:grid;grid-template-columns:repeat(7,1fr);
    background:var(--bg-app);border:1px solid var(--border);
    border-bottom:none;border-radius:var(--radius-sm) var(--radius-sm) 0 0;
}
.cal-day-header {
    padding:8px;text-align:center;font-size:11px;font-weight:700;
    text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);
}
.cal-month-grid {
    display:grid;grid-template-columns:repeat(7,1fr);
    gap:1px;background:var(--border);
    border:1px solid var(--border);border-radius:0 0 var(--radius-sm) var(--radius-sm);overflow:hidden;
}
.cal-cell {
    background:var(--bg-card);min-height:90px;padding:5px;cursor:default;
    transition:background .1s;
}
.cal-cell:hover { background:#f8fafc; }
.cal-cell.other  { background:#f8fafc;opacity:.5; }
.cal-cell.today  { background:var(--accent-light); }
.cal-day-num {
    font-size:12px;font-weight:700;color:var(--text-muted);
    margin-bottom:4px;line-height:1;
}
.cal-cell.today .cal-day-num {
    background:var(--accent);color:#fff;
    border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;
}
.cal-chip {
    display:block;font-size:10.5px;font-weight:600;padding:2px 5px;
    border-radius:4px;margin-bottom:2px;text-decoration:none;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    line-height:1.4;
}
.cal-chip:hover { opacity:.85; }
.cal-more { font-size:10px;color:var(--text-muted);padding:1px 4px; }

/* ── Calendario Semanal ────────────────────────────────────────── */
.cal-week-wrap { overflow-x:auto; }
.cal-week-grid {
    display:grid;grid-template-columns:48px repeat(7,1fr);
    min-width:640px;
}
.cal-week-head {
    padding:8px;text-align:center;border-bottom:2px solid var(--border);
    position:sticky;top:0;background:var(--bg-card);z-index:2;
}
.cal-week-head.time-col { border-right:1px solid var(--border); }
.cal-wday-name { font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px; }
.cal-wday-num  { font-size:20px;font-weight:800;color:var(--text-h);line-height:1.2; }
.cal-wday-today .cal-wday-num { color:var(--accent); }
.cal-week-body { display:contents; }
.cal-time-label {
    font-size:10px;color:var(--text-muted);padding:2px 4px 0;
    border-right:1px solid var(--border);border-bottom:1px solid #f1f5f9;
    height:56px;text-align:right;vertical-align:top;
}
.cal-hour-slot {
    position:relative;border-bottom:1px solid #f1f5f9;height:56px;
}
.cal-event-block {
    position:absolute;left:2px;right:2px;border-radius:5px;padding:3px 6px;
    font-size:11px;font-weight:600;text-decoration:none;overflow:hidden;
    white-space:nowrap;text-overflow:ellipsis;z-index:1;cursor:pointer;
}
.cal-event-block:hover { filter:brightness(.92); }
.cal-can-create { cursor:pointer; }
.cal-can-create:hover { background:var(--accent-light) !important; }

/* ── Calendario Día ────────────────────────────────────────────── */
.cal-day-grid {
    display:grid;grid-template-columns:48px 1fr;
    min-width:280px;
}
</style>

<script src="<?= base_url('assets/js/clase-modal.js') ?>"></script>
<script>
const canManage = <?= $canManage ? 'true' : 'false' ?>;
const CSRF_NAME = '<?= csrf_token() ?>';
const CSRF_HASH = '<?= csrf_hash() ?>';

// ── Calendarios ─────────────────────────────────────────────────
const CAL = {
    view: 'month',
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    weekStart: null,
    day: null,
    events: [],

    async load() {
        let year = this.year, month = this.month;
        if (this.view === 'week') {
            year = this.weekStartYear(); month = this.weekStartMonth();
        } else if (this.view === 'day' && this.day) {
            const p = this.day.split('-');
            year = parseInt(p[0]); month = parseInt(p[1]);
        }
        const url = `/clases/api/calendario?year=${year}&month=${month}`;
        try {
            const res = await fetch(url);
            this.events = await res.json();
        } catch(e) { this.events = []; }
        this.render();
    },

    render() {
        if (this.view === 'month') this.renderMonth();
        else if (this.view === 'week') this.renderWeek();
        else this.renderDay();
    },

    switchView(v, btn) {
        this.view = v;
        document.querySelectorAll('.calendar-view-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (v === 'week' && !this.weekStart) this.weekStart = this.getMonday(new Date());
        if (v === 'day'  && !this.day)       this.day = this.fmt(new Date());
        this.load();
    },

    prev() {
        if (this.view === 'month') {
            if (--this.month < 1) { this.month = 12; this.year--; }
        } else if (this.view === 'week') {
            const d = new Date(this.weekStart + 'T00:00:00');
            d.setDate(d.getDate() - 7);
            this.weekStart = this.fmt(d);
        } else {
            const d = new Date(this.day + 'T00:00:00');
            d.setDate(d.getDate() - 1);
            this.day = this.fmt(d);
        }
        this.load();
    },

    next() {
        if (this.view === 'month') {
            if (++this.month > 12) { this.month = 1; this.year++; }
        } else if (this.view === 'week') {
            const d = new Date(this.weekStart + 'T00:00:00');
            d.setDate(d.getDate() + 7);
            this.weekStart = this.fmt(d);
        } else {
            const d = new Date(this.day + 'T00:00:00');
            d.setDate(d.getDate() + 1);
            this.day = this.fmt(d);
        }
        this.load();
    },

    today() {
        const n = new Date();
        this.year = n.getFullYear();
        this.month = n.getMonth() + 1;
        this.weekStart = this.getMonday(n);
        this.day = this.fmt(n);
        this.load();
    },

    renderMonth() {
        const mn  = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        document.getElementById('cal-label').textContent = mn[this.month - 1] + ' ' + this.year;

        const first = new Date(this.year, this.month - 1, 1).getDay();
        const offset = (first + 6) % 7;
        const daysInMonth = new Date(this.year, this.month, 0).getDate();
        const todayStr = this.fmt(new Date());

        let html = '<div class="cal-month-headers">';
        ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'].forEach(d =>
            html += `<div class="cal-day-header">${d}</div>`
        );
        html += '</div><div class="cal-month-grid">';

        for (let i = 0; i < offset; i++) html += '<div class="cal-cell other"></div>';

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${this.year}-${String(this.month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const isToday = dateStr === todayStr;
            const dayEvts = this.events.filter(e => e.date === dateStr);

            html += `<div class="cal-cell${isToday ? ' today' : ''}${canManage ? ' cal-can-create' : ''}"
                         data-date="${dateStr}" onclick="handleCellClick(event, '${dateStr}')">`;
            html += `<div class="cal-day-num">${day}</div>`;

            const shown = dayEvts.slice(0, 3);
            shown.forEach(ev => {
                html += `<a href="/clases/${ev.id}" class="cal-chip"
                            style="background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44"
                            title="${ev.title} ${ev.start}–${ev.end}">
                            ${ev.start} ${ev.title}
                         </a>`;
            });
            if (dayEvts.length > 3) {
                html += `<div class="cal-more">+${dayEvts.length - 3} más</div>`;
            }

            html += '</div>';
        }

        const total = offset + daysInMonth;
        const fill  = (7 - (total % 7)) % 7;
        for (let i = 0; i < fill; i++) html += '<div class="cal-cell other"></div>';
        html += '</div>';

        document.getElementById('cal-grid').innerHTML = html;
    },

    renderWeek() {
        if (!this.weekStart) this.weekStart = this.getMonday(new Date());

        const ws = new Date(this.weekStart + 'T00:00:00');
        const we = new Date(ws); we.setDate(we.getDate() + 6);
        const todayStr = this.fmt(new Date());

        const days = [], dates = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(ws); d.setDate(d.getDate() + i);
            days.push(d);
            dates.push(this.fmt(d));
        }

        const mn = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        const dnames = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
        document.getElementById('cal-label').textContent =
            `${ws.getDate()} ${mn[ws.getMonth()]} – ${we.getDate()} ${mn[we.getMonth()]} ${we.getFullYear()}`;

        const HOUR_START = 7, HOUR_END = 22;
        const SLOT_H = 56;

        let html = '<div class="cal-week-wrap"><div class="cal-week-grid">';

        html += '<div class="cal-week-head time-col"></div>';
        days.forEach((d, i) => {
            const isT = dates[i] === todayStr;
            html += `<div class="cal-week-head${isT ? ' cal-wday-today' : ''}">
                        <div class="cal-wday-name">${dnames[i]}</div>
                        <div class="cal-wday-num">${d.getDate()}</div>
                     </div>`;
        });

        for (let h = HOUR_START; h < HOUR_END; h++) {
            html += `<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            dates.forEach((dateStr, di) => {
                const dayEvts = this.events.filter(e => {
                    if (e.date !== dateStr) return false;
                    const eh = parseInt(e.start.split(':')[0]);
                    return eh === h;
                });

                html += `<div class="cal-hour-slot${canManage ? ' cal-can-create' : ''}"
                              data-date="${dateStr}" data-hour="${h}"
                              onclick="handleSlotClick(event, '${dateStr}', ${h})">`;

                dayEvts.forEach(ev => {
                    const [sh, sm] = ev.start.split(':').map(Number);
                    const [eh2, em] = (ev.end || ev.start).split(':').map(Number);
                    const topPx = (sm / 60) * SLOT_H;
                    const durMin = (eh2 * 60 + em) - (sh * 60 + sm);
                    const heightPx = Math.max((durMin / 60) * SLOT_H, 22);

                    html += `<a href="/clases/${ev.id}" class="cal-event-block"
                                style="top:${topPx}px;height:${heightPx}px;background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44"
                                title="${ev.title} ${ev.start}–${ev.end}"
                                onclick="event.stopPropagation()">
                                ${ev.start} ${ev.title}
                             </a>`;
                });

                html += '</div>';
            });
        }

        html += '</div></div>';
        document.getElementById('cal-grid').innerHTML = html;
    },

    renderDay() {
        if (!this.day) this.day = this.fmt(new Date());
        const d        = new Date(this.day + 'T00:00:00');
        const dnames   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        const mn       = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        const todayStr = this.fmt(new Date());
        const isToday  = this.day === todayStr;

        document.getElementById('cal-label').textContent =
            `${dnames[d.getDay()]}, ${d.getDate()} de ${mn[d.getMonth()]} ${d.getFullYear()}`;

        const HOUR_START = 7, HOUR_END = 22, SLOT_H = 56;
        const dayEvts = this.events.filter(e => e.date === this.day);

        let html = '<div class="cal-week-wrap"><div class="cal-day-grid">';

        html += '<div class="cal-week-head time-col"></div>';
        html += `<div class="cal-week-head${isToday ? ' cal-wday-today' : ''}">
                    <div class="cal-wday-name">${dnames[d.getDay()].substring(0,3)}</div>
                    <div class="cal-wday-num">${d.getDate()}</div>
                 </div>`;

        for (let h = HOUR_START; h < HOUR_END; h++) {
            html += `<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            const slotEvts = dayEvts.filter(e => parseInt(e.start.split(':')[0]) === h);
            html += `<div class="cal-hour-slot${canManage ? ' cal-can-create' : ''}"
                         data-date="${this.day}" data-hour="${h}"
                         onclick="handleSlotClick(event, '${this.day}', ${h})">`;
            slotEvts.forEach(ev => {
                const [sh, sm] = ev.start.split(':').map(Number);
                const [eh2, em] = (ev.end || ev.start).split(':').map(Number);
                const topPx   = (sm / 60) * SLOT_H;
                const durMin  = (eh2 * 60 + em) - (sh * 60 + sm);
                const heightPx = Math.max((durMin / 60) * SLOT_H, 22);
                html += `<a href="/clases/${ev.id}" class="cal-event-block"
                            style="top:${topPx}px;height:${heightPx}px;background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44"
                            title="${ev.title} ${ev.start}–${ev.end}"
                            onclick="event.stopPropagation()">
                            ${ev.start} ${ev.title}
                         </a>`;
            });
            html += '</div>';
        }

        html += '</div></div>';
        document.getElementById('cal-grid').innerHTML = html;
    },

    getMonday(d) {
        const day  = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return this.fmt(new Date(d.setDate(diff)));
    },
    fmt(d) {
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    },
    weekStartYear()  { return this.weekStart ? parseInt(this.weekStart.split('-')[0]) : this.year; },
    weekStartMonth() { return this.weekStart ? parseInt(this.weekStart.split('-')[1]) : this.month; },
};

function handleCellClick(e, date) {
    if (e.target.closest('a')) return;
    if (canManage) {
        ClaseModal.open({ date });
    } else {
        // Non-managers: click month cell → jump to day view
        const btn = document.querySelector('.calendar-view-tab:last-child');
        CAL.day = date;
        CAL.view = 'day';
        document.querySelectorAll('.calendar-view-tab').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        CAL.load();
    }
}
function handleSlotClick(e, date, hour) {
    if (e.target.closest('a')) return;
    if (canManage) {
        const h = String(hour).padStart(2,'0');
        ClaseModal.open({ date, time: `${h}:00` });
    }
}

// Inicializar
CAL.load();

<?php if ($canManage): ?>
ClaseModal.init({
    csrfName: CSRF_NAME,
    csrfHash: CSRF_HASH,
    onCreated: () => CAL.load(),
});
<?php endif; ?>
</script>
<?= $this->endSection() ?>

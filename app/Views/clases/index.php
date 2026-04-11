<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Clases y Calendario</h2>
        <p>Sesiones de entrenamiento programadas</p>
    </div>
    <?php if ($canManage): ?>
    <a href="/clases/nueva" class="btn-jp btn-jp-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva clase
    </a>
    <?php endif; ?>
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
            <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="openQuickCreate()">
                <i class="bi bi-plus-lg me-1"></i>Añadir sesión
            </button>
            <?php endif; ?>
        </div>

        <!-- Grid del calendario -->
        <div id="cal-grid"></div>
    </div>
</div>

<!-- ── Modal: quick-create ───────────────────────────────────── -->
<?php if ($canManage): ?>
<div id="modalQuickCreate" class="cs-modal-overlay d-none">
    <div class="cs-modal" style="max-width:540px">
        <div class="cs-modal-header">
            <span><i class="bi bi-plus-circle-fill me-2" style="color:var(--accent)"></i>Nueva sesión rápida</span>
            <button onclick="closeModal('modalQuickCreate')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Título <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="qc-title" class="form-control-jp" placeholder="Ej: Entrenamiento individual – Control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="qc-date" class="form-control-jp" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Inicio <span style="color:var(--danger)">*</span></label>
                    <input type="time" id="qc-start" class="form-control-jp">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Fin</label>
                    <input type="time" id="qc-end" class="form-control-jp">
                </div>
                <div class="col-12">
                    <label class="form-label">Lugar (opcional)</label>
                    <input type="text" id="qc-location" class="form-control-jp" placeholder="Instalación o descripción">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Entrenadores</label>
                    <select id="qc-coach-sel" class="form-control-jp" onchange="qcAddCoach(this)">
                        <option value="">Añadir entrenador…</option>
                    </select>
                    <div id="qc-coach-list" class="mt-2" style="display:flex;flex-wrap:wrap;gap:4px"></div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Jugadores</label>
                    <select id="qc-player-sel" class="form-control-jp" onchange="qcAddPlayer(this)">
                        <option value="">Añadir jugador…</option>
                    </select>
                    <div id="qc-player-list" class="mt-2" style="display:flex;flex-wrap:wrap;gap:4px"></div>
                </div>
            </div>
            <div id="qc-error" style="color:var(--danger);font-size:13px;margin-top:10px;display:none"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid var(--border)">
            <button class="btn-jp btn-jp-secondary" onclick="closeModal('modalQuickCreate')">Cancelar</button>
            <button class="btn-jp btn-jp-primary" id="qc-submit" onclick="submitQuickCreate()">
                <i class="bi bi-check-lg me-1"></i>Crear sesión
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<style>
/* ── Modal ─────────────────────────────────────────────────────── */
.cs-modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1050;
    display:flex;align-items:center;justify-content:center;padding:16px;
}
.cs-modal {
    background:var(--bg-card);border:1px solid var(--border);border-radius:12px;
    width:100%;max-width:480px;max-height:90vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.cs-modal-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
    font-size:15px;font-weight:700;color:var(--text-h);
}
.cs-modal-header button {
    background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;
    width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;
    transition:background .15s;
}
.cs-modal-header button:hover { background:var(--bg-app);color:var(--text-h); }
.cs-modal-body { padding:20px;overflow-y:auto;flex:1; }

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

/* ── Tag chips para quick-create ───────────────────────────────── */
.qc-tag {
    display:inline-flex;align-items:center;gap:4px;padding:3px 8px;
    border-radius:20px;font-size:11.5px;font-weight:600;background:var(--accent-light);
    color:var(--accent);
}
.qc-tag button {
    background:none;border:none;cursor:pointer;color:var(--accent);
    padding:0;font-size:12px;line-height:1;
}
</style>

<script>
const canManage = <?= $canManage ? 'true' : 'false' ?>;
const CSRF_NAME = '<?= csrf_token() ?>';
const CSRF_HASH = '<?= csrf_hash() ?>';

// ── Modal helpers ────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id)?.classList.add('d-none');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.cs-modal-overlay:not(.d-none)').forEach(m => closeModal(m.id));
});
document.querySelectorAll('.cs-modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); })
);

// ── Calendarios ─────────────────────────────────────────────────
const CAL = {
    view: 'month',
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    weekStart: null,   // Date string 'YYYY-MM-DD' (lunes de la semana)
    events: [],

    async load() {
        const url = this.view === 'month'
            ? `/clases/api/calendario?year=${this.year}&month=${this.month}`
            : `/clases/api/calendario?year=${this.weekStartYear()}&month=${this.weekStartMonth()}`;
        try {
            const res = await fetch(url);
            this.events = await res.json();
        } catch(e) { this.events = []; }
        this.render();
    },

    render() {
        this.view === 'month' ? this.renderMonth() : this.renderWeek();
    },

    switchView(v, btn) {
        this.view = v;
        document.querySelectorAll('.calendar-view-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (v === 'week' && !this.weekStart) {
            this.weekStart = this.getMonday(new Date());
        }
        this.load();
    },

    prev() {
        if (this.view === 'month') {
            if (--this.month < 1) { this.month = 12; this.year--; }
        } else {
            const d = new Date(this.weekStart + 'T00:00:00');
            d.setDate(d.getDate() - 7);
            this.weekStart = this.fmt(d);
        }
        this.load();
    },

    next() {
        if (this.view === 'month') {
            if (++this.month > 12) { this.month = 1; this.year++; }
        } else {
            const d = new Date(this.weekStart + 'T00:00:00');
            d.setDate(d.getDate() + 7);
            this.weekStart = this.fmt(d);
        }
        this.load();
    },

    today() {
        const n = new Date();
        this.year = n.getFullYear();
        this.month = n.getMonth() + 1;
        this.weekStart = this.getMonday(n);
        this.load();
    },

    // ── Vista mensual ───────────────────────────────────────────
    renderMonth() {
        const mn  = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        document.getElementById('cal-label').textContent = mn[this.month - 1] + ' ' + this.year;

        const first = new Date(this.year, this.month - 1, 1).getDay(); // 0=dom
        const offset = (first + 6) % 7; // lunes=0
        const daysInMonth = new Date(this.year, this.month, 0).getDate();
        const todayStr = this.fmt(new Date());

        let html = '<div class="cal-month-headers">';
        ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'].forEach(d =>
            html += `<div class="cal-day-header">${d}</div>`
        );
        html += '</div><div class="cal-month-grid">';

        // Celdas vacías anteriores
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

        // Rellenar última fila
        const total = offset + daysInMonth;
        const fill  = (7 - (total % 7)) % 7;
        for (let i = 0; i < fill; i++) html += '<div class="cal-cell other"></div>';
        html += '</div>';

        document.getElementById('cal-grid').innerHTML = html;
    },

    // ── Vista semanal ───────────────────────────────────────────
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

        // Horas visibles: 7-22
        const HOUR_START = 7, HOUR_END = 22;
        const SLOT_H = 56; // px por hora

        let html = '<div class="cal-week-wrap"><div class="cal-week-grid">';

        // Cabeceras
        html += '<div class="cal-week-head time-col"></div>';
        days.forEach((d, i) => {
            const isT = dates[i] === todayStr;
            html += `<div class="cal-week-head${isT ? ' cal-wday-today' : ''}">
                        <div class="cal-wday-name">${dnames[i]}</div>
                        <div class="cal-wday-num">${d.getDate()}</div>
                     </div>`;
        });

        // Filas de horas
        for (let h = HOUR_START; h < HOUR_END; h++) {
            html += `<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            dates.forEach((dateStr, di) => {
                const slotDate = dateStr;
                const dayEvts = this.events.filter(e => {
                    if (e.date !== dateStr) return false;
                    const eh = parseInt(e.start.split(':')[0]);
                    return eh === h;
                });

                html += `<div class="cal-hour-slot${canManage ? ' cal-can-create' : ''}"
                              data-date="${slotDate}" data-hour="${h}"
                              onclick="handleSlotClick(event, '${slotDate}', ${h})">`;

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

    // ── Helpers ──────────────────────────────────────────────────
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

// Manejar clicks en celda mensual
function handleCellClick(e, date) {
    if (e.target.closest('a')) return;
    if (canManage) openQuickCreate(date);
}
// Manejar clicks en slot semanal
function handleSlotClick(e, date, hour) {
    if (e.target.closest('a')) return;
    if (canManage) {
        const h = String(hour).padStart(2,'0');
        openQuickCreate(date, `${h}:00`);
    }
}

// Inicializar
CAL.load();

// ────────────────────────────────────────────────────────────────
// Quick Create Modal
// ────────────────────────────────────────────────────────────────
const qcCoaches  = new Map();
const qcPlayers  = new Map();
let   optionsFetched = false;

async function openQuickCreate(date = null, time = null) {
    if (!canManage) return;

    if (!optionsFetched) {
        try {
            const res = await fetch('/clases/api/opciones');
            const d   = await res.json();
            const cSel = document.getElementById('qc-coach-sel');
            const pSel = document.getElementById('qc-player-sel');
            d.coaches.forEach(c => {
                const o = document.createElement('option');
                o.value = c.id; o.textContent = c.name;
                o.dataset.name = c.name;
                cSel.appendChild(o);
            });
            d.players.forEach(p => {
                const o = document.createElement('option');
                o.value = p.id; o.textContent = p.name;
                o.dataset.name = p.name;
                pSel.appendChild(o);
            });
            optionsFetched = true;
        } catch(e) {}
    }

    if (date) document.getElementById('qc-date').value = date;
    if (time) document.getElementById('qc-start').value = time;
    document.getElementById('qc-error').style.display = 'none';
    openModal('modalQuickCreate');
}

function qcAddCoach(sel) {
    const id = parseInt(sel.value);
    if (!id || qcCoaches.has(id)) { sel.value = ''; return; }
    const name = sel.options[sel.selectedIndex].dataset.name;
    qcCoaches.set(id, name);
    sel.value = '';
    renderQcTags('qc-coach-list', qcCoaches, () => qcCoaches.forEach((_, k) => {}));
    renderQcTags('qc-coach-list', qcCoaches, id => qcCoaches.delete(id));
}

function qcAddPlayer(sel) {
    const id = parseInt(sel.value);
    if (!id || qcPlayers.has(id)) { sel.value = ''; return; }
    const name = sel.options[sel.selectedIndex].dataset.name;
    qcPlayers.set(id, name);
    sel.value = '';
    renderQcTags('qc-player-list', qcPlayers, id => qcPlayers.delete(id));
}

function renderQcTags(containerId, map, onRemove) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    map.forEach((name, id) => {
        const tag = document.createElement('span');
        tag.className = 'qc-tag';
        tag.innerHTML = `${name} <button onclick="qcRemoveTag(${id}, '${containerId}')">×</button>`;
        container.appendChild(tag);
    });
}

function qcRemoveTag(id, containerId) {
    if (containerId === 'qc-coach-list') qcCoaches.delete(id);
    else qcPlayers.delete(id);
    renderQcTags(containerId,
        containerId === 'qc-coach-list' ? qcCoaches : qcPlayers,
        i => containerId === 'qc-coach-list' ? qcCoaches.delete(i) : qcPlayers.delete(i)
    );
}

async function submitQuickCreate() {
    const title = document.getElementById('qc-title').value.trim();
    const date  = document.getElementById('qc-date').value;
    const start = document.getElementById('qc-start').value;
    const errEl = document.getElementById('qc-error');

    if (!title || !date || !start) {
        errEl.textContent = 'Título, fecha y hora de inicio son obligatorios.';
        errEl.style.display = 'block';
        return;
    }
    errEl.style.display = 'none';

    const btn = document.getElementById('qc-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creando…';

    const formData = new FormData();
    formData.append(CSRF_NAME, CSRF_HASH);
    formData.append('title', title);
    formData.append('session_date', date);
    formData.append('start_time', start);
    formData.append('end_time', document.getElementById('qc-end').value);
    formData.append('location_custom', document.getElementById('qc-location').value);
    qcCoaches.forEach((_, id) => formData.append('coach_ids[]', id));
    qcPlayers.forEach((_, id) => formData.append('player_ids[]', id));

    try {
        const res  = await fetch('/clases/rapida', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            closeModal('modalQuickCreate');
            // Limpiar form
            document.getElementById('qc-title').value = '';
            document.getElementById('qc-start').value = '';
            document.getElementById('qc-end').value   = '';
            document.getElementById('qc-location').value = '';
            qcCoaches.clear(); qcPlayers.clear();
            document.getElementById('qc-coach-list').innerHTML = '';
            document.getElementById('qc-player-list').innerHTML = '';
            // Recargar calendario
            await CAL.load();
        } else {
            errEl.textContent = data.error || 'Error al crear la sesión.';
            errEl.style.display = 'block';
        }
    } catch(e) {
        errEl.textContent = 'Error de conexión. Inténtalo de nuevo.';
        errEl.style.display = 'block';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Crear sesión';
}
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>

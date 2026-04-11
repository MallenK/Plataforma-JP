<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<?php $role = session('role'); $isAdmin = in_array($role, ['superadmin', 'admin']); ?>

<!-- ── Métricas ──────────────────────────────────────────── -->
<?php if ($isAdmin): ?>
<div
    class="row g-3 mb-4"
    id="stats-container"
    data-url="<?= route_to('dashboard_stats') ?>"
    data-csrf-name="<?= csrf_token() ?>"
    data-csrf-hash="<?= csrf_hash() ?>"
>
    <!-- Alumnos activos -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Alumnos activos</span>
                <div class="metric-icon blue"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="metric-value" id="alumnos-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend up" id="alumnos-trend"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">vs mes anterior</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="alumnos-bar" style="width:0%;background:var(--accent)"></div></div>
        </div>
    </div>

    <!-- Entrenadores -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Entrenadores</span>
                <div class="metric-icon green"><i class="bi bi-person-workspace"></i></div>
            </div>
            <div class="metric-value" id="entrenadores-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend neutral" id="entrenadores-trend">—</span>
                <span class="metric-footer-label">activos</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="entrenadores-bar" style="width:0%;background:var(--success)"></div></div>
        </div>
    </div>

    <!-- Ingresos del mes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label color-red">Ingresos mes</span>
                <div class="metric-icon orange"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="metric-value" id="ingresos-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend up" id="ingresos-trend"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">meta mensual</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="ingresos-bar" style="width:0%;background:#f97316"></div></div>
        </div>
    </div>

    <!-- Alertas -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Alertas</span>
                <div class="metric-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
            <div class="metric-value" id="alertas-count">—</div>
            <div class="metric-footer">
                <span class="badge-trend down" id="alertas-trend"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">acción requerida</span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar" id="alertas-bar" style="width:0%;background:var(--danger)"></div></div>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Vista reducida para coach / staff / alumno -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-body text-center py-4">
                <i class="bi bi-person-check-fill" style="font-size:2.5rem;color:var(--accent)"></i>
                <h5 class="mt-3 mb-1" style="color:var(--text-h);font-weight:700">
                    Bienvenido, <?= esc(session('name')) ?>
                </h5>
                <p style="color:var(--text-muted);margin:0">
                    <?php if ($role === 'alumno'): ?>
                        Accede a tu <a href="<?= base_url('alumno') ?>">ficha</a> o consulta la <a href="<?= base_url('documentacion') ?>">documentación</a>.
                    <?php elseif ($role === 'coach'): ?>
                        Gestiona tus alumnos desde <a href="<?= base_url('alumnos') ?>">Alumnos</a> o revisa las <a href="<?= base_url('clases') ?>">Clases</a>.
                    <?php else: ?>
                        Consulta los <a href="<?= base_url('torneos') ?>">torneos</a> o la <a href="<?= base_url('documentacion') ?>">documentación</a>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ── Cuerpo: Calendario + Paneles laterales ─────────────── -->
<div class="row g-3">

    <!-- Calendario -->
    <?php $dbCanManage = in_array($role, ['superadmin', 'admin', 'staff', 'coach']); ?>
    <div class="col-12 col-xl-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>Calendario</span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="calendar-view-tabs">
                        <button class="calendar-view-tab active" onclick="DBCAL.switchView('month', this)">Mes</button>
                        <button class="calendar-view-tab" onclick="DBCAL.switchView('week', this)">Semana</button>
                    </div>
                    <?php if ($dbCanManage): ?>
                    <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="dbOpenQuickCreate()">
                        <i class="bi bi-plus-lg me-1"></i>Nueva clase
                    </button>
                    <?php endif; ?>
                    <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-arrow-right me-1"></i>Ver todo
                    </a>
                </div>
            </div>
            <div class="card-jp-body">
                <div class="calendar-toolbar">
                    <div class="calendar-nav">
                        <button onclick="DBCAL.prev()"><i class="bi bi-chevron-left"></i></button>
                        <button onclick="DBCAL.today()" style="width:auto;padding:0 12px;font-size:12px;font-weight:600">Hoy</button>
                        <span class="calendar-nav-label" id="db-cal-label">Cargando…</span>
                        <button onclick="DBCAL.next()"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <div id="db-cal-grid"></div>
            </div>
        </div>
    </div>

    <!-- Panel lateral derecho -->
    <div class="col-12 col-xl-4 d-flex flex-column gap-3">

        <!-- Próximos torneos -->
        <?php if (in_array($role, ['superadmin', 'admin', 'coach', 'staff'])): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-trophy-fill me-2" style="color:var(--warning)"></i>Próximos Torneos</span>
                <a href="<?= base_url('torneos') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Ver todos</a>
            </div>
            <div class="card-jp-body py-0">
                <div id="proximos-torneos">
                    <div class="list-item-jp">
                        <div class="list-item-icon blue"><i class="bi bi-calendar-event"></i></div>
                        <div class="list-item-info">
                            <div class="list-item-title">—</div>
                            <div class="list-item-sub">Pendiente de carga</div>
                        </div>
                        <i class="bi bi-chevron-right list-item-action"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Próximas clases -->
        <?php if ($dbCanManage): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-collection-play-fill me-2" style="color:var(--accent)"></i>Próximas clases
                </span>
                <a href="<?= base_url('clases') ?>" style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600">Ver todas</a>
            </div>
            <div id="db-proximas-clases" class="card-jp-body py-2">
                <div style="font-size:13px;color:var(--text-muted);text-align:center;padding:10px">Cargando…</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Accesos rápidos (coach / alumno) -->
        <?php if ($role === 'coach'): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">Accesos rápidos</span>
            </div>
            <div class="card-jp-body d-flex flex-column gap-2">
                <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary w-100">
                    <i class="bi bi-people-fill"></i> Mis alumnos
                </a>
                <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-secondary w-100">
                    <i class="bi bi-collection-play-fill"></i> Mis clases
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'alumno'): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">Mi espacio</span>
            </div>
            <div class="card-jp-body d-flex flex-column gap-2">
                <a href="<?= base_url('alumno') ?>" class="btn-jp btn-jp-primary w-100 justify-content-center">
                    <i class="bi bi-person-badge-fill"></i> Ver mi ficha
                </a>
                <a href="<?= base_url('documentacion') ?>" class="btn-jp btn-jp-secondary w-100 justify-content-center">
                    <i class="bi bi-folder2-open"></i> Documentación
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── Modal quick-create (Dashboard) ─────────────────────── -->
<?php if ($dbCanManage): ?>
<div id="modalDbQuickCreate" class="cs-modal-overlay d-none">
    <div class="cs-modal" style="max-width:500px">
        <div class="cs-modal-header">
            <span><i class="bi bi-plus-circle-fill me-2" style="color:var(--accent)"></i>Nueva sesión</span>
            <button onclick="dbCloseModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Título <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="db-qc-title" class="form-control-jp" placeholder="Ej: Entrenamiento individual">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="db-qc-date" class="form-control-jp" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Inicio <span style="color:var(--danger)">*</span></label>
                    <input type="time" id="db-qc-start" class="form-control-jp">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Fin</label>
                    <input type="time" id="db-qc-end" class="form-control-jp">
                </div>
                <div class="col-12">
                    <label class="form-label">Lugar (opcional)</label>
                    <input type="text" id="db-qc-location" class="form-control-jp" placeholder="Campo, instalación…">
                </div>
            </div>
            <div id="db-qc-error" style="color:var(--danger);font-size:13px;margin-top:10px;display:none"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid var(--border)">
            <button class="btn-jp btn-jp-secondary" onclick="dbCloseModal()">Cancelar</button>
            <button class="btn-jp btn-jp-primary" id="db-qc-btn" onclick="dbSubmitQuickCreate()">
                <i class="bi bi-check-lg me-1"></i>Crear
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
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

.cal-month-headers { display:grid;grid-template-columns:repeat(7,1fr);background:var(--bg-app);border:1px solid var(--border);border-bottom:none;border-radius:var(--radius-sm) var(--radius-sm) 0 0; }
.cal-day-header { padding:7px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted); }
.cal-month-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:0 0 var(--radius-sm) var(--radius-sm);overflow:hidden; }
.cal-cell { background:var(--bg-card);min-height:80px;padding:5px;transition:background .1s; }
.cal-cell:hover { background:#f8fafc; }
.cal-cell.other { background:#f8fafc;opacity:.5; }
.cal-cell.today { background:var(--accent-light); }
.cal-day-num { font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:3px; }
.cal-cell.today .cal-day-num { background:var(--accent);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center; }
.cal-chip { display:block;font-size:10.5px;font-weight:600;padding:2px 5px;border-radius:4px;margin-bottom:2px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.cal-chip:hover { opacity:.85; }
.cal-more { font-size:10px;color:var(--text-muted);padding:1px 4px; }
.cal-week-wrap { overflow-x:auto; }
.cal-week-grid { display:grid;grid-template-columns:48px repeat(7,1fr);min-width:560px; }
.cal-week-head { padding:7px;text-align:center;border-bottom:2px solid var(--border);background:var(--bg-card); }
.cal-week-head.time-col { border-right:1px solid var(--border); }
.cal-wday-name { font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px; }
.cal-wday-num  { font-size:18px;font-weight:800;color:var(--text-h); }
.cal-wday-today .cal-wday-num { color:var(--accent); }
.cal-time-label { font-size:10px;color:var(--text-muted);padding:2px 4px 0;border-right:1px solid var(--border);border-bottom:1px solid #f1f5f9;height:52px;text-align:right; }
.cal-hour-slot { position:relative;border-bottom:1px solid #f1f5f9;height:52px; }
.cal-event-block { position:absolute;left:2px;right:2px;border-radius:4px;padding:2px 5px;font-size:10.5px;font-weight:600;text-decoration:none;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;z-index:1; }
</style>
<script>
const DB_CSRF_NAME = '<?= csrf_token() ?>';
const DB_CSRF_HASH = '<?= csrf_hash() ?>';
const dbCanManage  = <?= $dbCanManage ? 'true' : 'false' ?>;

// ── Mini-calendario dashboard ─────────────────────────────────
const DBCAL = {
    view: 'month',
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    weekStart: null,
    events: [],

    async load() {
        const url = `/clases/api/calendario?year=${this.year}&month=${this.month}`;
        try {
            const res = await fetch(url);
            this.events = await res.json();
        } catch(e) { this.events = []; }
        this.render();
        this.loadProximas();
    },

    render() { this.view === 'month' ? this.renderMonth() : this.renderWeek(); },

    switchView(v, btn) {
        this.view = v;
        document.querySelectorAll('.calendar-view-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (v === 'week' && !this.weekStart) this.weekStart = this.getMonday(new Date());
        this.render();
    },

    prev() {
        if (this.view === 'month') { if (--this.month < 1) { this.month = 12; this.year--; } }
        else { const d = new Date(this.weekStart+'T00:00:00'); d.setDate(d.getDate()-7); this.weekStart = this.fmt(d); }
        this.load();
    },
    next() {
        if (this.view === 'month') { if (++this.month > 12) { this.month = 1; this.year++; } }
        else { const d = new Date(this.weekStart+'T00:00:00'); d.setDate(d.getDate()+7); this.weekStart = this.fmt(d); }
        this.load();
    },
    today() {
        const n = new Date(); this.year = n.getFullYear(); this.month = n.getMonth()+1;
        this.weekStart = this.getMonday(n); this.load();
    },

    renderMonth() {
        const mn = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        document.getElementById('db-cal-label').textContent = mn[this.month-1] + ' ' + this.year;
        const first = new Date(this.year, this.month-1, 1).getDay();
        const offset = (first+6)%7;
        const dim = new Date(this.year, this.month, 0).getDate();
        const todayStr = this.fmt(new Date());
        let html = '<div class="cal-month-headers">';
        ['L','M','X','J','V','S','D'].forEach(d => html += `<div class="cal-day-header">${d}</div>`);
        html += '</div><div class="cal-month-grid">';
        for (let i=0; i<offset; i++) html += '<div class="cal-cell other"></div>';
        for (let day=1; day<=dim; day++) {
            const ds = `${this.year}-${String(this.month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const isT = ds === todayStr;
            const evts = this.events.filter(e=>e.date===ds);
            html += `<div class="cal-cell${isT?' today':''}${dbCanManage?' cal-can-create':''}" onclick="dbHandleClick(event,'${ds}')">`;
            html += `<div class="cal-day-num">${day}</div>`;
            evts.slice(0,2).forEach(ev => {
                html += `<a href="/clases/${ev.id}" class="cal-chip" style="background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44" onclick="event.stopPropagation()">${ev.start} ${ev.title}</a>`;
            });
            if (evts.length>2) html += `<div class="cal-more">+${evts.length-2}</div>`;
            html += '</div>';
        }
        const fill = (7 - ((offset+dim)%7))%7;
        for (let i=0; i<fill; i++) html += '<div class="cal-cell other"></div>';
        html += '</div>';
        document.getElementById('db-cal-grid').innerHTML = html;
    },

    renderWeek() {
        if (!this.weekStart) this.weekStart = this.getMonday(new Date());
        const ws = new Date(this.weekStart+'T00:00:00');
        const we = new Date(ws); we.setDate(we.getDate()+6);
        const todayStr = this.fmt(new Date());
        const mn = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        document.getElementById('db-cal-label').textContent =
            `${ws.getDate()} ${mn[ws.getMonth()]} – ${we.getDate()} ${mn[we.getMonth()]} ${we.getFullYear()}`;
        const days=[],dates=[];
        for(let i=0;i<7;i++){const d=new Date(ws);d.setDate(d.getDate()+i);days.push(d);dates.push(this.fmt(d));}
        const dn=['L','M','X','J','V','S','D'];
        const HS=7, HE=20, SH=52;
        let html='<div class="cal-week-wrap"><div class="cal-week-grid">';
        html+='<div class="cal-week-head time-col"></div>';
        days.forEach((d,i)=>{
            const isT=dates[i]===todayStr;
            html+=`<div class="cal-week-head${isT?' cal-wday-today':''}"><div class="cal-wday-name">${dn[i]}</div><div class="cal-wday-num">${d.getDate()}</div></div>`;
        });
        for(let h=HS;h<HE;h++){
            html+=`<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            dates.forEach(ds=>{
                const evts=this.events.filter(e=>{if(e.date!==ds)return false;return parseInt(e.start.split(':')[0])===h;});
                html+=`<div class="cal-hour-slot${dbCanManage?' cal-can-create':''}" onclick="dbHandleSlot(event,'${ds}',${h})">`;
                evts.forEach(ev=>{
                    const [sh2,sm]=ev.start.split(':').map(Number);
                    const [eh2,em]=(ev.end||ev.start).split(':').map(Number);
                    const top=(sm/60)*SH;
                    const dur=Math.max(((eh2*60+em)-(sh2*60+sm))/60*SH,20);
                    html+=`<a href="/clases/${ev.id}" class="cal-event-block" style="top:${top}px;height:${dur}px;background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44" onclick="event.stopPropagation()">${ev.start} ${ev.title}</a>`;
                });
                html+='</div>';
            });
        }
        html+='</div></div>';
        document.getElementById('db-cal-grid').innerHTML = html;
    },

    async loadProximas() {
        const el = document.getElementById('db-proximas-clases');
        if (!el) return;
        try {
            const res  = await fetch(`/clases/api/calendario?year=${this.year}&month=${this.month}`);
            const data = await res.json();
            const today = this.fmt(new Date());
            const upcoming = data.filter(e => e.date >= today).slice(0, 4);
            if (!upcoming.length) {
                el.innerHTML = '<div style="font-size:13px;color:var(--text-muted);text-align:center;padding:10px">Sin clases próximas</div>';
                return;
            }
            const mn=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            el.innerHTML = upcoming.map(e => {
                const d = new Date(e.date+'T00:00:00');
                return `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
                    <div style="min-width:38px;text-align:center;background:${e.color}22;color:${e.color};border-radius:6px;padding:4px 0;font-size:12px;font-weight:700">${d.getDate()}<br><span style="font-size:10px">${mn[d.getMonth()]}</span></div>
                    <div style="flex:1;min-width:0">
                        <a href="/clases/${e.id}" style="font-size:13px;font-weight:600;color:var(--text-h);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${e.title}</a>
                        <div style="font-size:11px;color:var(--text-muted)">${e.start}–${e.end}</div>
                    </div>
                </div>`;
            }).join('');
        } catch(e) {}
    },

    getMonday(d) { const day=d.getDay(),diff=d.getDate()-day+(day===0?-6:1);return this.fmt(new Date(d.setDate(diff))); },
    fmt(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; },
};

function dbHandleClick(e, date) { if(e.target.closest('a'))return; if(dbCanManage) dbOpenQuickCreate(date); }
function dbHandleSlot(e, date, hour) { if(e.target.closest('a'))return; if(dbCanManage) dbOpenQuickCreate(date, String(hour).padStart(2,'0')+':00'); }

// ── Quick create (dashboard modal) ───────────────────────────
function dbOpenQuickCreate(date=null, time=null) {
    if(date) document.getElementById('db-qc-date').value = date;
    if(time) document.getElementById('db-qc-start').value = time;
    document.getElementById('db-qc-error').style.display = 'none';
    document.getElementById('modalDbQuickCreate').classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function dbCloseModal() {
    document.getElementById('modalDbQuickCreate').classList.add('d-none');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') dbCloseModal(); });
document.getElementById('modalDbQuickCreate')?.addEventListener('click', e => { if(e.target.id==='modalDbQuickCreate') dbCloseModal(); });

async function dbSubmitQuickCreate() {
    const title = document.getElementById('db-qc-title').value.trim();
    const date  = document.getElementById('db-qc-date').value;
    const start = document.getElementById('db-qc-start').value;
    const errEl = document.getElementById('db-qc-error');
    if (!title||!date||!start) { errEl.textContent='Título, fecha y hora son obligatorios.'; errEl.style.display='block'; return; }
    errEl.style.display='none';
    const btn = document.getElementById('db-qc-btn');
    btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split me-1"></i>Creando…';
    const fd = new FormData();
    fd.append(DB_CSRF_NAME, DB_CSRF_HASH);
    fd.append('title', title);
    fd.append('session_date', date);
    fd.append('start_time', start);
    fd.append('end_time', document.getElementById('db-qc-end').value);
    fd.append('location_custom', document.getElementById('db-qc-location').value);
    try {
        const res  = await fetch('/clases/rapida', {method:'POST',body:fd});
        const data = await res.json();
        if (data.success) {
            dbCloseModal();
            document.getElementById('db-qc-title').value='';
            document.getElementById('db-qc-start').value='';
            document.getElementById('db-qc-end').value='';
            document.getElementById('db-qc-location').value='';
            await DBCAL.load();
        } else {
            errEl.textContent = data.error||'Error al crear la sesión.';
            errEl.style.display='block';
        }
    } catch(e) { errEl.textContent='Error de conexión.'; errEl.style.display='block'; }
    btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg me-1"></i>Crear';
}

// Inicializar
DBCAL.load();
</script>
<?php if ($isAdmin): ?>
<?= view('dashboard/scripts') ?>
<?php endif; ?>
<?= $this->endSection() ?>

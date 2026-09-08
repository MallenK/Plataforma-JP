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

        <!-- Buscador: nombre de clase / entrenador / jugador -->
        <div class="cal-search" id="cal-search">
            <i class="bi bi-search cal-search-icon"></i>
            <input type="text" id="cal-search-input" autocomplete="off" spellcheck="false"
                   placeholder="Buscar una clase por nombre, entrenador o jugador…">
            <button type="button" id="cal-search-clear" aria-label="Limpiar búsqueda" hidden>
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="cal-search-panel" id="cal-search-panel" hidden></div>
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
    display:grid;grid-template-columns:repeat(7,minmax(0,1fr));
    background:var(--bg-app);border:1px solid var(--border);
    border-bottom:none;border-radius:var(--radius-sm) var(--radius-sm) 0 0;
}
.cal-day-header {
    padding:8px;text-align:center;font-size:11px;font-weight:700;
    text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);
}
.cal-month-grid {
    display:grid;grid-template-columns:repeat(7,minmax(0,1fr));
    gap:1px;background:var(--border);
    border:1px solid var(--border);border-radius:0 0 var(--radius-sm) var(--radius-sm);overflow:hidden;
}
.cal-cell {
    background:var(--bg-card);min-height:90px;min-width:0;padding:5px;cursor:default;
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
.cal-more {
    display:block;width:100%;text-align:left;
    background:none;border:none;font-family:inherit;
    font-size:10px;color:var(--text-muted);padding:1px 4px;cursor:pointer;
}
.cal-more:hover { color:var(--accent);text-decoration:underline; }

/* ── Calendario Semanal ────────────────────────────────────────── */
.cal-week-wrap { overflow-x:auto; }
.cal-week-grid {
    display:grid;grid-template-columns:48px repeat(7,1fr);
    min-width:640px;position:relative;
}
.cal-week-head {
    padding:8px;text-align:center;border-bottom:2px solid var(--border);
    position:sticky;top:0;background:var(--bg-card);z-index:6;
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
/* El primer slot de cada columna-día aloja la capa de eventos completa,
   que se desborda hacia abajo por encima del resto de slots. */
.cal-hour-slot.cal-anchor { overflow:visible;z-index:4; }
.cal-event-block {
    position:absolute;left:2px;right:2px;border-radius:5px;padding:3px 6px;
    font-size:11px;font-weight:600;text-decoration:none;overflow:hidden;
    white-space:nowrap;text-overflow:ellipsis;z-index:2;cursor:pointer;box-sizing:border-box;
}
.cal-event-block:hover { filter:brightness(.92); }
.cal-can-create { cursor:pointer; }
.cal-can-create:hover { background:var(--accent-light) !important; }

/* ── Calendario Día ────────────────────────────────────────────── */
.cal-day-grid {
    display:grid;grid-template-columns:48px 1fr;
    min-width:280px;position:relative;
}

/* ── Buscador de clases ────────────────────────────────────────── */
.cal-search { position:relative;margin:0 0 14px; }
.cal-search-icon {
    position:absolute;left:12px;top:50%;transform:translateY(-50%);
    color:var(--text-muted);font-size:14px;pointer-events:none;
}
#cal-search-input {
    width:100%;box-sizing:border-box;
    padding:9px 36px 9px 34px;
    border:1px solid var(--border);border-radius:9px;
    font-size:13.5px;background:var(--bg-card);color:var(--text-h);
    transition:border-color .15s,box-shadow .15s;
}
#cal-search-input:focus {
    outline:none;border-color:var(--accent);
    box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 18%,transparent);
}
#cal-search-clear {
    position:absolute;right:8px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:var(--text-muted);cursor:pointer;
    padding:4px;line-height:0;border-radius:6px;font-size:12px;
}
#cal-search-clear:hover { background:var(--bg-app);color:var(--text-h); }
.cal-search-panel {
    position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:20;
    background:var(--bg-card);border:1px solid var(--border);
    border-radius:10px;box-shadow:0 12px 28px rgba(15,23,42,.12);
    max-height:340px;overflow-y:auto;padding:4px;
}
.cal-search-row {
    display:flex;align-items:center;gap:10px;
    padding:8px 10px;border-radius:7px;text-decoration:none;
    color:var(--text-h);font-size:13px;
}
.cal-search-row:hover,.cal-search-row:focus { background:var(--bg-app); }
.cal-search-row .csr-dot { flex:none;width:9px;height:9px;border-radius:50%; }
.cal-search-row .csr-main { flex:1;min-width:0; }
.cal-search-row .csr-title {
    font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.cal-search-row .csr-sub {
    font-size:11.5px;color:var(--text-muted);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.cal-search-empty,.cal-search-hint {
    padding:12px 10px;font-size:12.5px;color:var(--text-muted);text-align:center;
}
</style>

<script>
/* ── Clases solapadas (misma hora): columnas + pop-up selector ──────
   Va inline en la vista (no como assets/js/*.js) para no depender de
   que el estático se sirva bien en el servidor. Mismo código en
   clases/index.php y dashboard/index.php. */
window.CalOverlap = (function () {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function toMin(t) {
        var p = String(t || '0').split(':');
        return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
    }

    var eventsProvider = function () { return []; };
    function useEvents(fn) { if (typeof fn === 'function') eventsProvider = fn; }

    // ── Reparto en columnas por solapamiento REAL ────────────────────
    // Antes el reparto se hacía por franja horaria (cada hora su <div>),
    // así que dos clases que se solapaban pero empezaban en horas
    // distintas (15:30 y 16:00) se dibujaban una encima de otra.
    // Ahora se agrupan por "cluster" de solapamiento sobre TODO el día.

    function endMin(ev) {
        var s = toMin(ev.start);
        var e = toMin(ev.end || ev.start);
        return e > s ? e : s + 60; // sin hora de fin → 1h por defecto
    }

    // Devuelve [{ cols, placed:[{ev,col}], group:[ev...] }] — un elemento por cluster.
    function clusterPack(evts) {
        var sorted = evts.slice().sort(function (a, b) {
            return toMin(a.start) - toMin(b.start) || endMin(a) - endMin(b);
        });

        var clusters = [], cur = [], curEnd = -1;
        sorted.forEach(function (ev) {
            if (cur.length && toMin(ev.start) >= curEnd) {
                clusters.push(cur); cur = []; curEnd = -1;
            }
            cur.push(ev);
            curEnd = Math.max(curEnd, endMin(ev));
        });
        if (cur.length) clusters.push(cur);

        return clusters.map(function (group) {
            var laneEnd = [];
            var placed = group.map(function (ev) {
                var s = toMin(ev.start), e = endMin(ev);
                var col = -1;
                for (var i = 0; i < laneEnd.length; i++) {
                    if (laneEnd[i] <= s) { col = i; break; }
                }
                if (col === -1) { col = laneEnd.length; laneEnd.push(e); }
                else { laneEnd[col] = e; }
                return { ev: ev, col: col };
            });
            return { cols: laneEnd.length || 1, placed: placed, group: group };
        });
    }

    // Compat: reparto plano (sin clusters) usado por algún test/consumidor antiguo.
    function packColumns(evts) {
        var clusters = clusterPack(evts);
        var placements = [];
        var cols = 1;
        clusters.forEach(function (cl) {
            cols = Math.max(cols, cl.cols);
            cl.placed.forEach(function (p) { placements.push(p); });
        });
        return { cols: cols, placements: placements };
    }

    // ── Capa de eventos de un día (Semana/Día) ───────────────────────
    // Se renderiza una sola vez por columna-día, posicionada en píxeles
    // desde `hourStart`. Un cluster con más de `maxCols` columnas colapsa
    // a un único botón "Ver todas" que cubre su franja.
    function dayLayerHtml(evts, dateStr, opts) {
        if (!evts || !evts.length) return '';
        opts = opts || {};
        var slotH = opts.slotH || 52;
        var hs    = opts.hourStart || 0;
        // En móvil las columnas lado a lado no se leen: cualquier cluster
        // con 2+ clases colapsa al botón "Ver todas".
        var isMobile = typeof window !== 'undefined' && window.matchMedia
            && window.matchMedia('(max-width: 768px)').matches;
        var maxCols = isMobile ? 1 : (opts.maxCols || 4);

        var out = '';
        clusterPack(evts).forEach(function (cl) {
            var gStart = Math.min.apply(null, cl.group.map(function (e) { return toMin(e.start); }));
            var gEnd   = Math.max.apply(null, cl.group.map(endMin));
            var top    = ((gStart - hs * 60) / 60) * slotH;

            if (cl.cols > maxCols) {
                var bh = Math.max(((gEnd - gStart) / 60) * slotH, 24);
                out += '<button type="button" class="cal-event-block cal-event-more" ' +
                    'style="top:' + top + 'px;height:' + bh + 'px;left:2px;right:2px;width:auto;padding:3px 6px;" ' +
                    'title="Ver las ' + cl.group.length + ' clases solapadas" ' +
                    "onclick=\"event.stopPropagation();CalOverlap.openPopupRange('" +
                        esc(dateStr) + "'," + gStart + ',' + gEnd + ')">' +
                    '<i class="bi bi-layers-half"></i> Ver todas &middot; ' + cl.group.length +
                    '</button>';
                return;
            }

            var w = 100 / cl.cols;
            cl.placed.forEach(function (p) {
                var ev = p.ev;
                var s = toMin(ev.start), e = endMin(ev);
                var t   = ((s - hs * 60) / 60) * slotH;
                var hgt = Math.max(((e - s) / 60) * slotH, 20);
                var leftPct = p.col * w;

                out += '<a href="/clases/' + encodeURIComponent(ev.id) + '" class="cal-event-block" ' +
                    'style="top:' + t + 'px;height:' + hgt + 'px;' +
                        'left:calc(' + leftPct + '% + 2px);width:calc(' + w + '% - 4px);right:auto;' +
                        'background:' + ev.color + '22;color:' + ev.color + ';border:1px solid ' + ev.color + '44" ' +
                    'title="' + esc(ev.title) + ' &middot; ' + esc(ev.start) + '–' + esc(ev.end || '') + '" ' +
                    'onclick="event.stopPropagation()">' +
                    esc(ev.start) + ' ' + esc(ev.title) +
                    '</a>';
            });
        });
        return out;
    }

    // Compat: firma antigua (una franja de una hora). Redirige a dayLayerHtml
    // tratando `hour` como hora de inicio del rango.
    function slotHtml(evts, dateStr, hour, opts) {
        opts = opts || {};
        return dayLayerHtml(evts, dateStr, {
            slotH: opts.slotH, hourStart: parseInt(hour, 10) || 0, maxCols: opts.maxCols
        });
    }

    // ── Pop-up selector ─────────────────────────────────────────────
    function closePopup() {
        var el = document.getElementById('cal-picker');
        if (el) el.remove();
        document.removeEventListener('keydown', onKey);
        document.body.style.overflow = '';
    }
    function onKey(e) { if (e.key === 'Escape') closePopup(); }

    function showPicker(titleTxt, evts) {
        var rows = evts.map(function (ev) {
            return '<a href="/clases/' + encodeURIComponent(ev.id) + '" class="cal-picker-row">' +
                '<span class="cal-picker-dot" style="background:' + ev.color + '"></span>' +
                '<span class="cal-picker-time">' + esc(ev.start) +
                    (ev.end ? '<small>–' + esc(ev.end) + '</small>' : '') + '</span>' +
                '<span class="cal-picker-title">' + esc(ev.title) + '</span>' +
                '<i class="bi bi-chevron-right cal-picker-arrow"></i>' +
                '</a>';
        }).join('') || '<div class="cal-picker-empty">No hay clases.</div>';

        closePopup();

        var overlay = document.createElement('div');
        overlay.id = 'cal-picker';
        overlay.className = 'cal-picker-overlay';
        overlay.innerHTML =
            '<div class="cal-picker-modal" role="dialog" aria-modal="true">' +
                '<div class="cal-picker-head">' +
                    '<span><i class="bi bi-calendar3"></i> ' + esc(titleTxt) + '</span>' +
                    '<button type="button" class="cal-picker-close" aria-label="Cerrar">' +
                        '<i class="bi bi-x-lg"></i></button>' +
                '</div>' +
                '<div class="cal-picker-hint">Elige qué clase quieres ver</div>' +
                '<div class="cal-picker-list">' + rows + '</div>' +
            '</div>';

        overlay.addEventListener('click', function (e) { if (e.target === overlay) closePopup(); });
        overlay.querySelector('.cal-picker-close').addEventListener('click', closePopup);
        document.addEventListener('keydown', onKey);

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
    }

    function dayLabel(dateStr, extra) {
        var d = new Date(dateStr + 'T00:00:00');
        var dn = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        var mn = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return dn[d.getDay()] + ' ' + d.getDate() + ' ' + mn[d.getMonth()] + (extra ? ' · ' + extra : '');
    }

    // Franja horaria (compat: openPopup por hora de inicio o día entero).
    function openPopup(dateStr, hour) {
        var all = eventsProvider() || [];
        var evts = all.filter(function (e) {
            if (e.date !== dateStr) return false;
            if (hour == null) return true;
            return (parseInt(String(e.start).split(':')[0], 10) || 0) === hour;
        }).sort(function (a, b) { return String(a.start).localeCompare(String(b.start)); });

        showPicker(dayLabel(dateStr, hour != null ? String(hour).padStart(2, '0') + ':00' : ''), evts);
    }

    // Rango [startMin, endMin) — usado por el botón "Ver todas" de un cluster.
    function openPopupRange(dateStr, startMin, endMin) {
        var all = eventsProvider() || [];
        var evts = all.filter(function (e) {
            if (e.date !== dateStr) return false;
            var s = toMin(e.start);
            return s >= startMin && s < endMin;
        }).sort(function (a, b) { return String(a.start).localeCompare(String(b.start)); });

        var lbl = String(Math.floor(startMin / 60)).padStart(2, '0') + ':' + String(startMin % 60).padStart(2, '0');
        showPicker(dayLabel(dateStr, lbl), evts);
    }

    return {
        esc: esc, toMin: toMin, useEvents: useEvents,
        packColumns: packColumns, clusterPack: clusterPack,
        dayLayerHtml: dayLayerHtml, slotHtml: slotHtml,
        openPopup: openPopup, openPopupRange: openPopupRange, closePopup: closePopup
    };
})();
</script>
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
        // Meses a cargar. La vista Semana puede solapar dos meses (p. ej.
        // 31 ago – 6 sep): hay que pedir los dos o las clases del mes que
        // no coincide con weekStart no aparecen.
        const months = [];
        const add = (y, m) => { const k = y + '-' + m; if (!months.some(x => x.k === k)) months.push({ k, y, m }); };

        if (this.view === 'week') {
            const ws = new Date((this.weekStart || this.getMonday(new Date())) + 'T00:00:00');
            const we = new Date(ws); we.setDate(we.getDate() + 6);
            add(ws.getFullYear(), ws.getMonth() + 1);
            add(we.getFullYear(), we.getMonth() + 1);
        } else if (this.view === 'day' && this.day) {
            const p = this.day.split('-');
            add(parseInt(p[0]), parseInt(p[1]));
        } else {
            add(this.year, this.month);
        }

        try {
            const lists = await Promise.all(months.map(x =>
                fetch(`/clases/api/calendario?year=${x.y}&month=${x.m}`).then(r => r.json())));
            const seen = new Set();
            this.events = lists.flat().filter(e => !seen.has(e.id) && seen.add(e.id));
        } catch (e) { this.events = []; }
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
                const t = CalOverlap.esc(ev.title);
                html += `<a href="/clases/${ev.id}" class="cal-chip"
                            style="background:${ev.color}22;color:${ev.color};border:1px solid ${ev.color}44"
                            title="${t} ${ev.start}–${ev.end}">
                            ${ev.start} ${t}
                         </a>`;
            });
            if (dayEvts.length > 3) {
                html += `<button type="button" class="cal-more"
                            onclick="event.stopPropagation();CalOverlap.openPopup('${dateStr}', null)">
                            +${dayEvts.length - 3} más
                         </button>`;
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

        const SLOT_H = 56;
        const [HOUR_START, HOUR_END] = this.hourRange(this.events.filter(e => dates.includes(e.date)));

        let html = '<div class="cal-week-wrap"><div class="cal-week-grid">';

        html += '<div class="cal-week-head time-col"></div>';
        days.forEach((d, i) => {
            const isT = dates[i] === todayStr;
            html += `<div class="cal-week-head${isT ? ' cal-wday-today' : ''}">
                        <div class="cal-wday-name">${dnames[i]}</div>
                        <div class="cal-wday-num">${d.getDate()}</div>
                     </div>`;
        });

        // La capa de eventos de cada columna-día se pinta una vez (primer
        // slot) y se posiciona en píxeles: las clases que se solapan aunque
        // empiecen en horas distintas salen en columnas, no una sobre otra.
        const colEvts = dates.map(ds => this.events.filter(e => e.date === ds));
        for (let h = HOUR_START; h < HOUR_END; h++) {
            html += `<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            dates.forEach((dateStr, di) => {
                const anchor = h === HOUR_START;
                html += `<div class="cal-hour-slot${anchor ? ' cal-anchor' : ''}${canManage ? ' cal-can-create' : ''}"
                              data-date="${dateStr}" data-hour="${h}"
                              onclick="handleSlotClick(event, '${dateStr}', ${h})">`;

                // Semana: hasta 2 columnas legibles por cluster; más → "Ver todas".
                if (anchor) html += CalOverlap.dayLayerHtml(colEvts[di], dateStr, { slotH: SLOT_H, hourStart: HOUR_START, maxCols: 2 });

                html += '</div>';
            });
        }

        html += '</div></div>';
        document.getElementById('cal-grid').innerHTML = html;
    },

    // Rango de horas [inicio, fin) a pintar en Semana/Día: 07–22 salvo que
    // haya clases fuera de esa franja.
    hourRange(evts) {
        let hs = 7, he = 22;
        (evts || []).forEach(e => {
            const sh = parseInt(String(e.start).split(':')[0]) || 0;
            const ep = String(e.end || '').split(':');
            const eh = (parseInt(ep[0]) || sh) + ((parseInt(ep[1]) || 0) > 0 ? 1 : 0);
            if (sh < hs) hs = Math.max(0, sh);
            if (Math.max(eh, sh + 1) > he) he = Math.min(24, Math.max(eh, sh + 1));
        });
        return [hs, he];
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

        const SLOT_H = 56;
        const dayEvts = this.events.filter(e => e.date === this.day);
        const [HOUR_START, HOUR_END] = this.hourRange(dayEvts);

        let html = '<div class="cal-week-wrap"><div class="cal-day-grid">';

        html += '<div class="cal-week-head time-col"></div>';
        html += `<div class="cal-week-head${isToday ? ' cal-wday-today' : ''}">
                    <div class="cal-wday-name">${dnames[d.getDay()].substring(0,3)}</div>
                    <div class="cal-wday-num">${d.getDate()}</div>
                 </div>`;

        for (let h = HOUR_START; h < HOUR_END; h++) {
            const anchor = h === HOUR_START;
            html += `<div class="cal-time-label">${String(h).padStart(2,'0')}:00</div>`;
            html += `<div class="cal-hour-slot${anchor ? ' cal-anchor' : ''}${canManage ? ' cal-can-create' : ''}"
                         data-date="${this.day}" data-hour="${h}"
                         onclick="handleSlotClick(event, '${this.day}', ${h})">`;
            // Día: hasta 6 columnas por cluster; más → botón "Ver todas".
            if (anchor) html += CalOverlap.dayLayerHtml(dayEvts, this.day, { slotH: SLOT_H, hourStart: HOUR_START, maxCols: 6 });
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

// ── Buscador de clases (nombre / entrenador / jugador) ────────────
const ClaseSearch = (function () {
    const input = document.getElementById('cal-search-input');
    const panel = document.getElementById('cal-search-panel');
    const clear = document.getElementById('cal-search-clear');
    if (!input) return {};

    const esc = CalOverlap.esc;
    const MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const COLOR = { scheduled:'#3b82f6', completed:'#10b981', cancelled:'#94a3b8' };
    let timer = null, lastQ = '', ctrl = null;

    function fmtDate(d) {
        const p = String(d).split('-');
        return p.length === 3 ? (parseInt(p[2],10) + ' ' + MESES[parseInt(p[1],10)-1] + ' ' + p[0]) : d;
    }

    function open()  { panel.hidden = false; }
    function close() { panel.hidden = true; }

    function render(rows) {
        if (!rows.length) {
            panel.innerHTML = '<div class="cal-search-empty">Sin resultados para “' + esc(lastQ) + '”.</div>';
            open(); return;
        }
        panel.innerHTML = rows.map(function (r) {
            const sub = [fmtDate(r.date) + (r.start ? ' · ' + esc(r.start) : '')];
            if (r.coaches) sub.push(esc(r.coaches));
            if (r.players) sub.push(r.players + (r.players === 1 ? ' jugador' : ' jugadores'));
            return '<a class="cal-search-row" href="/clases/' + encodeURIComponent(r.id) + '">' +
                '<span class="csr-dot" style="background:' + (COLOR[r.status] || '#3b82f6') + '"></span>' +
                '<span class="csr-main">' +
                    '<span class="csr-title">' + esc(r.title) + '</span>' +
                    '<span class="csr-sub">' + sub.join(' &nbsp;·&nbsp; ') + '</span>' +
                '</span>' +
                '<i class="bi bi-chevron-right" style="color:var(--text-muted);font-size:12px"></i>' +
            '</a>';
        }).join('');
        open();
    }

    async function run(q) {
        lastQ = q;
        if (q.trim().length < 2) { close(); return; }
        panel.innerHTML = '<div class="cal-search-hint">Buscando…</div>'; open();
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        try {
            const res = await fetch('/clases/api/buscar?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal
            });
            if (!res.ok) throw new Error(res.status);
            render(await res.json());
        } catch (e) {
            if (e.name === 'AbortError') return;
            panel.innerHTML = '<div class="cal-search-empty">No se pudo buscar. Inténtalo de nuevo.</div>'; open();
        }
    }

    input.addEventListener('input', function () {
        const q = input.value;
        clear.hidden = q.length === 0;
        clearTimeout(timer);
        timer = setTimeout(function () { run(q); }, 220);
    });
    input.addEventListener('focus', function () { if (input.value.trim().length >= 2) run(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(); input.blur(); }
        if (e.key === 'Enter') { const a = panel.querySelector('.cal-search-row'); if (a) a.click(); }
    });
    clear.addEventListener('click', function () {
        input.value = ''; clear.hidden = true; close(); input.focus();
    });
    document.addEventListener('click', function (e) {
        if (!document.getElementById('cal-search').contains(e.target)) close();
    });

    return { run: run };
})();

// Inicializar
CalOverlap.useEvents(() => CAL.events);
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

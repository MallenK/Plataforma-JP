/**
 * bono-coverage.js — TICKET-013: aviso de bono al crear clases.
 *
 * Solo AVISA, nunca bloquea: si las clases que se van a crear superan el saldo
 * de bono de algún alumno, enseña una alerta amarilla y la clase se puede
 * crear igualmente. No crea nada: pregunta a POST /clases/api/cobertura.
 *
 * Uso:
 *   const cov = BonoCoverage.attach({
 *       panel:    document.getElementById('bono-cov'),   // contenedor donde se pinta
 *       csrfName: '...', csrfHash: '...',
 *       collect:  () => FormData con player_ids[] y recurrence_* o session_date (o null si no aplica),
 *       onChange: (state) => {}                           // opcional, tras pintar
 *   });
 *   cov.refresh();   // llamar cuando cambien alumnos / días / fechas
 */
(function () {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Una línea de aviso por alumno al que le falta bono (o null si está cubierto).
    function alertLine(p) {
        var s = p.summary || {};
        var gap = s.uncovered || 0;
        if (gap > 0) {
            if (p.never_had_bono) {
                return '<strong>' + esc(p.name) + '</strong> no tiene bono.';
            }
            if ((s.requested || 0) <= 1) {
                return '<strong>' + esc(p.name) + '</strong> no tiene saldo de bono para esta clase.';
            }
            return '<strong>' + esc(p.name) + '</strong>: se superará su bono ('
                + (s.covered || 0) + ' de ' + s.requested + ' clases cubiertas, '
                + gap + ' de más).';
        }
        if ((s.at_risk || 0) > 0) {
            return '<strong>' + esc(p.name) + '</strong>: su bono caduca antes de '
                + (s.at_risk === 1 ? 'una de las clases.' : s.at_risk + ' de las clases.');
        }
        return null;
    }

    function attach(cfg) {
        var panel = cfg.panel;
        var state = { loading: false, players: [], hasWarning: false };
        var timer = null;
        var seq = 0;

        function render() {
            if (!panel) { return; }
            var lines = state.players.map(alertLine).filter(Boolean);
            state.hasWarning = lines.length > 0;
            if (!lines.length) {
                panel.innerHTML = '';
                panel.classList.add('d-none');
            } else {
                panel.classList.remove('d-none');
                panel.innerHTML = '<div role="alert" style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;'
                    + 'border-radius:8px;padding:10px 14px;font-size:13px;line-height:1.45">'
                    + '<i class="bi bi-exclamation-triangle-fill me-2"></i>'
                    + lines.join('<br><i class="bi bi-exclamation-triangle-fill me-2" style="visibility:hidden"></i>')
                    + '<div style="font-size:12px;margin-top:4px;opacity:.85">Puedes crear la clase igualmente.</div>'
                    + '</div>';
            }
            if (typeof cfg.onChange === 'function') { cfg.onChange(state); }
        }

        function run() {
            var fd = cfg.collect();
            if (!fd) { state.players = []; render(); return; }
            if (cfg.csrfName && !fd.has(cfg.csrfName)) { fd.append(cfg.csrfName, cfg.csrfHash); }

            var mine = ++seq;
            state.loading = true;
            fetch('/clases/api/cobertura', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (mine !== seq) { return; }
                    state.loading = false;
                    state.players = (d && d.success) ? (d.players || []) : [];
                    render();
                })
                .catch(function () {
                    if (mine !== seq) { return; }
                    state.loading = false;
                    state.players = [];
                    render();
                });
        }

        return {
            state: state,
            refresh: function () {
                clearTimeout(timer);
                timer = setTimeout(run, 250);
            },
            reset: function () {
                seq++;
                state.players = [];
                render();
            }
        };
    }

    window.BonoCoverage = { attach: attach };
})();

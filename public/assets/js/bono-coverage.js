/**
 * bono-coverage.js — TICKET-013: cobertura de bono al crear clases.
 *
 * Muestra, por alumno, si su bono cubre las sesiones que se van a crear
 * (cubierta / caduca antes / pendiente de bono) y deja decidir al admin qué
 * hacer con las que no se cubren. No crea nada: solo pregunta a
 * POST /clases/api/cobertura y pinta el resultado.
 *
 * Uso:
 *   const cov = BonoCoverage.attach({
 *       panel:    document.getElementById('bono-cov'),     // contenedor donde se pinta
 *       modeInput: document.getElementById('bono-cov-mode'),// <input hidden name="coverage_mode">
 *       csrfName: '...', csrfHash: '...',
 *       collect:  () => FormData con player_ids[], recurrence_* o session_date,
 *       onChange: (state) => {}                              // opcional
 *   });
 *   cov.refresh();   // llamar cuando cambien alumnos / días / fechas
 */
(function () {
    'use strict';

    var STATUS = {
        covered:   { label: 'Cubierta por bono',        cls: 'bc-chip-ok' },
        at_risk:   { label: 'El bono caduca antes',     cls: 'bc-chip-risk' },
        uncovered: { label: 'Pendiente de bono',        cls: 'bc-chip-gap' }
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmt(d) {
        var p = String(d).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] : d;
    }

    function fmtFull(d) {
        var p = String(d).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
    }

    function attach(cfg) {
        var panel = cfg.panel;
        var state = { loading: false, needsDecision: false, mode: '', players: [], canForce: false, fit: null };
        var timer = null;
        var seq = 0;

        function setMode(m) {
            state.mode = m;
            if (cfg.modeInput) { cfg.modeInput.value = m; }
            if (typeof cfg.onChange === 'function') { cfg.onChange(state); }
        }

        function playerCard(p) {
            var s = p.summary;
            var head;
            if (p.never_had_bono) {
                head = '<span class="bc-tag bc-tag-none"><i class="bi bi-ticket-perforated me-1"></i>Sin bono registrado</span>';
            } else if (!p.bonos.length) {
                head = '<span class="bc-tag bc-tag-gap"><i class="bi bi-ticket-perforated me-1"></i>Sin saldo de bono</span>';
            } else {
                var total = p.bonos.reduce(function (a, b) { return a + b.remaining; }, 0);
                head = '<span class="bc-tag bc-tag-ok"><i class="bi bi-ticket-perforated-fill me-1"></i>Saldo: '
                    + total + ' sesión' + (total === 1 ? '' : 'es') + '</span>';
            }

            var notes = '';
            if (p.debt_count > 0) {
                notes += '<div class="bc-note bc-note-gap"><i class="bi bi-exclamation-circle me-1"></i>Tiene '
                    + p.debt_count + ' sesión' + (p.debt_count === 1 ? '' : 'es') + ' dada' + (p.debt_count === 1 ? '' : 's')
                    + ' sin bono (deuda); se saldará primero con su próximo bono.</div>';
            }
            if (p.expiring_soon) {
                notes += '<div class="bc-note bc-note-risk"><i class="bi bi-hourglass-split me-1"></i>Su bono caduca el '
                    + esc(fmtFull(p.expiring_soon)) + '. Puedes ampliarlo desde Bonos.</div>';
            }
            if (p.never_had_bono) {
                notes += '<div class="bc-note"><i class="bi bi-info-circle me-1"></i>Nunca ha tenido bono en la plataforma: '
                    + 'se crearán todas las clases, pero si asiste quedará registrada como sesión sin bono hasta que se le asigne uno.</div>';
            }

            var chips = Object.keys(p.dates).sort().map(function (d) {
                var st = STATUS[p.dates[d]] || STATUS.uncovered;
                return '<span class="bc-chip ' + st.cls + '" title="' + esc(st.label) + '">' + esc(fmt(d)) + '</span>';
            }).join('');

            var line = s.uncovered > 0
                ? '<strong>' + s.covered + ' de ' + s.requested + '</strong> cubiertas · <span class="bc-gap-text">'
                    + s.uncovered + ' sin cubrir</span>'
                : '<strong>' + s.requested + ' de ' + s.requested + '</strong> cubiertas'
                    + (s.at_risk ? ' · <span class="bc-risk-text">' + s.at_risk + ' con bono que caduca antes</span>' : '');

            return '<div class="bc-card"><div class="bc-card-top"><span class="bc-name">' + esc(p.name) + '</span>' + head + '</div>'
                + '<div class="bc-line">' + line + '</div>' + notes
                + '<div class="bc-chips">' + chips + '</div></div>';
        }

        function legend() {
            return '<div class="bc-legend">'
                + '<span class="bc-chip bc-chip-ok">Cubierta</span> el bono tiene sesión libre · '
                + '<span class="bc-chip bc-chip-risk">Caduca antes</span> hay saldo pero el bono vence antes de esa clase (ampliable) · '
                + '<span class="bc-chip bc-chip-gap">Pendiente</span> sin saldo: si asiste, queda como deuda hasta el próximo bono.</div>';
        }

        function decision(shortPlayers) {
            var names = shortPlayers.map(function (p) { return esc(p.name); }).join(', ');
            var forceDisabled = state.canForce ? '' : ' disabled';
            var fitHtml = '';
            if (state.fit && typeof cfg.onFit === 'function') {
                fitHtml = state.fit.count > 0
                    ? '<div class="bc-fit"><span>Con su bono caben <strong>' + state.fit.count + '</strong> clase' + (state.fit.count === 1 ? '' : 's')
                        + (state.fit.end ? ' (hasta el ' + esc(fmtFull(state.fit.end)) + ')' : '') + '.</span>'
                        + '<button type="button" data-bc-fit>Ajustar la serie a ' + state.fit.count + ' clase' + (state.fit.count === 1 ? '' : 's') + '</button></div>'
                    : '<div class="bc-fit"><span>Ahora mismo no le queda saldo libre para ninguna clase de esta serie.</span></div>';
            }
            return '<div class="bc-decision" role="radiogroup" aria-label="Qué hacer con las clases sin cubrir">'
                + '<div class="bc-decision-title"><i class="bi bi-exclamation-triangle-fill me-1"></i>'
                + 'El bono no cubre toda la serie de ' + names + '. ¿Qué hacemos?</div>' + fitHtml
                + '<label class="bc-opt"><input type="radio" name="bc-mode-radio" value="limit" checked>'
                + '<span><strong>Crear solo las cubiertas</strong> (recomendado)<br>'
                + '<small>El alumno solo entra en las sesiones que su bono cubre. Más adelante se pueden añadir al renovar el bono.</small></span></label>'
                + '<label class="bc-opt' + (state.canForce ? '' : ' bc-opt-off') + '"><input type="radio" name="bc-mode-radio" value="all"' + forceDisabled + '>'
                + '<span><strong>Crear todas, aunque no estén cubiertas</strong><br>'
                + '<small>Las no cubiertas quedan marcadas como <em>pendientes de bono</em> y se avisa al alumno y a los administradores.'
                + (state.canForce ? '' : ' Solo administradores.') + '</small></span></label>'
                + '</div>';
        }

        function render() {
            if (!panel) { return; }
            if (state.loading && !state.players.length) {
                panel.innerHTML = '<div class="bc-loading"><i class="bi bi-hourglass-split me-1"></i>Comprobando bonos…</div>';
                return;
            }
            if (!state.players.length) {
                panel.innerHTML = '';
                panel.classList.add('d-none');
                setMode('');
                return;
            }
            panel.classList.remove('d-none');

            var shortPlayers = state.players.filter(function (p) {
                return !p.never_had_bono && p.summary.uncovered > 0;
            });
            state.needsDecision = shortPlayers.length > 0;

            var html = '<div class="bc-head"><i class="bi bi-ticket-perforated-fill me-1"></i>Cobertura de bono</div>'
                + state.players.map(playerCard).join('') + legend();
            if (state.needsDecision) { html += decision(shortPlayers); }
            panel.innerHTML = html;

            if (state.needsDecision) {
                var fitBtn = panel.querySelector('[data-bc-fit]');
                if (fitBtn) { fitBtn.addEventListener('click', function () { cfg.onFit(state.fit); }); }
                var keep = (state.mode === 'all' && state.canForce) ? 'all' : 'limit';
                var radios = panel.querySelectorAll('input[name="bc-mode-radio"]');
                Array.prototype.forEach.call(radios, function (r) {
                    r.checked = (r.value === keep);
                    r.addEventListener('change', function () { if (r.checked) { setMode(r.value); } });
                });
                setMode(keep);
            } else {
                setMode('');
            }
        }

        function run() {
            var fd = cfg.collect();
            if (!fd) { state.players = []; render(); return; }
            if (cfg.csrfName && !fd.has(cfg.csrfName)) { fd.append(cfg.csrfName, cfg.csrfHash); }

            var mine = ++seq;
            state.loading = true;
            render();
            fetch('/clases/api/cobertura', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (mine !== seq) { return; }
                    state.loading = false;
                    state.players = (d && d.success) ? (d.players || []) : [];
                    state.fit = (d && d.success) ? (d.fit || null) : null;
                    state.canForce = !!(d && d.can_force);
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
                state.mode = '';
                render();
            }
        };
    }

    window.BonoCoverage = { attach: attach };
})();

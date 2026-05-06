/**
 * Modal unificado de creación de clases.
 * Único punto de entrada UI tanto en dashboard como en /clases.
 *
 * Uso:
 *   ClaseModal.init({
 *       csrfName: '...',
 *       csrfHash: '...',
 *       onCreated: (data) => { ... } // se llama tras crear con éxito
 *   });
 *   ClaseModal.open({ date: '2026-05-06', time: '09:00' });
 */
(function () {
    const HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
    const MINS  = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];

    let opts = { csrfName: '', csrfHash: '', onCreated: null };
    let optionsFetched = false;
    let coachOptions   = [];
    let playerOptions  = [];

    const selectedCoaches = new Map(); // id => name
    const selectedPlayers = new Map();
    const selectedDays    = new Set();

    let currentType = 'single';

    // ── DOM helpers ─────────────────────────────────────────────
    function $(id)  { return document.getElementById(id); }
    function show(el){ el && el.classList.remove('d-none'); }
    function hide(el){ el && el.classList.add('d-none'); }

    // ── Init: poblar selects y eventos ──────────────────────────
    function init(options = {}) {
        opts = Object.assign({ csrfName: '', csrfHash: '', onCreated: null }, options);

        const overlay = $('modalCreateClass');
        if (!overlay) return;

        // Poblar horas/minutos
        ['cm-start-h', 'cm-end-h'].forEach(id => {
            const sel = $(id);
            sel.innerHTML = HOURS.map(h => `<option value="${h}">${h}</option>`).join('');
        });
        ['cm-start-m', 'cm-end-m'].forEach(id => {
            const sel = $(id);
            sel.innerHTML = MINS.map(m => `<option value="${m}">${m}</option>`).join('');
        });

        // Cerrar
        overlay.querySelectorAll('[data-cm-close]').forEach(b => b.addEventListener('click', close));
        overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !overlay.classList.contains('d-none')) close();
        });

        // Tabs tipo
        overlay.querySelectorAll('.cm-type-tab').forEach(btn => {
            btn.addEventListener('click', () => setType(btn.dataset.cmType));
        });

        // Días recurrentes
        overlay.querySelectorAll('.cm-day-pill').forEach(pill => {
            pill.addEventListener('click', e => {
                e.preventDefault();
                const cb  = pill.querySelector('.cm-day-check');
                cb.checked = !cb.checked;
                pill.classList.toggle('active', cb.checked);
                if (cb.checked) selectedDays.add(parseInt(cb.value));
                else            selectedDays.delete(parseInt(cb.value));
            });
        });

        // Add coach
        $('cm-coach-sel').addEventListener('change', function () {
            const id = parseInt(this.value);
            if (!id || selectedCoaches.has(id)) { this.value = ''; return; }
            const name = this.options[this.selectedIndex].dataset.name || '';
            selectedCoaches.set(id, name);
            this.value = '';
            renderTags('cm-coach-list', selectedCoaches);
            updateWarning();
        });

        // Add player
        $('cm-player-sel').addEventListener('change', function () {
            const id = parseInt(this.value);
            if (!id || selectedPlayers.has(id)) { this.value = ''; return; }
            const name = this.options[this.selectedIndex].dataset.name || '';
            selectedPlayers.set(id, name);
            this.value = '';
            renderTags('cm-player-list', selectedPlayers);
            updateWarning();
        });

        // Submit
        const submitBtn = overlay.querySelector('button[id$="submit"]') || $('cm-submit');
        submitBtn?.addEventListener('click', submit);

        // Inicializar tipo
        setType('single');
    }

    // ── Cargar opciones (entrenadores, jugadores, lugares) ───────
    async function fetchOptions() {
        if (optionsFetched) return;
        try {
            const res = await fetch('/clases/api/opciones');
            const d   = await res.json();
            coachOptions  = d.coaches  || [];
            playerOptions = d.players  || [];

            const cSel = $('cm-coach-sel');
            const pSel = $('cm-player-sel');
            const lSel = $('cm-location-id');

            coachOptions.forEach(c => {
                const o = document.createElement('option');
                o.value = c.id; o.textContent = c.name; o.dataset.name = c.name;
                cSel.appendChild(o);
            });
            playerOptions.forEach(p => {
                const o = document.createElement('option');
                o.value = p.id; o.textContent = p.name; o.dataset.name = p.name;
                pSel.appendChild(o);
            });
            (d.locations || []).forEach(l => {
                const o = document.createElement('option');
                o.value = l.id; o.textContent = l.name;
                lSel.appendChild(o);
            });

            optionsFetched = true;
        } catch (e) {
            console.warn('No se pudieron cargar opciones de clases', e);
        }
    }

    // ── Tipo (puntual / recurrente) ─────────────────────────────
    function setType(t) {
        currentType = (t === 'recurring') ? 'recurring' : 'single';
        document.querySelectorAll('.cm-type-tab').forEach(b => {
            b.classList.toggle('active', b.dataset.cmType === currentType);
        });
        document.querySelectorAll('.cm-block-single').forEach(el =>
            el.classList.toggle('d-none', currentType !== 'single'));
        document.querySelectorAll('.cm-block-recurring').forEach(el =>
            el.classList.toggle('d-none', currentType !== 'recurring'));
    }

    // ── Render tags ────────────────────────────────────────────
    function renderTags(containerId, map) {
        const el = $(containerId);
        if (!el) return;
        el.innerHTML = '';
        map.forEach((name, id) => {
            const tag = document.createElement('span');
            tag.className = 'cm-tag';
            tag.innerHTML = `${escHtml(name)} <button type="button" aria-label="Quitar">×</button>`;
            tag.querySelector('button').addEventListener('click', () => {
                map.delete(id);
                renderTags(containerId, map);
                updateWarning();
            });
            el.appendChild(tag);
        });
    }

    // ── Warning de límites ─────────────────────────────────────
    function updateWarning() {
        const w   = $('cm-warning');
        const txt = $('cm-warning-text');
        const msgs = [];
        if (selectedCoaches.size > 1) {
            msgs.push(`Has añadido ${selectedCoaches.size} entrenadores. Lo habitual es asignar uno solo.`);
        }
        if (selectedPlayers.size > 2) {
            msgs.push(`Has añadido ${selectedPlayers.size} jugadores. Lo habitual son uno o dos por sesión.`);
        }
        if (msgs.length) {
            txt.textContent = msgs.join(' ');
            show(w);
        } else {
            hide(w);
        }
    }

    // ── Open / Close ───────────────────────────────────────────
    async function open(prefill = {}) {
        await fetchOptions();
        // Reset
        $('cm-title').value = '';
        $('cm-focus').value = '';
        $('cm-pre-notes').value = '';
        $('cm-location-custom').value = '';
        $('cm-location-id').value = '';
        $('cm-rec-start').value = '';
        $('cm-rec-end').value = '';
        selectedCoaches.clear();
        selectedPlayers.clear();
        selectedDays.clear();
        document.querySelectorAll('.cm-day-pill').forEach(p => {
            p.classList.remove('active');
            const cb = p.querySelector('.cm-day-check'); if (cb) cb.checked = false;
        });
        renderTags('cm-coach-list', selectedCoaches);
        renderTags('cm-player-list', selectedPlayers);
        hide($('cm-error'));
        hide($('cm-warning'));

        // Defaults
        const today = new Date();
        const fmt = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        $('cm-date').value      = prefill.date     || fmt(today);
        $('cm-rec-start').value = prefill.date     || fmt(today);
        const oneMonth = new Date(today); oneMonth.setMonth(oneMonth.getMonth() + 1);
        $('cm-rec-end').value   = prefill.recEnd   || fmt(oneMonth);

        if (prefill.time) {
            const [h, m] = prefill.time.split(':');
            $('cm-start-h').value = h.padStart(2, '0');
            $('cm-start-m').value = roundMinute(m);
            const endH = (parseInt(h) + 1) % 24;
            $('cm-end-h').value = String(endH).padStart(2, '0');
            $('cm-end-m').value = roundMinute(m);
        } else {
            $('cm-start-h').value = '09';
            $('cm-start-m').value = '00';
            $('cm-end-h').value   = '10';
            $('cm-end-m').value   = '00';
        }

        setType('single');
        $('modalCreateClass').classList.remove('d-none');
        document.body.style.overflow = 'hidden';
        setTimeout(() => $('cm-title').focus(), 50);
    }

    function close() {
        $('modalCreateClass').classList.add('d-none');
        document.body.style.overflow = '';
    }

    function roundMinute(m) {
        m = parseInt(m || '0');
        const opts = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];
        let best = 0, diff = 99;
        opts.forEach(o => { if (Math.abs(o - m) < diff) { diff = Math.abs(o - m); best = o; }});
        return String(best).padStart(2, '0');
    }

    // ── Submit ─────────────────────────────────────────────────
    async function submit() {
        const errEl  = $('cm-error');
        const title  = $('cm-title').value.trim();
        const startH = $('cm-start-h').value, startM = $('cm-start-m').value;
        const endH   = $('cm-end-h').value,   endM   = $('cm-end-m').value;
        const start  = `${startH}:${startM}`;
        const end    = `${endH}:${endM}`;

        hide(errEl);

        if (!title) {
            errEl.textContent = 'El título es obligatorio.'; show(errEl); return;
        }

        // Validar tipo
        if (currentType === 'single') {
            if (!$('cm-date').value) {
                errEl.textContent = 'La fecha es obligatoria.'; show(errEl); return;
            }
        } else {
            if (selectedDays.size === 0) {
                errEl.textContent = 'Selecciona al menos un día de la semana.'; show(errEl); return;
            }
            if (!$('cm-rec-start').value || !$('cm-rec-end').value) {
                errEl.textContent = 'Selecciona el rango de fechas para la recurrencia.'; show(errEl); return;
            }
            if ($('cm-rec-end').value < $('cm-rec-start').value) {
                errEl.textContent = 'La fecha "Hasta" debe ser posterior a "Desde".'; show(errEl); return;
            }
        }

        // Validar fin > inicio si fin proporcionado
        if (end && end <= start) {
            errEl.textContent = 'La hora de fin debe ser posterior a la hora de inicio.';
            show(errEl); return;
        }

        // Construir form data
        const fd = new FormData();
        if (opts.csrfName) fd.append(opts.csrfName, opts.csrfHash);
        fd.append('type',         currentType);
        fd.append('title',        title);
        fd.append('start_time',   start);
        fd.append('end_time',     end);
        fd.append('location_id',  $('cm-location-id').value);
        fd.append('location_custom', $('cm-location-custom').value);
        fd.append('focus',        $('cm-focus').value);
        fd.append('pre_notes',    $('cm-pre-notes').value);

        if (currentType === 'single') {
            fd.append('session_date', $('cm-date').value);
        } else {
            fd.append('recurrence_start', $('cm-rec-start').value);
            fd.append('recurrence_end',   $('cm-rec-end').value);
            selectedDays.forEach(d => fd.append('recurrence_days[]', d));
        }

        selectedCoaches.forEach((_, id) => fd.append('coach_ids[]', id));
        selectedPlayers.forEach((_, id) => fd.append('player_ids[]', id));

        // Botón
        const btn = $('cm-submit');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creando…';

        try {
            const res  = await fetch('/clases/rapida', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                close();
                if (typeof opts.onCreated === 'function') opts.onCreated(data);
            } else {
                errEl.textContent = data.error || 'Error al crear la sesión.';
                show(errEl);
            }
        } catch (e) {
            errEl.textContent = 'Error de conexión. Inténtalo de nuevo.';
            show(errEl);
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    window.ClaseModal = { init, open, close };
})();

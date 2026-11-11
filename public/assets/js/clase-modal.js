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
    let cov = null; // BonoCoverage (TICKET-013)

    function refreshCov() { if (cov) cov.refresh(); }

    let sb = null; // SeriesBuilder (primera clase, nº de clases, fecha límite, clase a clase)
    function curTimes() {
        return { start: $('cm-start-h').value + ':' + $('cm-start-m').value, end: $('cm-end-h').value + ':' + $('cm-end-m').value };
    }

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
                if (sb) sb.refresh();
                refreshCov();
            });
        });

        // Buscadores de entrenador y de alumno (sustituyen a los desplegables)
        PersonPicker.attach({
            root:       $('cm-coach-picker'),
            getOptions: () => coachOptions,
            isTaken:    id => selectedCoaches.has(parseInt(id)),
            onPick:     o => {
                selectedCoaches.set(parseInt(o.id), o.name);
                renderTags('cm-coach-list', selectedCoaches);
                updateWarning();
            }
        });
        PersonPicker.attach({
            root:       $('cm-player-picker'),
            getOptions: () => playerOptions,
            isTaken:    id => selectedPlayers.has(parseInt(id)),
            onPick:     o => {
                selectedPlayers.set(parseInt(o.id), o.name);
                renderTags('cm-player-list', selectedPlayers);
                updateWarning();
                refreshCov();
            }
        });

        // Alumno registrado / Externo (clase puntual de prueba o suelta)
        overlay.querySelectorAll('[data-cm-who]').forEach(btn => {
            btn.addEventListener('click', () => switchWho(btn.dataset.cmWho));
        });

        // Submit
        const submitBtn = overlay.querySelector('button[id$="submit"]') || $('cm-submit');
        submitBtn?.addEventListener('click', submit);
        attachGuard(overlay);

        // Configuración de la serie (TICKET-013)
        if (window.SeriesBuilder && $('cm-series-builder')) {
            sb = SeriesBuilder.attach({
                root:     $('cm-series-builder'),
                simple:   true,                  // clase rápida: solo primera clase + nº de clases
                moreUrl:  '/clases/nueva',       // lo avanzado, en el formulario completo
                getDays:  () => Array.from(selectedDays),
                getTimes: curTimes,
                onChange: refreshCov
            });
            ['cm-start-h', 'cm-start-m', 'cm-end-h', 'cm-end-m'].forEach(id =>
                $(id).addEventListener('change', () => { sb.refresh(); refreshCov(); }));
        }

        // Aviso de bono (TICKET-013): solo avisa, nunca bloquea
        if (window.BonoCoverage && $('cm-bono-cov')) {
            cov = BonoCoverage.attach({
                panel:     $('cm-bono-cov'),
                csrfName:  opts.csrfName,
                csrfHash:  opts.csrfHash,
                collect: function () {
                    if (!selectedPlayers.size) return null;
                    const fd = new FormData();
                    selectedPlayers.forEach((_, id) => fd.append('player_ids[]', id));
                    if (currentType === 'recurring') {
                        if (!sb || sb.validate() || !selectedDays.size) return null;
                        selectedDays.forEach(d => fd.append('recurrence_days[]', d));
                        sb.appendTo(fd);
                    } else {
                        if (!$('cm-date').value) return null;
                        fd.append('session_date', $('cm-date').value);
                    }
                    return fd;
                }
            });
            $('cm-date').addEventListener('change', refreshCov);
        }

        // Inicializar tipo
        setType('single');
    }

    // ── Botón «Crear» blando: dice qué falta y lleva al campo (JPGuard) ──
    // Espejo de lo que exige el servidor (ClasesService::participantsError + horas + fecha).
    let guard = null;
    function attachGuard(overlay) {
        if (!window.JPGuard || !$('cm-submit')) return;
        const exRoot = () => $('cm-ext-block')?.querySelector('[data-ex-root]');
        const people = () => (exRoot() && window.ExternosList) ? ExternosList.attach(exRoot()).collect() : { people: [], error: '' };
        const dateEl = () => $('cm-date')._dateEs || $('cm-date');
        guard = JPGuard.attach({
            root: overlay,
            buttons: [$('cm-submit')],
            // la línea «Falta: …» va entre el cuerpo (que tiene scroll) y los botones: siempre a la vista
            hintAfter: $('cm-error').parentElement, hintClass: 'jpg-hint-bar',
            rules: [
                { label: 'el título', ok: () => $('cm-title').value.trim() !== '', focus: () => $('cm-title') },
                { label: 'la fecha', ok: () => currentType !== 'single' || !!$('cm-date').value, focus: dateEl },
                { label: 'al menos un día de la semana', ok: () => currentType !== 'recurring' || selectedDays.size > 0,
                  focus: () => overlay.querySelector('.cm-block-recurring button, .cm-block-recurring input') },
                { label: 'la configuración de la serie', ok: () => currentType !== 'recurring' || !sb || !sb.validate(),
                  focus: () => overlay.querySelector('#cm-series-builder input, #cm-series-builder button') },
                { label: 'una hora de fin posterior a la de inicio', ok: () => { const t = curTimes(); return !t.end || t.end === '00:00' || t.end > t.start; }, focus: () => $('cm-end-h') },
                { label: 'un entrenador', ok: () => selectedCoaches.size > 0, focus: () => $('cm-coach-q') },
                { label: 'un alumno', ok: () => externoMode || selectedPlayers.size > 0, focus: () => $('cm-player-q') },
                { label: 'el nombre de la persona externa', ok: () => { if (!externoMode) return true; const c = people(); return c.people.length > 0 && !c.error; }, focus: () => $('cm-ex-first') }
            ]
        });
    }

    // ── Cargar opciones (entrenadores, jugadores, lugares) ───────
    async function fetchOptions() {
        if (optionsFetched) return;
        try {
            const res = await fetch('/clases/api/opciones');
            const d   = await res.json();
            coachOptions  = d.coaches  || [];
            playerOptions = d.players  || [];

            const lSel = $('cm-location-id');

            // Entrenadores y jugadores los consultan los buscadores (PersonPicker) desde estas listas.
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

    // ── Alumno registrado / Externo ─────────────────────────────
    let externoMode = false;

    function setWho(mode) {
        const hasExt = !!$('cm-ext-block');
        externoMode = hasExt && mode === 'ext';
        document.querySelectorAll('[data-cm-who]').forEach(b =>
            b.classList.toggle('active', b.dataset.cmWho === (externoMode ? 'ext' : 'reg')));
        $('cm-reg-block')?.classList.toggle('d-none', externoMode);
        $('cm-ext-block')?.classList.toggle('d-none', !externoMode);
        $('cm-ext-hint')?.classList.toggle('d-none', !externoMode);
        refreshCov();
    }

    function externosPendientes() {
        const root = $('cm-ext-block')?.querySelector('[data-ex-root]');
        return root && window.ExternosList ? ExternosList.pendingCount(root) : 0;
    }
    function clearExternos() {
        const root = $('cm-ext-block')?.querySelector('[data-ex-root]');
        if (root && window.ExternosList) ExternosList.attach(root).reset();
    }

    // Cambio Alumno ⇄ Externo: nunca se descarta nada sin preguntar (la clase lleva alumnos O externos, no ambos).
    function switchWho(mode) {
        const want = mode === 'ext' ? 'ext' : 'reg';
        if (want === (externoMode ? 'ext' : 'reg')) return;
        if (want === 'ext' && selectedPlayers.size > 0) {
            ExternosList.confirmDiscard({
                title: 'Ya has elegido alumnos registrados',
                description: Array.from(selectedPlayers.values()).join(', ') + '. Si pasas a «Externo» se quitarán de esta clase (una clase lleva alumnos registrados o personas externas, no ambos). ¿Quitarlos?',
                confirmLabel: 'Quitar alumnos y pasar a Externo', cancelLabel: 'Mantener alumnos'
            }).then(ok => { if (ok) { selectedPlayers.clear(); renderTags('cm-player-list', selectedPlayers); updateWarning(); setWho('ext'); } });
            return;
        }
        if (want === 'reg' && externosPendientes() > 0) {
            const n = externosPendientes();
            ExternosList.confirmDiscard({
                title: 'Ya has añadido personas externas',
                description: n + ' persona' + (n === 1 ? '' : 's') + ' externa' + (n === 1 ? '' : 's') + ' (guardada' + (n === 1 ? '' : 's') + ' o a medio escribir). Si pasas a «Alumno» se quitarán de esta clase. ¿Quitarlas?',
                confirmLabel: 'Quitar personas y pasar a Alumno', cancelLabel: 'Mantener externas'
            }).then(ok => { if (ok) { clearExternos(); setWho('reg'); } });
            return;
        }
        setWho(want);
    }

    function resetExterno() {
        const root = $('cm-ext-block')?.querySelector('[data-ex-root]');
        if (root && window.ExternosList) ExternosList.attach(root).reset();
        setWho('reg');
    }

    // Una clase recurrente no admite externos (es una clase puntual de una persona).
    function syncWho() {
        const wrap = $('cm-who');
        if (!wrap) return;
        const recurring = currentType === 'recurring';
        wrap.classList.toggle('d-none', recurring);
        if (recurring && externoMode) setWho('reg');
    }

    // ── Tipo (puntual / recurrente) ─────────────────────────────
    function setType(t) {
        // Una clase recurrente no admite externos: se pregunta antes de quitarlos.
        if (t === 'recurring' && currentType !== 'recurring' && externoMode && externosPendientes() > 0) {
            const n = externosPendientes();
            ExternosList.confirmDiscard({
                title: 'Una clase recurrente no admite personas externas',
                description: 'Has añadido ' + n + ' persona' + (n === 1 ? '' : 's') + ' externa' + (n === 1 ? '' : 's') + '. Si sigues se quitarán. Para una persona externa usa una clase puntual.',
                confirmLabel: 'Quitar y hacerla recurrente', cancelLabel: 'Mantener clase puntual'
            }).then(ok => { if (ok) { clearExternos(); setType('recurring'); } });
            return;
        }
        currentType = (t === 'recurring') ? 'recurring' : 'single';
        syncWho();
        document.querySelectorAll('.cm-type-tab').forEach(b => {
            b.classList.toggle('active', b.dataset.cmType === currentType);
        });
        document.querySelectorAll('.cm-block-single').forEach(el =>
            el.classList.toggle('d-none', currentType !== 'single'));
        document.querySelectorAll('.cm-block-recurring').forEach(el =>
            el.classList.toggle('d-none', currentType !== 'recurring'));
        refreshCov();
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
                if (containerId === 'cm-player-list') refreshCov();
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
        selectedCoaches.clear();
        selectedPlayers.clear();
        selectedDays.clear();
        document.querySelectorAll('.cm-day-pill').forEach(p => {
            p.classList.remove('active');
            const cb = p.querySelector('.cm-day-check'); if (cb) cb.checked = false;
        });
        // Preselección del responsable (TICKET-011): al crear desde un
        // calendario ya filtrado por "Ver calendario de <persona>", se
        // asigna directamente en vez de dejarlo vacío.
        if (prefill.coachId) {
            const match = coachOptions.find(c => parseInt(c.id) === parseInt(prefill.coachId));
            if (match) selectedCoaches.set(parseInt(match.id), match.name);
        }
        renderTags('cm-coach-list', selectedCoaches);
        renderTags('cm-player-list', selectedPlayers);
        resetExterno();
        hide($('cm-error'));
        hide($('cm-warning'));

        // Defaults
        const today = new Date();
        const fmt = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        $('cm-date').value      = prefill.date     || fmt(today);

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

        if (cov) cov.reset();
        if (sb) sb.reset(prefill.date || fmt(today), 4);
        setType('single');
        $('modalCreateClass').classList.remove('d-none');
        document.body.style.overflow = 'hidden';
        guard?.refresh();
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
            const sbErr = sb ? sb.validate() : 'No se pudo cargar la configuración de la serie. Recarga la página.';
            if (sbErr) {
                errEl.textContent = sbErr; show(errEl); return;
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
        fd.append('class_format', document.querySelector('input[name="cm-class-format"]:checked')?.value || 'individual');
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
            sb.appendTo(fd);
            selectedDays.forEach(d => fd.append('recurrence_days[]', d));
        }

        selectedCoaches.forEach((_, id) => fd.append('coach_ids[]', id));
        if (externoMode && currentType === 'single') {
            // Persona externa: sus datos viajan como externo[...] y el servidor valida y crea clase + ficha juntas.
            fd.append('externo_mode', '1');
            const exErr = window.ExternosList ? ExternosList.appendTo($('cm-ext-block').querySelector('[data-ex-root]'), fd, 'externos') : 'No se pudo cargar el formulario de externos. Recarga la página.';
            if (exErr) { errEl.textContent = exErr; show(errEl); return; }
        } else {
            selectedPlayers.forEach((_, id) => fd.append('player_ids[]', id));
        }

        // Botón
        const btn = $('cm-submit');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creando…';

        try {
            const res = await fetch('/clases/rapida', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const raw = await res.text();
            let data = null;
            try { data = JSON.parse(raw); } catch (e) { /* no era JSON */ }
            if (data && data.csrf) opts.csrfHash = data.csrf;   // por si el servidor rota el token

            // Sesión caducada / página desactualizada: redirige al login (no es JSON) o responde 401/403 sin mensaje de negocio.
            const sessionLost = res.redirected || /\/login/.test(res.url) || res.status === 401 || (res.status === 403 && !(data && data.error));
            if (sessionLost) {
                errEl.textContent = 'Tu sesión ha caducado o la página está desactualizada. Recarga la página (F5) e inténtalo de nuevo; no se ha creado nada.';
                show(errEl);
                return;
            }
            if (!data) {
                console.error('clases/rapida: respuesta inesperada', res.status, raw.slice(0, 300));
                errEl.textContent = 'El servidor ha respondido con un error (' + res.status + '). No se ha creado nada: inténtalo de nuevo o avisa con el botón «Reportar».';
                show(errEl);
                return;
            }
            if (data.success) {
                close();
                try { if (typeof opts.onCreated === 'function') opts.onCreated(data); }
                catch (e) { console.error('clases/rapida: error al refrescar el calendario', e); }
            } else if (window.handleApiError && window.handleApiError(data, 'clases.rapida')) {
                close();
            } else {
                errEl.textContent = data.error || 'Error al crear la sesión.';
                show(errEl);
            }
        } catch (e) {
            console.error('clases/rapida', e);
            errEl.textContent = 'No se pudo contactar con el servidor (¿sin conexión?). No se ha creado nada: inténtalo de nuevo.';
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

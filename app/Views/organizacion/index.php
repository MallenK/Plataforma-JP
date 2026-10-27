<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/organizacion.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/organizacion.css') ?: time() ?>">

<div class="org-wrap" id="org-app">

    <div class="org-head">
        <div>
            <h2 class="org-title"><i class="bi bi-kanban-fill"></i> Organización</h2>
            <p class="org-sub">Tareas por estados y agenda del equipo. Lo marcado como <i class="bi bi-lock-fill"></i> «Solo yo» no lo ve nadie más.</p>
        </div>
        <div class="org-actions">
            <button type="button" class="btn btn-primary btn-sm" id="org-new-task"><i class="bi bi-plus-lg"></i> Nueva tarea</button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="org-new-event"><i class="bi bi-calendar-plus"></i> Nuevo evento</button>
        </div>
    </div>

    <div class="org-toolbar">
        <div class="org-tabs" role="tablist">
            <button type="button" class="org-tab active" data-view="board" role="tab"><i class="bi bi-columns-gap"></i> Tareas</button>
            <button type="button" class="org-tab" data-view="agenda" role="tab"><i class="bi bi-calendar3"></i> Agenda</button>
        </div>
        <div class="org-filters" role="group" aria-label="Filtro">
            <button type="button" class="org-filter active" data-filter="all">Todo</button>
            <button type="button" class="org-filter" data-filter="mine">Mías</button>
            <button type="button" class="org-filter" data-filter="private"><i class="bi bi-lock-fill"></i> Solo yo</button>
        </div>
    </div>

    <div id="org-loading" class="org-empty">Cargando…</div>

    <section id="org-board"   class="org-panel" hidden></section>
    <section id="org-agenda"  class="org-panel" hidden></section>
</div>

<!-- Tarea -->
<dialog id="org-task-dlg" class="org-dlg">
    <form id="org-task-form" autocomplete="off">
        <h3 id="org-task-h">Nueva tarea</h3>
        <input type="hidden" name="id">
        <label class="org-f">Título
            <input type="text" name="title" maxlength="200" required>
        </label>
        <label class="org-f">Descripción
            <textarea name="description" rows="3"></textarea>
        </label>
        <div class="org-row">
            <label class="org-f">Estado
                <select name="status">
                    <?php foreach ($statuses as $k => $v): ?><option value="<?= esc($k, 'attr') ?>"><?= esc($v) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="org-f">Prioridad
                <select name="priority">
                    <option value="baja">Baja</option>
                    <option value="normal" selected>Normal</option>
                    <option value="alta">Alta</option>
                </select>
            </label>
        </div>
        <div class="org-row">
            <label class="org-f">Asignada a
                <select name="assigned_to"><option value="">Sin asignar</option></select>
            </label>
            <label class="org-f">Fecha límite
                <input type="date" name="due_date">
            </label>
        </div>
        <label class="org-f">Visibilidad
            <select name="visibility">
                <option value="team">Equipo — la ve todo el equipo</option>
                <option value="personal">Solo yo — privada</option>
            </select>
        </label>
        <p class="org-note" id="org-task-note" hidden></p>
        <div class="org-dlg-foot">
            <button type="button" class="btn btn-outline-danger btn-sm" id="org-task-archive" hidden><i class="bi bi-archive"></i> Archivar</button>
            <span class="org-spacer"></span>
            <button type="button" class="btn btn-light btn-sm" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary btn-sm" id="org-task-save">Guardar</button>
        </div>
    </form>
</dialog>

<!-- Evento -->
<dialog id="org-event-dlg" class="org-dlg">
    <form id="org-event-form" autocomplete="off">
        <h3 id="org-event-h">Nuevo evento</h3>
        <input type="hidden" name="id">
        <label class="org-f">Título
            <input type="text" name="title" maxlength="200" required>
        </label>
        <div class="org-row org-row-3">
            <label class="org-f">Fecha
                <input type="date" name="date" required>
            </label>
            <label class="org-f">Inicio
                <input type="time" name="start_time">
            </label>
            <label class="org-f">Fin
                <input type="time" name="end_time">
            </label>
        </div>
        <label class="org-check"><input type="checkbox" name="all_day" value="1"> Todo el día</label>
        <label class="org-f">Lugar
            <input type="text" name="location_text" maxlength="160">
        </label>
        <label class="org-f">Notas
            <textarea name="description" rows="3"></textarea>
        </label>
        <fieldset class="org-f">
            <legend>Participantes</legend>
            <div id="org-event-people" class="org-people"></div>
        </fieldset>
        <label class="org-f">Visibilidad
            <select name="visibility">
                <option value="team">Equipo — lo ve todo el equipo</option>
                <option value="personal">Solo yo — privado</option>
            </select>
        </label>
        <p class="org-note" id="org-event-note" hidden></p>
        <div class="org-dlg-foot">
            <button type="button" class="btn btn-outline-danger btn-sm" id="org-event-archive" hidden><i class="bi bi-archive"></i> Archivar</button>
            <span class="org-spacer"></span>
            <button type="button" class="btn btn-light btn-sm" data-close>Cancelar</button>
            <button type="submit" class="btn btn-primary btn-sm" id="org-event-save">Guardar</button>
        </div>
    </form>
</dialog>

<script>window.ORG = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="<?= base_url('assets/js/organizacion.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/organizacion.js') ?: time() ?>"></script>

<?= $this->endSection() ?>

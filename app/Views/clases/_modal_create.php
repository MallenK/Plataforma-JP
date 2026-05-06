<?php
/**
 * Modal unificado de creación de clases.
 * Se incluye desde el dashboard y desde /clases.
 *
 * Variables esperadas (opcionales — si no se pasan, se cargan via AJAX):
 *  - $modalId   string  ID del overlay (default: 'modalCreateClass')
 *  - $btnId     string  ID del botón submit
 */
$modalId = $modalId ?? 'modalCreateClass';
$btnId   = $btnId   ?? 'cm-submit';
?>

<div id="<?= $modalId ?>" class="cs-modal-overlay d-none">
    <div class="cs-modal cm-modal">
        <div class="cs-modal-header">
            <span><i class="bi bi-plus-circle-fill me-2" style="color:var(--accent)"></i>Nueva clase</span>
            <button type="button" data-cm-close><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="cs-modal-body">

            <!-- Tipo: puntual / recurrente -->
            <div class="cm-type-tabs mb-3">
                <button type="button" class="cm-type-tab active" data-cm-type="single">
                    <i class="bi bi-calendar-event me-1"></i>Sesión puntual
                </button>
                <button type="button" class="cm-type-tab" data-cm-type="recurring">
                    <i class="bi bi-arrow-repeat me-1"></i>Recurrente
                </button>
            </div>

            <div class="row g-3">
                <!-- Título -->
                <div class="col-12">
                    <label class="form-label">Título <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="cm-title" class="form-control-jp"
                           placeholder="Ej: Entrenamiento individual – Control y pase">
                </div>

                <!-- Sesión puntual: fecha -->
                <div class="col-12 col-md-12 cm-block-single">
                    <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="cm-date" class="form-control-jp">
                </div>

                <!-- Recurrente: días + rango fechas -->
                <div class="col-12 cm-block-recurring d-none">
                    <label class="form-label">Días de la semana <span style="color:var(--danger)">*</span></label>
                    <div class="cm-day-row">
                        <?php foreach ([1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'] as $n=>$d): ?>
                        <label class="cm-day-pill">
                            <input type="checkbox" class="cm-day-check" value="<?= $n ?>">
                            <span><?= $d ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-6 cm-block-recurring d-none">
                    <label class="form-label">Desde <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="cm-rec-start" class="form-control-jp">
                </div>
                <div class="col-6 cm-block-recurring d-none">
                    <label class="form-label">Hasta <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="cm-rec-end" class="form-control-jp">
                </div>

                <!-- Hora inicio (24h custom) -->
                <div class="col-6 col-md-6">
                    <label class="form-label">Hora inicio <span style="color:var(--danger)">*</span></label>
                    <div class="cm-time-pair">
                        <select id="cm-start-h" class="form-control-jp cm-time-select" aria-label="Hora inicio - horas"></select>
                        <span>:</span>
                        <select id="cm-start-m" class="form-control-jp cm-time-select" aria-label="Hora inicio - minutos"></select>
                    </div>
                </div>
                <!-- Hora fin (24h custom) -->
                <div class="col-6 col-md-6">
                    <label class="form-label">Hora fin</label>
                    <div class="cm-time-pair">
                        <select id="cm-end-h" class="form-control-jp cm-time-select" aria-label="Hora fin - horas"></select>
                        <span>:</span>
                        <select id="cm-end-m" class="form-control-jp cm-time-select" aria-label="Hora fin - minutos"></select>
                    </div>
                </div>

                <!-- Lugar -->
                <div class="col-12 col-md-6">
                    <label class="form-label">Instalación</label>
                    <select id="cm-location-id" class="form-control-jp">
                        <option value="">— Seleccionar —</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">O lugar personalizado</label>
                    <input type="text" id="cm-location-custom" class="form-control-jp"
                           placeholder="Ej: Estadio Municipal, Campo 3">
                </div>

                <!-- Foco / objetivo -->
                <div class="col-12">
                    <label class="form-label">Objetivo del entrenamiento</label>
                    <input type="text" id="cm-focus" class="form-control-jp"
                           placeholder="Ej: Mejora del primer toque, presión alta…">
                </div>

                <!-- Pre-notes -->
                <div class="col-12">
                    <label class="form-label">Notas de planificación</label>
                    <textarea id="cm-pre-notes" class="form-control-jp" rows="2"
                              placeholder="Descripción / ejercicios / objetivos…"></textarea>
                </div>

                <!-- Entrenadores -->
                <div class="col-12 col-md-6">
                    <label class="form-label">Entrenadores</label>
                    <select id="cm-coach-sel" class="form-control-jp">
                        <option value="">Añadir entrenador…</option>
                    </select>
                    <div id="cm-coach-list" class="cm-tags mt-2"></div>
                </div>
                <!-- Jugadores -->
                <div class="col-12 col-md-6">
                    <label class="form-label">Jugadores</label>
                    <select id="cm-player-sel" class="form-control-jp">
                        <option value="">Añadir jugador…</option>
                    </select>
                    <div id="cm-player-list" class="cm-tags mt-2"></div>
                </div>
            </div>

            <div id="cm-warning" class="cm-warning d-none">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <span id="cm-warning-text"></span>
            </div>
            <div id="cm-error" class="cm-error d-none"></div>
        </div>

        <div class="cs-modal-footer">
            <button type="button" class="btn-jp btn-jp-secondary" data-cm-close>Cancelar</button>
            <button type="button" class="btn-jp btn-jp-primary" id="<?= $btnId ?>">
                <i class="bi bi-check-lg me-1"></i>Crear
            </button>
        </div>
    </div>
</div>

<style>
/* Modal base (compatible con la clase cs-modal-overlay existente) */
.cs-modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1050;
    display:flex;align-items:center;justify-content:center;padding:16px;
}
.cs-modal {
    background:var(--bg-card);border:1px solid var(--border);border-radius:12px;
    width:100%;max-width:480px;max-height:90vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.cm-modal { max-width: 720px; }
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
.cs-modal-footer {
    display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid var(--border);
}

/* Tabs tipo sesión */
.cm-type-tabs { display:flex; gap:8px; }
.cm-type-tab {
    flex:1;padding:10px 14px;border:2px solid var(--border);background:var(--bg-card);
    border-radius:8px;font-size:13px;font-weight:600;color:var(--text-muted);cursor:pointer;
    transition:all .15s;
}
.cm-type-tab.active {
    border-color:var(--accent);background:var(--accent-light);color:var(--accent);
}

/* Selector de tiempo 24h */
.cm-time-pair { display:flex; align-items:center; gap:6px; }
.cm-time-pair span { font-weight:700; color:var(--text-h); }
.cm-time-select { flex:1; }

/* Días */
.cm-day-row { display:flex; flex-wrap:wrap; gap:6px; }
.cm-day-pill {
    cursor:pointer; padding:6px 12px; border:1px solid var(--border);
    border-radius:20px; font-size:12.5px; font-weight:600; color:var(--text-body);
    transition:all .15s; user-select:none;
}
.cm-day-pill input { display:none; }
.cm-day-pill.active {
    background:var(--accent-light); border-color:var(--accent); color:var(--accent);
}

/* Tags de entrenadores/jugadores */
.cm-tags { display:flex; flex-wrap:wrap; gap:5px; min-height:24px; }
.cm-tag {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 8px; border-radius:20px; font-size:11.5px; font-weight:600;
    background:var(--accent-light); color:var(--accent);
}
.cm-tag button {
    background:none; border:none; color:var(--accent); cursor:pointer;
    padding:0; font-size:13px; line-height:1;
}

/* Warning / error */
.cm-warning {
    margin-top:12px; padding:8px 12px; border-radius:6px;
    background:rgba(245,158,11,.12); color:#b45309; font-size:12.5px;
    border:1px solid rgba(245,158,11,.3);
}
.cm-error {
    margin-top:12px; padding:8px 12px; border-radius:6px;
    background:rgba(239,68,68,.10); color:var(--danger); font-size:12.5px;
    border:1px solid rgba(239,68,68,.25);
}
</style>

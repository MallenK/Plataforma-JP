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

// Persona externa (clase de prueba / suelta): solo si las tablas existen. El precio y el cobro,
// además, solo con acceso total a Finanzas (igual que en el formulario completo).
$cmExterno = false; $cmMoney = false; $cmMethods = [];
try {
    $cmSvc     = new \App\Services\ExternosService();
    $cmExterno = $cmSvc->available();
    $cmMoney   = $cmExterno && \App\Services\FinanceAccessService::canFull() && $cmSvc->moneyAvailable();
    $cmMethods = $cmMoney ? (new \App\Services\FinanceService())->methods() : [];
} catch (\Throwable $e) {
    $cmExterno = false; $cmMoney = false;
}
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

                <!-- Entrenadores (buscador) -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="cm-coach-q">Entrenadores</label>
                    <div class="pp" id="cm-coach-picker">
                        <input type="search" id="cm-coach-q" class="form-control-jp" placeholder="Buscar entrenador…">
                        <ul class="pp-list" hidden></ul>
                    </div>
                    <div id="cm-coach-list" class="cm-tags mt-2"></div>
                </div>

                <!-- Jugadores: alumno registrado o persona externa (clase puntual) -->
                <div class="col-12 col-md-6">
                    <label class="form-label" for="cm-player-q">Jugadores</label>
                    <?php if ($cmExterno): ?>
                    <div class="cm-who mb-2" id="cm-who" role="radiogroup" aria-label="Quién recibe la clase">
                        <button type="button" class="cm-who-tab active" data-cm-who="reg"><i class="bi bi-person-check-fill me-1"></i>Alumno</button>
                        <button type="button" class="cm-who-tab" data-cm-who="ext"><i class="bi bi-person-badge-fill me-1"></i>Externo</button>
                    </div>
                    <?php endif; ?>
                    <div id="cm-reg-block">
                        <div class="pp" id="cm-player-picker">
                            <input type="search" id="cm-player-q" class="form-control-jp" placeholder="Buscar alumno…">
                            <ul class="pp-list" hidden></ul>
                        </div>
                        <div id="cm-player-list" class="cm-tags mt-2"></div>
                    </div>
                    <?php if ($cmExterno): ?>
                    <p id="cm-ext-hint" class="d-none" style="font-size:12px;color:var(--text-muted);margin:0">
                        Persona que no es alumna (clase de prueba o suelta): rellena sus datos abajo.
                    </p>
                    <?php endif; ?>
                </div>

                <?php if ($cmExterno): ?>
                <!-- Datos de la persona externa: solo se envían en modo «Externo» -->
                <div class="col-12 d-none" id="cm-ext-block">
                    <div class="cm-ext-box" data-ex-root>
                        <div data-ex-list></div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-first">Nombre <span style="color:var(--danger)">*</span></label>
                                <input type="text" id="cm-ex-first" data-ex="first_name" class="form-control-jp" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-last">Apellidos</label>
                                <input type="text" id="cm-ex-last" data-ex="last_name" class="form-control-jp" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-birth">Fecha de nacimiento <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                                <input type="date" id="cm-ex-birth" data-ex="birth_date" class="form-control-jp" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-phone">Teléfono</label>
                                <input type="tel" id="cm-ex-phone" data-ex="phone" class="form-control-jp" maxlength="30">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-email">Email</label>
                                <input type="email" id="cm-ex-email" data-ex="email" class="form-control-jp" maxlength="150">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-gname">Madre / padre / tutor <span style="font-weight:400;color:var(--text-muted)">(si es menor)</span></label>
                                <input type="text" id="cm-ex-gname" data-ex="guardian_name" class="form-control-jp" maxlength="150">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cm-ex-gphone">Teléfono del tutor</label>
                                <input type="tel" id="cm-ex-gphone" data-ex="guardian_phone" class="form-control-jp" maxlength="30">
                            </div>
                            <?php if ($cmMoney): ?>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="cm-ex-price">Precio (€)</label>
                                <input type="text" inputmode="decimal" id="cm-ex-price" data-ex="price" class="form-control-jp" placeholder="0 = gratis">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="cm-ex-method">Medio de pago</label>
                                <select id="cm-ex-method" data-ex="method_id" class="form-control-jp">
                                    <?php foreach ($cmMethods as $m): ?>
                                        <option value="<?= (int) $m['id'] ?>" <?= $m['code'] === 'efectivo' ? 'selected' : '' ?>><?= esc($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 d-flex align-items-end">
                                <label style="display:flex;gap:8px;align-items:center;font-size:13px;cursor:pointer;margin-bottom:10px">
                                    <input type="checkbox" id="cm-ex-paid" data-ex="paid_now" value="1"><span>Cobrado ahora</span>
                                </label>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;cursor:pointer">
                                    <input type="checkbox" id="cm-ex-img" data-ex="image_rights" value="1" style="margin-top:3px">
                                    <span>Autoriza el uso de su imagen en las comunicaciones de la academia.</span>
                                </label>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" data-ex-save><i class="bi bi-person-plus-fill me-1"></i>Guardar persona</button>
                                <p data-ex-error hidden></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formato de clase -->
                <div class="col-12">
                    <label class="form-label">Formato</label>
                    <div style="display:flex;gap:10px">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:2px solid var(--border);border-radius:8px;flex:1">
                            <input type="radio" name="cm-class-format" value="individual" checked style="accent-color:var(--accent)">
                            <div>
                                <div style="font-weight:700;font-size:13px">Individual</div>
                                <div style="font-size:11px;color:var(--text-muted)">1 alumno</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:2px solid var(--border);border-radius:8px;flex:1">
                            <input type="radio" name="cm-class-format" value="pareja" style="accent-color:var(--accent)">
                            <div>
                                <div style="font-weight:700;font-size:13px">Pareja</div>
                                <div style="font-size:11px;color:var(--text-muted)">2 alumnos</div>
                            </div>
                        </label>
                    </div>
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
                <!-- Configuración de la serie (TICKET-013): lo pinta series-builder.js -->
                <div class="col-12 cm-block-recurring d-none">
                    <div id="cm-series-builder"></div>
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

            </div>

            <!-- Aviso de bono (TICKET-013): solo informa, no bloquea -->
            <div id="cm-bono-cov" class="d-none" style="margin-top:12px"></div>

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

/* Alumno / Externo */
.cm-who { display:flex; gap:6px; }
.cm-who-tab {
    flex:1; padding:7px 10px; border:2px solid var(--border); background:var(--bg-card);
    border-radius:8px; font-size:12.5px; font-weight:600; color:var(--text-muted); cursor:pointer;
}
.cm-who-tab.active { border-color:var(--accent); background:var(--accent-light); color:var(--accent); }
.cm-ext-box { padding:12px; border:1px dashed var(--border); border-radius:8px; background:var(--bg-app); }

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

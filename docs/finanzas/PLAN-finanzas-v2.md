# Plan — Finanzas (Fase 0 «Nada se borra» + v2.0.0)

**Estado:** propuesta para aprobar · **Fecha:** 2026-10-10
**Base:** estudio de datos de prod anonimizados (10/10/2026) y decisiones del responsable.

---

## 1. Decisiones ya tomadas

| # | Decisión |
|---|----------|
| 1 | Control máximo: precio pagado, cobros, medio de pago, descuentos, gratuidades, plazos. |
| 2 | IVA y facturas: **sí, configurable y opcional** (apagado por defecto). |
| 3 | Sin coste por sesión de entrenador de momento, pero **gastos asignables** a entrenador, material, reparaciones o cualquier concepto. |
| 4 | Gastos generales desde el principio. |
| 5 | Acceso a Finanzas: **solo superadmin y admin**. |
| 6 | **Nada se borra.** Todo se puede añadir, corregir o anular, pero siempre queda registro. Va en una versión previa. |
| 7 | DUO: el precio es **por jugador**. |
| 8 | Falta sin justificar, o aviso con **menos de 24 h**, descuenta la sesión **automáticamente**. Coach, admin o superadmin siempre pueden revertirlo. |
| 9 | Saldo inicial de los bonos actuales: pagados al precio actual, marcados como **estimado**, corregibles. |
| 10 | Bono caducado con saldo: el dinero se da por ganado, pero el caso queda **marcado como "sin usar"**. |
| 11 | Clases sin descontar y sesiones sin cerrar: **se revisan siempre** y lo pendiente queda marcado. |

## 2. Principios técnicos

- **Libro inmutable.** Toda operación económica es un apunte. Corregir significa añadir un apunte de anulación o corrección. Nunca `UPDATE` ni `DELETE` sobre apuntes.
- **Precio congelado.** El bono guarda lo que costó al venderlo. Cambiar la tarifa no reescribe el pasado.
- **Importes en céntimos** (`INT`), sin decimales flotantes.
- **Auditoría universal.** Quién, cuándo, qué había antes, qué hay después y por qué.
- **Lógica en `app/Services`.** Los controllers se quedan finos y las rutas llevan `filter => ['auth', 'role:superadmin,admin']`.
- **Compatibles con MariaDB 11.8 (prod), MySQL 8 (local y Docker) y la copia `jp_prodlike`.** Las migraciones son idempotentes y llevan su SQL de prod en `docs/deploy/`.
- **Probado contra `jp_prodlike`.** Las cifras de la plataforma tienen que cuadrar con el estudio de prod (importes emitidos y pendientes, 354 sesiones de saldo vivo, 68 caducadas sin usar, 149 clases sin descontar). Los importes del estudio no se guardan en el repositorio.

---

## 3. Fase 0 — «Nada se borra» (versión previa a 2.0.0)

Objetivo: dejar de perder histórico **desde ya**, antes de construir Finanzas encima.

### 3.1 Problemas que corrige (verificados en código y en datos de prod)

| Dónde | Qué pasa hoy | Evidencia en prod |
|-------|--------------|-------------------|
| `BonosController::destroy` | Borra el bono en duro. | 2 descuentos apuntan a bonos que ya no existen. |
| `ConfiguracionService::deleteBonoType` | Borra el tipo de bono. | En local, la FK `CASCADE` borraría todos sus bonos. En prod no hay FK, así que quedan huérfanos. |
| `ConfiguracionService::deleteStaffUser` | Borra al usuario **y sus filas** en `class_session_coaches` y `class_session_players`. | Se perdería qué clases dio cada entrenador. |
| `ClasesService::deleteSession` / `deleteSeries` | Devuelve los bonos, pero borra la sesión y su lista aunque ya se hubiera impartido. | — |
| Quitar un alumno de una sesión | Devuelve el bono y borra la fila. | — |
| `BonosController::update` | Cambia saldo o caducidad sin guardar el valor anterior. | 31 caducidades y 52 saldos que no cuadran con los descuentos. |
| `player_bonos` | No guarda el precio. El informe usa el precio actual del tipo. | Todo importe es una estimación. |
| `spark purge:user` | Borra usuarios y todas sus filas relacionadas. | — |

### 3.2 Cambios

1. **Tabla `audit_log`** (solo se añaden filas): `entity_type`, `entity_id`, `action` (create, update, void, archive, restore, blocked), `before_json`, `after_json`, `reason`, `actor_id`, `actor_role`, `ip`, `created_at`. La escribe `AuditService::record()`. El código nunca actualiza ni borra filas de esta tabla.
2. **Bonos:** "Eliminar" pasa a ser **Anular**, con motivo obligatorio (`voided_at`, `voided_by`, `void_reason`).
   - Las sesiones que se habían descontado de ese bono pasan a "pendiente de saldar" y quedan marcadas.
   - Los listados ocultan los anulados, con un filtro para verlos.
3. **Editar un bono** (saldo, caducidad, tipo) exige motivo. Queda en `audit_log` y como apunte `adjusted` en `bono_movements`, con los valores antes y después.
4. **Precio congelado desde ya:** columnas `player_bonos.price_list_cents` (tarifa) y `price_cents` (precio final), más `price_estimated`.
   - Los bonos nuevos guardan su precio al crearse.
   - Los existentes se rellenan con el precio actual del tipo y quedan marcados como estimados (decisión 9).
5. **Tipos de bono:** no se pueden borrar, solo **archivar** (`archived_at`). Ya existe `active`; "archivado" lo saca también de Configuración.
6. **Usuarios del equipo:** "Eliminar" pasa a **Dar de baja** (estado inactivo).
   - Solo se permite el borrado real si el usuario no tiene ningún histórico (ni sesiones, ni bonos, ni movimientos, ni mensajes).
   - Si lo tiene, se bloquea y se ofrece la baja.
7. **Sesiones:**
   - Se pueden borrar solo si son futuras, siguen programadas y no tienen ningún bono descontado. Se guarda una foto de la sesión en `audit_log`.
   - Si ya se impartieron o tienen asistencia o descuentos, solo se pueden **cancelar** (como hoy, devolviendo los bonos).
   - Las series siguen la misma regla sesión a sesión.
8. **Quitar un alumno de una sesión pasada o con asistencia marcada:** bloqueado. Se marca su ausencia en su lugar.
9. **Sedes:** se archivan en vez de borrarse si alguna sesión las usa.
10. **`spark purge:user`:** se niega si el usuario tiene histórico económico, salvo con `--force`, que deja registro en auditoría.
11. **Finanzas › Revisión** (`/finanzas/revision`, antes "Pendiente de revisar") para admin (decisión 11):
    - Sesiones pasadas sin cerrar (hoy 173).
    - Clases dadas sin descontar (hoy 149: 132 anteriores al control del 06/10 y 17 posteriores).
    - Bonos con precio estimado.
    - Bonos caducados con sesiones sin usar.

    Cada elemento enlaza a su pantalla para resolverlo.
12. **Comando `spark finanzas:integridad`** (solo lectura): lista los datos huérfanos (descuentos sin bono, bonos de tipos inexistentes, saldos que no cuadran). Todavía **no se añaden FKs**, porque prod tiene datos huérfanos que las romperían. Se pondrán cuando el informe salga limpio.

**Migración de prod:** 1 tabla nueva y unas cuantas columnas nuevas (`ADD COLUMN`). No se borra nada. El SQL irá en `docs/deploy/migraciones_nada_se_borra.sql`.

---

## 4. v2.0.0 — Finanzas

### 4.1 Conceptos (cómo se mide el dinero)

Para cada alumno y para la academia se distinguen cuatro cifras. Así nada se mezcla:

| Concepto | Qué es | De dónde sale |
|----------|--------|---------------|
| **Facturado / cargado** | Lo que el alumno debe por los bonos o servicios vendidos. | Cargo al vender el bono, con su descuento. |
| **Cobrado (caja)** | El dinero que ha entrado de verdad. | Cobros registrados: efectivo, Bizum, transferencia, tarjeta, otro. |
| **Pendiente de cobro** | Facturado − cobrado. | Estado de cuenta del alumno. |
| **Devengado** | El servicio ya prestado: precio por sesión × sesiones consumidas (asistidas, faltas no justificadas y avisos tardíos). | Cada descuento de bono. |
| **Pendiente de servir** | Sesiones pagadas que aún no se han dado. Es una deuda de la academia con el alumno. | Saldo vivo de los bonos. |
| **Caducado sin usar** | Se da por ganado al caducar y queda marcado como "sin usar" (decisión 10). | Proceso diario de caducidades. |

### 4.2 Modelo de datos (nuevas tablas `fin_*`)

- **`fin_charges`** — cargos al alumno: `player_id`, `bono_id`, `concept`, `list_cents`, `discount_cents`, `discount_reason` (hermanos, promoción, cortesía…), `amount_cents`, `tax_rate`, `tax_cents`, `due_date`, `estimated`, `voided_*`.
- **`fin_payments`** — cobros: `player_id`, `amount_cents`, `method_id`, `paid_at`, `reference`, `note`, `estimated`, `voided_*`.
- **`fin_payment_allocations`** — qué cobro paga qué cargo. Permite pagos parciales, a plazos y un cobro que cubre varios bonos.
- **`fin_expenses`** — gastos: `category_id`, `amount_cents`, `tax_*`, `spent_at`, `method_id`, `supplier`, y opcionalmente `staff_id` (entrenador), `location_id` y `asset` (material). Admite adjunto (ticket o factura, guardado fuera del webroot como el resto de adjuntos) y `voided_*`.
- **`fin_ledger`** — libro único de solo añadir: cada cargo, cobro, gasto, devengo, caducidad y anulación genera aquí su apunte, con `kind`, `amount_cents`, `source_type`/`source_id`, `player_id`, `staff_id`, `entry_date` y `actor_id`. **De aquí salen todos los informes.**
- **`fin_categories`** — configurables, de ingreso y de gasto: Bonos, Sesión suelta, Material, Reparaciones, Entrenadores, Alquiler de campos, Suministros, Marketing, Otros…
- **`fin_payment_methods`** — configurables: efectivo, Bizum, transferencia, tarjeta, otro.
- **Fase IVA y facturas (opcional):** `fin_invoices`, `fin_invoice_lines` y `fin_invoice_counters` (numeración por serie y año, atómica como `ticket_counters`). Una factura emitida no se toca nunca: se corrige con una **factura rectificativa**.

> ⚠️ **Facturación legal:** emitir facturas oficiales desde un software propio está sujeto en España a **Veri\*factu** (registro encadenado y no alterable). La propuesta es que la plataforma emita **recibos** desde el primer día y que las facturas oficiales vayan detrás de un interruptor, que solo se activa cuando el gestor de la academia lo confirme. El diseño de numeración inmutable y apuntes encadenados ya va en esa dirección. Las fechas y requisitos exactos hay que confirmarlos con el gestor.

### 4.3 Reglas automáticas

- **Al vender un bono:** se crea el cargo con el precio congelado y el descuento. Opcionalmente, "cobrado ahora" con su medio de pago, en el mismo formulario.
- **Asistencia** (decisión 8), al pasar lista:
  - *No justificada* → descuento automático.
  - *Avisó ausencia* con menos de **N horas** (por defecto 24, configurable) o sin hora de aviso → descuento automático, marcado como **aviso tardío**.
  - Avisó con N horas o más, o ausencia justificada → no se descuenta.
  - **Qué bono se usa:** el que caduca antes, entre los que tienen saldo. Se puede cambiar después, como hoy.
  - Si el alumno no tiene saldo → queda como clase sin bono y aparece en Finanzas › Revisión.
  - **Siempre reversible** por coach (en sus sesiones), admin o superadmin, con motivo, y queda en auditoría.
- **Devengo:** cada descuento genera su apunte (precio congelado ÷ sesiones). Cada devolución genera el apunte contrario.
- **Caducidades:** el proceso diario reconoce como "caducado sin usar" el saldo de los bonos vencidos. Hoy se lanza al abrir el dashboard; se propone un **cron real** en hPanel (`php spark finanzas:diario`).

### 4.4 Saldo inicial (migración de datos)

Para los 217 bonos existentes:
- Se crea un cargo con el precio actual del tipo y un cobro "estimado", ambos marcados como estimados (decisión 9).
- Admin los confirma o corrige uno a uno, anulando y rehaciendo.

Además:
- Las 149 clases sin descontar entran en Finanzas › Revisión (decisión 11).
- El devengo histórico se reconstruye a partir de los descuentos existentes. Lo que no cuadra (52 bonos) se marca como **"consumo sin detalle"**, no se inventa.

### 4.5 Pantallas (`/finanzas`, solo superadmin y admin)

1. **Resumen:** cobrado, devengado, pendiente de cobro, pendiente de servir, gastos y resultado del mes. Evolución de 12 meses. Alertas.
2. **Libro de movimientos:** filtros por fecha, tipo, categoría, alumno, entrenador y medio de pago. Exportación CSV.
3. **Cobros:** registro rápido, también desde la ficha del alumno y desde el bono.
4. **Gastos:** alta con categoría, asignación opcional a entrenador, material o sede, y adjunto.
5. **Estado de cuenta del alumno:** cargos, cobros, sesiones consumidas y saldo. Recibo imprimible.
6. **Entrenadores:** sesiones impartidas (desde `class_session_coaches`) y gastos asignados.
7. **Análisis:**
   - Precio medio real por sesión.
   - Ausencias justificadas, no justificadas y tardías, y su impacto en €.
   - Caducados sin usar.
   - Desglose por tipo de bono, categoría de edad, formato (individual o DUO) y sede.
8. **Revisión** (heredada de la Fase 0, ampliado con cobros pendientes).
9. **Configuración:** categorías, medios de pago, horas de aviso, IVA y facturación (interruptor), series de numeración.

### 4.6 Entregas

| Entrega | Contenido |
|---------|-----------|
| **Fase 0** | Nada se borra, auditoría, precio congelado, sección Finanzas con la pestaña Revisión, informe de integridad. |
| **2.0 · A** | Modelo `fin_*` + libro, cargos, cobros, estado de cuenta, saldo inicial, reglas automáticas de asistencia. |
| **2.0 · B** | Gastos, categorías, adjuntos, asignación a entrenador, material o sede. |
| **2.0 · C** | Resumen, análisis, exportaciones y cron diario. |
| **2.0 · D** | IVA y facturas o recibos (opcional, con interruptor). |
| **2.0 · PWA** | Integrar la rama `feat/pwa-notificaciones-push` (ya avanzada). Avisos push de cobros pendientes y saldo bajo. |

Recorrido de cada entrega:
1. Rama desde `main`.
2. PR.
3. PPR (que ahora es igual que prod gracias a la copia anonimizada).
4. Prod, con su SQL en `docs/deploy/`, copia de seguridad antes y la rama `prod` avanzada después.

### 4.7 Pruebas

- **Unitarias puras:** cálculo de devengo, regla de 24 h, elección de bono, saldos, redondeos en céntimos y numeración.
- **De base de datos** contra MySQL en Docker, con el esquema manual, como ya se hace.
- **De aceptación sobre `jp_prodlike`:** las cifras de la plataforma tienen que coincidir con las del estudio de prod.

---

## 5. Pendiente de confirmar

1. **Situación fiscal de la academia:** ¿IVA 21 %, exento, otro? Lo confirma el gestor. Mientras tanto, IVA apagado.
2. **¿Facturas oficiales desde la plataforma, o basta con recibos?** Depende de Veri\*factu y del gestor.
3. **Versión de la Fase 0:** propuesta **1.33.0** (minor).

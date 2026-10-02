# TICKET-013 — Las clases recurrentes se generan sin controlar el saldo del bono

| Campo        | Valor                                          |
|--------------|------------------------------------------------|
| Categoría    | `bug` — Lógica de negocio                      |
| Prioridad    | `alta`                                         |
| Estado       | `en desarrollo` (solo PPR, sin tocar PROD)     |
| Rama         | `fix/recurrentes-saldo-bono`                   |
| Módulo       | Clases (recurrentes) · Bonos                   |
| Detectado en | Producción (visto en directo 30/09) · reproducido en PPR |
| Ejemplo      | Alumno "Aaron Alonso"                          |

## Descripción

Se asignó a un alumno un bono de **2 sesiones**. Acto seguido se creó una clase
recurrente de un mes (inicio–fin), y el sistema generó **4–5 sesiones** para ese
alumno, muy por encima de las 2 que cubre el bono.

Resultado: el calendario muestra más clases de las pagadas y el alumno acabará
asistiendo a clases sin bono (o el bono se agotará a mitad de mes sin aviso).

## Pasos para reproducir

1. Asignar a un alumno un bono de 2 sesiones.
2. Crear una clase recurrente (p. ej. 1 día/semana durante un mes) con ese alumno.
3. Se crean todas las sesiones del rango (4–5) sin ningún aviso ni límite.

## Causa probable

`ClasesService::createRecurring()` ([ClasesService.php:208](../../app/Services/ClasesService.php))
recorre todos los días entre `recurrence_start` y `recurrence_end` y llama a
`insertSingle()` + `syncPlayers()` por cada coincidencia, **sin consultar en ningún
momento el saldo de sesiones del bono del alumno**. El bono solo se descuenta
después, al pasar lista, así que la creación nunca lo tiene en cuenta.

## Comportamiento esperado

- Al crear una recurrente con alumnos, comprobar el saldo de bono disponible de
  cada uno **antes** de generar las sesiones.
- Si las sesiones a generar superan el saldo: avisar (cuántas cubre el bono vs.
  cuántas se piden) y permitir elegir entre limitar la serie al saldo o continuar
  de forma explícita (decisión a confirmar con negocio, ver abajo).
- Mismo control en `quickCreate()` y en cualquier otra vía que genere recurrentes
  (incluida la renovación de series).

## Preguntas abiertas (negocio)

- ¿Bloquear, limitar automáticamente al saldo, o solo avisar y dejar al admin decidir?
- ¿Se cuentan las sesiones ya programadas pendientes del alumno, o solo el saldo actual?
- ¿Qué pasa con alumnos sin bono (clases sueltas/pago aparte)?

## Criterios de aceptación


## Escala (datos de prod, 02/10/2026)

Al menos 25 de 155 alumnos tenían más clases futuras programadas que saldo de bono
(≈161 sesiones sin cobertura). De 8 casos muestreados: 6 con bono agotado/caducado
y 2 que nunca tuvieron bono. La renovación mensual de series copiaba los alumnos
sin mirar el saldo, así que el desajuste crecía cada mes.

## Solución (decidida con negocio)

- **Cobertura al crear/continuar una serie** (`BonoCoverageService`): proyección por
  alumno y fecha (bonos en cola, clases ya programadas, deudas, caducidad). Panel con
  chips *cubierta / caduca antes / pendiente de bono*. Por defecto **limita al saldo**;
  solo admin/superadmin pueden **crear todas** (pendientes de bono) y se avisa al
  alumno y a los administradores. Staff/coach quedan limitados al saldo.
- **Libro de movimientos** (`bono_movements`, solo se añade): alta, asignación, descuento,
  devolución, ajuste, ampliación, deuda saldada/resuelta y aviso de caducidad.
- **Deuda de sesión** (derivada): clase dada, asistencia que consume bono, sin descuento.
  Se **salda sola** al emitir/asignar un bono (más antigua primero) avisando a los admins.
  Resolución manual: *pagada fuera de bono* o *condonada*, siempre registrada. Pantalla
  `/bonos/deudas`.
- **Caducidad laxa**: aviso a 7 días (sin cron: se dispara desde el Dashboard, máx. 1/h,
  un aviso por bono) y **ampliación** de +15 / +30 / +60 días o fecha personalizada.
- **Punto de control** (`bono_control_since`): lo anterior no se toca ni se convierte en
  deuda; se muestra como "no reflejado".
- Marca `bono_coverage` por plaza (también al añadir alumnos a una sesión suelta) y
  etiquetas en la ficha de la clase y en Pasar lista.

## Despliegue

`docs/deploy/migraciones_bono_cobertura.sql` (o `php spark migrate`). **No ejecutar en PROD
hasta decidirlo**: la fecha de ejecución fija el punto de control. Datos de prueba en PPR:
`php spark db:seed BonoCoverageSeeder` (alumnos ficticios; no toca alumnos reales).

## Criterios de aceptación

- [x] Bono de 2 sesiones + recurrente de un mes ⇒ no se generan más de las permitidas sin decisión explícita.
- [x] El aviso indica saldo vs. sesiones solicitadas, por alumno.
- [x] Tests: `BonoCoverageServiceTest`, `BonoControlServiceTest` + escenarios verificados contra BD.
- [ ] Verificación visual en PPR (paneles, ampliar, deudas).

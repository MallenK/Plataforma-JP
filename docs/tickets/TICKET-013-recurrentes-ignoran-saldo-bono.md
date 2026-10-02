# TICKET-013 — Las clases recurrentes se generan sin controlar el saldo del bono

| Campo        | Valor                                          |
|--------------|------------------------------------------------|
| Categoría    | `bug` — Lógica de negocio                      |
| Prioridad    | `alta`                                         |
| Estado       | `abierto`                                      |
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

- [ ] Bono de 2 sesiones + recurrente de un mes ⇒ no se generan más de las permitidas sin confirmación explícita.
- [ ] El aviso indica claramente saldo vs. sesiones solicitadas, por alumno.
- [ ] Test unitario/integración del cálculo (saldo, sesiones previas ya programadas, sin bono).

# TICKET-001 — Los admin y el staff no pueden impartir clases

| Campo        | Valor                                   |
|--------------|-----------------------------------------|
| Categoría    | `bug` — Error / Bug                     |
| Prioridad    | `alta`                                  |
| Estado       | `abierto`                               |
| Módulo       | Clases / Calendario                     |
| Rama         | `fix/clases-asignar-admin-staff`        |
| Detectado en | Producción (revisión funcional)         |

## Descripción

Al crear o editar una sesión de clase, el selector de **responsable**
("Entrenadores" / "Staff responsable") solo ofrece usuarios con rol
`coach` o `staff` respectivamente. Un usuario con rol **`admin`** o
**`superadmin`** nunca aparece en la lista, por lo que **no se le puede
asignar como responsable de una clase** aunque en la práctica los
administradores también imparten sesiones.

## Reproducción

1. Iniciar sesión como `admin` o `superadmin`.
2. Ir a *Clases → Nueva clase* (o editar una existente).
3. En "Tipo de responsable" elegir "Entrenador".
4. Abrir el desplegable de responsable.
5. **Resultado:** solo se listan `coach`. El admin actual no está.
6. **Esperado:** los `admin` y `superadmin` también deben poder
   seleccionarse como responsable, igual que el `staff`.

## Causa raíz

`app/Services/ClasesService.php`:

- `getCoachOptions()` → `where('role', 'coach')`
- `getStaffOptions()` → `where('role', 'staff')`

Ambas consultas filtran por un único rol y excluyen a `admin` /
`superadmin`.

## Solución aplicada

- Nuevas constantes `ClasesService::RESPONSABLE_TECNICO_ROLES`
  (`coach`, `admin`, `superadmin`) y `RESPONSABLE_STAFF_ROLES`
  (`staff`, `admin`, `superadmin`).
- `getCoachOptions()` / `getStaffOptions()` pasan a usar
  `whereIn('role', …)` con esas constantes.
- El resto del flujo ya soportaba responsables no-coach
  (`isAssignedOrAdmin()`, rutas `clases/*` con
  `role:superadmin,admin,staff,coach`, vista `clases/create.php`
  y `clase-modal.js`), por lo que no requiere más cambios.

## Verificación

- `tests/unit/ClasesServiceResponsablesTest.php`
- Manual: crear clase como admin, asignarse como responsable, confirmar
  que la sesión aparece y es gestionable.

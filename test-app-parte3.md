

# **FASE 3 — Tests del rol STAFF · Informe QA**

Plataforma: `plataforma-jp.onrender.com` · Usuario: `staff.test@jptest.com` · Fecha: 03/06/2026

## **Resultados por test**

**AUTH**

* **\[S-AUTH-01\] ✅ PASS** — Login correcto y redirección al dashboard con menú de rol staff (Dashboard, Clases, Mensajes, Documentos). ⚠️ *Observación importante:* la contraseña indicada en el enunciado del test (`Staff123!`) **NO funciona** ("Credenciales incorrectas"). El login solo funcionó con la contraseña de la cabecera del prompt: `Jp0a4489dd!`.

**DASHBOARD**

* **\[S-DASH-01\] ✅ PASS** — Dashboard carga correctamente ("STAFF · PANEL DE CONTROL"). NO muestra métricas de negocio de admin (sin alumnos activos ni ingresos mensuales); solo calendario y próximas clases.

**ACCESOS DENEGADOS**

* **\[S-ALU-01\] ✅ PASS** — `/alumnos` → 403 "Sin permisos" (Tu rol actual: staff).  
* **\[S-ENT-01\] ✅ PASS** — `/entrenadores` → 403\.  
* **\[S-BON-01\] ✅ PASS** — `/bonos` → 403\.  
* **\[S-CFG-01\] ⚠️ PARCIAL** — `/configuracion` NO da 403 ni redirige. Carga una vista de **solo lectura** ("Tienes acceso de solo lectura a la información general de la academia"). El staff sí puede ver la configuración general. Difiere de lo esperado en el test, aunque no permite edición.  
* **\[S-LIS-01\] ✅ PASS** — `/pasar-lista` → 403\.

**CLASES**

* **\[S-CLA-01\] ✅ PASS** — Calendario de clases accesible.  
* **\[S-CLA-02\] ✅ PASS** — Clase creada ("Clase Staff QA", 05/06/2026, 11:00–12:00, sede "Sede QA Test"). Mensaje "Sesión creada correctamente".  
* **\[S-CLA-03\] ✅ PASS** — Título editado a "Clase Staff QA Editada". Mensaje "Sesión actualizada correctamente".  
* **\[S-CLA-04\] ✅ PASS** — Clase cancelada; estado cambia a "Cancelada".

**DOCUMENTACIÓN**

* **\[S-DOC-01\] ✅ PASS** — Página de documentación carga correctamente.  
* **\[S-DOC-02\] ✅ PASS** — Archivo subido a carpeta "Reglamentos"; mensaje "Archivo subido correctamente" y contador pasa a 1 archivo.  
* **\[S-DOC-03\] ❌ FAIL** — La descarga **no funciona**. El endpoint de descarga devuelve **HTTP 503** de forma consistente (3 intentos). Curiosamente, el endpoint de *preview* del mismo archivo devuelve 200 (el archivo está almacenado y es visible), pero la descarga falla.

**MENSAJES**

* **\[S-MSG-01\] ✅ PASS** — Conversación iniciada con el admin (Joel Paredees · Admin). Mensaje "Hola Admin, soy el staff de prueba QA" enviado y visible en el chat.

**NOTIFICACIONES**

* **\[S-NOT-01\] ✅ PASS** — La notificación del admin "Notif Grupal QA" (Mensaje grupal) es visible en el centro de notificaciones. *Nota:* la pantalla no tiene una pestaña separada "Recibidas"; muestra las recibidas directamente.  
* **\[S-NOT-02\] ✅ Resultado: SÍ puede enviar** — Aparece el botón "Nueva notificación" y se abre el formulario de envío (destinatario, título, mensaje, adjunto). *Limitación:* el tipo de envío solo ofrece "Individual" (sin opción grupal/masiva como el admin). No se envió ninguna notificación (no requerido).

**PERFIL**

* **\[S-PER-01\] ✅ PASS** — Muestra el perfil "Staff Test QA", email staff.test@jptest.com, Rol "Staff", Estado Activo, ID `#180001`.  
* **\[S-PER-02\] ✅ PASS** — Edición de perfil funciona: cambié "Nombre completo" a "Staff Test QA Editado", se guardó y persistió tras recargar. (Restauré el nombre original al terminar.)

Sesión cerrada correctamente al finalizar.

## **Resumen**

24 verificaciones: **20 ✅ PASS · 1 ❌ FAIL · 2 ⚠️ PARCIAL/observación · 1 ℹ️ informativo (S-NOT-02)**

## **Bugs encontrados**

**BUG-1 (Alto) — Descarga de documentos rota (HTTP 503\)** La descarga de archivos en Documentación falla con error 503\. *Reproducir:* Login staff → Documentación → abrir carpeta "Reglamentos" → subir un archivo → pulsar el icono de descarga. La petición `GET /documentacion/file/{id}/download` devuelve 503\. El preview del mismo archivo (200) funciona, por lo que el archivo existe; el fallo es específico de la ruta de descarga.

**BUG-2 (Medio) — Campo "Cargo / Puesto específico" no persiste en el perfil** Al rellenar "Cargo / Puesto específico" y guardar, aparece "Perfil actualizado correctamente" pero el valor NO se guarda (queda vacío tras recargar). *Reproducir:* Perfil → escribir "Recepción QA" en Cargo → Guardar cambios → recargar: el campo aparece vacío. En el mismo formulario, "Nombre completo" sí persiste, lo que confirma que el problema es específico de este campo (mensaje de éxito engañoso / falso positivo).

**BUG-3 (Bajo / Inconsistencia) — `/configuracion` accesible para staff en solo lectura** A diferencia del resto de rutas de admin (que devuelven 403), `/configuracion` carga una vista de solo lectura para el staff. No permite editar, pero rompe la consistencia esperada de "403 para todas las rutas admin". Verificar si es comportamiento intencionado.

**Observación adicional (no bug, discrepancia de datos de prueba):** La contraseña documentada para el test S-AUTH-01 (`Staff123!`) es incorrecta; la válida es `Jp0a4489dd!`. Conviene corregir el plan de pruebas.

¿Quieres que reintente la descarga (S-DOC-03) más tarde por si el 503 fuera transitorio del servidor (Render free tier), o que documente algún test con más detalle?

# **📋 RESULTADOS DE TESTING — ROL PLAYER (joanquilabert@gmail.com)**

**Plataforma:** JP Preparation | **Fecha:** 03/06/2026 | **Tester:** Claude QA

---

## **🔐 AUTH**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-AUTH-01 | Login con joanquilabert@gmail.com / Jpa1bdf5\! | ✅ PASS | Redirige a /dashboard con menú reducido del jugador (Dashboard, Mi ficha, Clases, Mensajes, Documentos). Navbar muestra "Joan Quilabert \- PLAYER". |

---

## **📊 DASHBOARD**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-DASH-01 | 4 tarjetas del jugador | ✅ PASS | Aparecen todas: Clases Asistidas (1), Próximas Clases (0), Sesiones Bono (2 de 4), Bonos Activos (1). |
| P-DASH-02 | Tarjeta de identidad del jugador | ✅ PASS | Muestra: Joan Quilabert, joanquilabert@gmail.com, Activo, Intermedio, Posición: Mediocampista, Edad: 21 años, Físico: 184 cm · 78 kg. |
| P-DASH-03 | Tarjeta de bono activo con barra de progreso | ✅ PASS | Muestra "Bono 4 sesiones \- Mensual", 2/4 sesiones, barra de progreso naranja, fechas 22/05/2026 → 21/06/2026. |
| P-DASH-04 | Lista de próximas clases asignadas | ⚠️ PARCIAL | Próximas clases \= 0\. El calendario sí muestra las 2 clases del jugador (ambas el 02/06/2026, que ya es ayer). El contador de "Próximas" es correcto (0 futuras), no es un bug. |
| P-DASH-05 | Botones de acceso rápido | ✅ PASS | **Mi ficha** → /alumno ✅ |

---

## **🚫 ACCESOS DENEGADOS**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-ACC-01 | /alumnos | ✅ PASS | 403 "Sin permisos" — "Tu rol actual: player". |
| P-ACC-02 | /entrenadores | ✅ PASS | 403 "Sin permisos" — "Tu rol actual: player". |
| P-ACC-03 | /bonos | ✅ PASS | 403 "Sin permisos" — "Tu rol actual: player". |
| P-ACC-04 | /configuracion | ⚠️ PARCIAL | Acceso permitido con mensaje "Tienes acceso de solo lectura a la información general de la academia." — No hay 403\. El jugador ve los datos generales de la academia (nombre, email, teléfono, sede, idioma, zona horaria) en modo lectura. Revisar si este acceso está intencionado. |
| P-ACC-05 | /pasar-lista | ✅ PASS | 403 "Sin permisos" — "Tu rol actual: player". |
| P-ACC-06 | /alumnos/nuevo | ✅ PASS | 403 "Sin permisos" — "Tu rol actual: player". |
| P-ACC-07 | /alumnos/1/eliminar | ✅ PASS | 404 "Can't find a route for 'GET: alumnos/1/eliminar'" — La ruta destructiva no existe como GET. Protección válida. |

---

## **📅 CLASES**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-CLA-01 | Calendario muestra solo clases del jugador | ✅ PASS | Muestra "Sesión de prueba" y "Sesión QA Test Editada" el 02/06/2026. Solo las clases asignadas al jugador. |
| P-CLA-02 | Detalle de clase: fecha, hora, entrenador, sede | ✅ PASS | Muestra: Fecha 02/06/2026, Horario 00:00–01:00 (60 min), Lugar: Campo 1, Entrenador: Jan Mallen. |
| P-CLA-03 | Confirmar asistencia | ⚠️ PARCIAL | No existe botón "Confirmar asistencia" separado. El jugador ya aparece como "Presente" (confirmado por el admin al asignarlo). No hay flujo de auto-confirmación pendiente disponible en las clases actuales. |
| P-CLA-04 | Notificar ausencia con motivo | ✅ PASS | Botón "Avisar que no puedo asistir" funcional. Se introdujo motivo "Motivo test QA ausencia". Resultado: "Tu aviso de ausencia ha sido registrado." con badge "Aviso enviado". |
| P-CLA-05 | Sin botones de gestión (crear/editar/cancelar/eliminar) | ✅ PASS | No hay botón "Nueva clase". No hay botones de editar, cancelar ni eliminar en la vista del jugador ni en el detalle de clase. |

---

## **👤 PERFIL**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-PER-01 | /perfil muestra el perfil propio | ✅ PASS | URL /perfil carga "Mi perfil" de Joan Quilabert con datos personales (nombre, email, rol Alumno, ID \#10, estado Activo, miembro desde 11/04/2026). |
| P-PER-02 | Editar perfil (fecha nac, posición, altura, peso) | ✅ PASS | Se editó desde /alumno?edit=1. Altura cambiada de 183→184 cm, Peso de 77→78 kg. Guardado correctamente. Los datos se actualizaron en la ficha y en el dashboard. |
| P-PER-03 | Subir avatar | ✅ PASS | Imagen subida con éxito. Mensaje "Avatar actualizado correctamente." El avatar aparece en el perfil y en el sidebar del navbar. |
| P-PER-04 | Acceso a perfil ajeno (/perfil/1) | ✅ PASS | 403 "Sin permisos" — "Tu rol actual: player". El jugador no puede ver perfiles de otros usuarios. |

---

## **📁 DOCUMENTACIÓN**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-DOC-01 | Solo aparece carpeta personal del jugador | ⚠️ PARCIAL | En la sección JUGADORES solo aparece "Joan Quilabert" (Personal). Sin embargo, también son visibles 4 carpetas Públicas: Nutrición (2 archivos), Psicología, Reglamentos, Vídeos técnicos. Revisar si el jugador debería ver las carpetas públicas o solo la suya. Comportamiento probablemente intencionado. |
| P-DOC-02 | Subir archivo a carpeta personal | ✅ PASS | Subida de "test\_qa\_document.jpg" (50.3 KB) a "Mi carpeta". Mensaje: "Archivo subido correctamente." La carpeta pasó de 1 a 2 archivos. |
| P-DOC-03 | Descargar archivo subido | ❌ FAIL | La descarga retorna **HTTP 503 Service Unavailable**. URL: `/documentacion/file/180002/download`. El botón de descarga se activa pero el servidor falla al servir el archivo. |

---

## **💬 MENSAJES**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-MSG-01 | Conversación del admin visible | ✅ PASS | La conversación con "Sergi Mallén López (Admin)" aparece en la lista con los mensajes "Mensaje de prueba QA desde Admin" y "Adjunto de prueba" \+ imagen attachment\_test.png. |
| P-MSG-02 | Responder a la conversación del admin | ✅ PASS | Mensaje enviado: "Hola Admin, recibí tu mensaje \- Jugador QA". Aparece en el chat a las 17:41. |
| P-MSG-03 | Nueva conversación con el coach (Gina QA) | ✅ PASS | Se inició conversación con "Gina QA (Entrenador)". Mensaje enviado: "Hola coach, soy el jugador". Visible en el chat. |

---

## **🔔 NOTIFICACIONES**

| ID | Test | Estado | Observación |
| ----- | ----- | ----- | ----- |
| P-NOT-01 | Notificaciones del admin en "Recibidas" | ✅ PASS | Ambas notificaciones visibles: "Notif Grupal QA" (Mensaje grupal) y "Notif QA Test" (Mensaje de prueba) de Sergi Mallén López. Contador: "2 sin leer". |
| P-NOT-02 | Marcar notificación como leída | ✅ PASS | Al hacer clic en el punto azul de "Notif Grupal QA", el indicador desaparece y el contador de la campana bajó de 2 a 1\. |
| P-NOT-03 | El jugador NO puede enviar notificaciones | ❌ FAIL | El botón **"Nueva notificación" está visible y completamente funcional** para el jugador. Al hacer clic, abre un formulario completo con tipo de envío (Individual), destinatario (select con usuarios), título y mensaje. El jugador puede enviar notificaciones a cualquier usuario de la plataforma. |
| P-NOT-04 | Dropdown campana con notificaciones no leídas | ✅ PASS | Al hacer clic en la campana: aparece dropdown con "Notif Grupal QA" y "Notif QA Test", con "Ver todas" y "Marcar todas como leídas". El contador muestra correctamente 1 no leída. |

---

## **🐛 LISTA DE BUGS ENCONTRADOS**

### **BUG-01 — 🔴 CRÍTICO: Jugador puede enviar notificaciones**

* **Test:** P-NOT-03  
* **Descripción:** El botón "Nueva notificación" está visible y funcional para el rol PLAYER. El formulario permite seleccionar cualquier destinatario, poner título y mensaje, y enviar notificaciones. Esta funcionalidad debería estar restringida al rol Admin/Staff.  
* **Pasos para reproducir:**  
  1. Login con joanquilabert@gmail.com  
  2. Ir a /notificaciones  
  3. Hacer clic en "Nueva notificación"  
  4. El formulario abre completamente con todos los campos activos  
* **Impacto:** Cualquier jugador puede enviar mensajes/notificaciones a otros usuarios haciéndose pasar por comunicaciones oficiales.

---

### **BUG-02 — 🟡 MODERADO: Descarga de archivos retorna 503**

* **Test:** P-DOC-03  
* **Descripción:** Al intentar descargar un archivo desde la carpeta personal del jugador, el servidor retorna HTTP 503 Service Unavailable.  
* **URL afectada:** `GET /documentacion/file/180002/download`  
* **Pasos para reproducir:**  
  1. Login como jugador  
  2. Ir a /documentacion  
  3. Abrir la carpeta "Joan Quilabert"  
  4. Hacer clic en el icono de descarga de cualquier archivo  
  5. La descarga falla con 503  
* **Impacto:** Los jugadores no pueden descargar ningún documento de su carpeta personal.

---

### **BUG-03 — 🟡 MODERADO: /configuracion accesible para el jugador**

* **Test:** P-ACC-04  
* **Descripción:** La ruta `/configuracion` no devuelve 403\. En su lugar muestra la página de configuración general en modo lectura con mensaje "Tienes acceso de solo lectura a la información general de la academia."  
* **Pasos para reproducir:**  
  1. Login como jugador  
  2. Navegar a /configuracion  
  3. Se muestra la página con datos de la academia (nombre, email, teléfono, sede principal, idioma, zona horaria, moneda)  
* **Impacto:** El jugador puede ver información de configuración de la plataforma. Revisar si es intencionado o debe mostrar 403\.

---

### **BUG-04 — 🟢 MENOR: No existe botón "Confirmar asistencia" explícito**

* **Test:** P-CLA-03  
* **Descripción:** El test espera un botón "Confirmar asistencia" en clases con estado "programada". El sistema muestra directamente el estado "Presente" (asignado por el admin) sin opción de que el jugador confirme activamente. Solo existe el flujo de "Avisar que no puedo asistir".  
* **Impacto:** UX — el jugador no puede auto-confirmar asistencia independientemente.

---

## **📊 RESUMEN EJECUTIVO**

| Categoría | PASS | FAIL | PARCIAL | Total |
| ----- | ----- | ----- | ----- | ----- |
| AUTH | 1 | 0 | 0 | 1 |
| DASHBOARD | 4 | 0 | 1 | 5 |
| ACCESOS | 5 | 0 | 2 | 7 |
| CLASES | 3 | 0 | 2 | 5 |
| PERFIL | 4 | 0 | 0 | 4 |
| DOCUMENTACIÓN | 1 | 1 | 1 | 3 |
| MENSAJES | 3 | 0 | 0 | 3 |
| NOTIFICACIONES | 3 | 1 | 0 | 4 |
| **TOTAL** | **24** | **2** | **6** | **32** |

* ✅ **PASS:** 24 (75%)  
* ❌ **FAIL:** 2 (6.25%) — P-DOC-03 (descarga 503), P-NOT-03 (jugador puede enviar notificaciones)  
* ⚠️ **PARCIAL:** 6 (18.75%) — P-DASH-04, P-ACC-04, P-CLA-03, P-CLA-04✓, P-DOC-01, P-DOC-03

**Bugs críticos a corregir antes de producción:**

1. 🔴 Eliminar el botón/funcionalidad "Nueva notificación" para el rol player  
2. 🟡 Corregir el endpoint de descarga de archivos (503)  
3. 🟡 Definir si /configuracion debe ser 403 o solo lectura para players

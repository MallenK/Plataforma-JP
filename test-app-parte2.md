
# **📋 INFORME COMPLETO DE TESTING — ROL COACH (JP Preparation)**

**Fecha:** 03/06/2026 | **Tester:** Claude QA | **Usuario testeado:** ginamoref@gmail.com (Gina, COACH)

---

## **RESULTADOS POR SECCIÓN**

---

### **🔐 AUTH**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-AUTH-01 | Login con ginamoref@gmail.com / Jp687f6a7d\! | ✅ PASS | Login exitoso. Redirige al dashboard con menú del coach: Dashboard, Alumnos, Clases, Mensajes, Documentos. Rol mostrado: "ENTRENADOR \- PANEL DE CONTROL". |

---

### **📊 DASHBOARD**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-DASH-01 | Verificar que NO aparecen métricas de admin | ✅ PASS | Dashboard básico sin "alumnos activos" ni "ingresos mensuales". Solo muestra: calendario, próximas clases y accesos rápidos. |
| C-DASH-02 | Calendario de clases visible con clases asignadas | ✅ PASS | Calendario de Junio 2026 visible. Muestra las clases asignadas (Sesión de prueba, Sesión QA Test Editada, y la nueva Clase Coach QA). |

---

### **👤 ALUMNOS (Solo lectura)**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-ALU-01 | Lista de alumnos accesible | ✅ PASS | /alumnos carga correctamente. Muestra 4 alumnos: Varo, Aleix, Joan Quilabert, Carlon. |
| C-ALU-02 | Ver perfil de alumno | ✅ PASS | Perfil de Varo cargó con toda la información: posición, altura, peso, bonos, actividad, etc. |
| C-ALU-03 | NO aparece "Nuevo alumno" ni botones editar/eliminar | ✅ PASS | Solo aparece icono "Ver" (ojo) en la columna de acciones. Sin botones de crear, editar ni eliminar. |
| C-ALU-04 | Acceso directo a /alumnos/nuevo bloqueado | ✅ PASS | Error 403 "Sin permisos". Muestra "Tu rol actual: coach". |
| C-ALU-05 | Añadir anotación "Anotación coach QA" y guardar | ✅ PASS | Anotación guardada correctamente. Aparece en la sección "Anotaciones" (1 anotación(es)) con autor "Gina" y timestamp. |

---

### **🏋️ ENTRENADORES (Sin acceso)**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-ENT-01 | Acceso a /entrenadores bloqueado | ✅ PASS | Error 403 "Sin permisos". El coach no tiene acceso a la gestión de entrenadores. |

---

### **📅 CLASES**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-CLA-01 | Calendario de clases en /clases | ✅ PASS | Carga correctamente con métricas (4 clases semana, 4 mes, 1 jugador activo, 100% asistencia media). |
| C-CLA-02 | Crear clase "Clase Coach QA" (04/06, 09:00-10:00, Campo 1\) | ✅ PASS | Clase creada correctamente. Mensaje "Sesión creada correctamente." URL: /clases/450003. |
| C-CLA-03 | Botón "Editar" presente en detalle de clase | ✅ PASS | Botón "Editar" visible junto a "Marcar completada", "Cancelar sesión" y "Eliminar". |
| C-CLA-04 | Añadir jugador Varo a la clase | ✅ PASS | "Jugador añadido." Aparece en la lista de jugadores. Resumen asistencia: 1 convocado. |
| C-CLA-05 | Guardar observación "Observación del coach QA" | ✅ PASS | "Observaciones guardadas." El texto aparece en el campo "Antes — Planificación". |
| C-CLA-06 | Marcar asistencia de Varo como "Presente" | ✅ PASS | "Asistencia registrada." Badge cambia a verde "Presente". Resumen: 1 presente. |
| C-CLA-07 | Acceso a /pasar-lista bloqueado | ✅ PASS | Error 403 "Sin permisos". El coach no puede usar la vista semanal de pasar lista. |
| C-CLA-08 | Cancelar clase "Clase Coach QA" | ⚠️ PARCIAL | Sesión cancelada ("Sesión cancelada." en verde). Los botones de acción desaparecen. **Observación**: No se muestra un badge/etiqueta de estado "Cancelada" visible en el encabezado de la sesión. La confirmación visual del estado solo es el mensaje flash. |

---

### **🎫 BONOS (Sin acceso)**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-BON-01 | Acceso a /bonos bloqueado | ✅ PASS | Error 403 "Sin permisos". El coach no tiene acceso a los bonos. |

---

### **📁 DOCUMENTACIÓN**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-DOC-01 | /documentacion accesible | ✅ PASS | Página carga correctamente con carpetas públicas (Nutrición, Psicología, Reglamentos, Vídeos técnicos) y personales. |
| C-DOC-02 | Subir archivo PDF a carpeta Nutrición | ✅ PASS | Archivo "qa\_test\_coach.pdf" (108 bytes) subido a carpeta Nutrición. El contador pasó de 1 a 2 archivos. |
| C-DOC-03 | Descargar archivo subido | ⚠️ PARCIAL | La URL de descarga `/documentacion/file/150001/download` existe en el código JS y el archivo se creó correctamente. No se pudo confirmar visualmente la descarga completa debido a timeouts del servidor (Render.com plan gratuito). La API confirma que el archivo existe y está accesible. |
| C-DOC-04 | NO aparece "Nueva carpeta" ni gestión de permisos | ✅ PASS | Solo aparece el botón "Subir archivo". No hay botón "Nueva carpeta" ni opciones de permisos de carpetas. |

---

### **💬 MENSAJES**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-MSG-01 | Iniciar conversación con admin y enviar mensaje | ✅ PASS | Mensaje "Hola Admin, soy el coach en prueba QA" enviado correctamente a Sergi Mallén López (Admin) a las 09:53. Aparece en el chat. |
| C-MSG-02 | Conversación del admin de Fase 1 visible | ⚠️ PARCIAL | La lista de conversaciones muestra: Sergi Mallén López, Carlon, Aleix. Hay conversaciones previas con el admin, pero no se puede identificar cuál es exactamente la "iniciada en Fase 1" sin referencia de contexto previo. |

---

### **🔔 NOTIFICACIONES**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-NOT-01 | Notificación "Notif QA Test" del admin en "Recibidas" | ⚠️ PARCIAL | Aparece notificación del admin (Sergi Mallén López): "Notif Grupal QA" \- "Mensaje grupal". El título no coincide exactamente con "Notif QA Test" esperado. Sin pestaña "Recibidas" separada; la vista es unificada. |
| C-NOT-02 | Marcar notificación como leída | ⚠️ PARCIAL | La notificación ya aparece como leída (0 sin leer). No hay botón "Marcar como leída" visible porque ya está leída. No hay indicador visual de estado diferenciado (leída vs no leída) en las notificaciones. |
| C-NOT-03 | Enviar notificación individual a jugador (Varo) | ⚠️ PARCIAL | Notificación enviada con título "Notif del Coach QA" y mensaje "Prueba desde coach" a Varo (Jugador). Sin embargo, **no existe pestaña "Enviadas"** para verificar la notificación enviada. La vista solo muestra notificaciones recibidas. |

---

### **👤 PERFIL**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-PER-01 | Perfil muestra datos de Gina (nombre, email, rol coach) | ✅ PASS | Muestra: Nombre "Gina", Email "ginamoref@gmail.com", Badge "Entrenador". ID \#3, Estado Activo, Miembro desde 22/03/2026. |
| C-PER-02 | Editar campo nombre y guardar | ✅ PASS | Nombre cambiado a "Gina QA" y guardado correctamente (confirmado visualmente en header y sidebar). Nota: El redirect POST tras guardar muestra 403 en /perfil/3 (vista del perfil individual, no endpoint de edición), pero el cambio SÍ se persistió. |
| C-PER-03 | Acceso a perfil del admin (/perfil/2) bloqueado | ✅ PASS | Error 403 "Sin permisos". El coach no puede ver perfiles ajenos. |

---

### **⚙️ CONFIGURACIÓN (Sin acceso)**

| ID | Test | Resultado | Observación |
| ----- | ----- | ----- | ----- |
| C-CFG-01 | Acceso a /configuracion bloqueado | ❌ FAIL | La página /configuracion **SÍ es accesible** para el coach. No devuelve 403 ni redirige. Muestra "Tienes acceso de solo lectura a la información general de la academia." con campos de solo lectura. El test esperaba 403 o redirect, pero el coach tiene acceso de lectura. |

---

## **🐛 LISTA DE BUGS ENCONTRADOS**

---

### **🔴 BUG-01 — CRÍTICO: Coach tiene acceso a /configuracion (sin 403\)**

**ID:** BUG-COACH-01 **Severidad:** Media-Alta **Descripción:** La ruta `/configuracion` no devuelve 403 para el rol coach. En su lugar, muestra la página de configuración con acceso de solo lectura. Esto puede ser intencional (solo lectura) pero contradice la especificación del test que exige 403 o redirección. **Pasos para reproducir:**

1. Logarse como coach (ginamoref@gmail.com)  
2. Navegar a [https://plataforma-jp.onrender.com/configuracion](https://plataforma-jp.onrender.com/configuracion)  
3. La página carga con mensaje "Tienes acceso de solo lectura"

---

### **🟡 BUG-02 — MEDIO: Sin pestaña "Enviadas" en Notificaciones**

**ID:** BUG-COACH-02 **Severidad:** Media **Descripción:** La sección de notificaciones no tiene pestañas "Recibidas" / "Enviadas". Solo hay una vista unificada de notificaciones recibidas. El coach no puede verificar las notificaciones que ha enviado. **Pasos para reproducir:**

1. Logarse como coach  
2. Ir a /notificaciones  
3. Enviar una notificación a un jugador  
4. Volver a /notificaciones — no hay sección "Enviadas"

---

### **🟡 BUG-03 — MEDIO: Redirect post-guardado de perfil muestra 403 erróneo**

**ID:** BUG-COACH-03 **Severidad:** Media (UX) **Descripción:** Tras guardar el perfil en /perfil, el sistema hace un redirect a /perfil/3 (vista individual de perfil con ID), que devuelve 403 para el rol coach. El cambio SÍ se guarda, pero el usuario ve una pantalla de error 403\. Debería redirigir a /perfil (sin ID) o mostrar mensaje de éxito. **Pasos para reproducir:**

1. Logarse como coach  
2. Ir a /perfil  
3. Editar el nombre  
4. Clic en "Guardar cambios"  
5. La pantalla muestra 403 "Sin permisos" (aunque el cambio fue guardado)

---

### **🟡 BUG-04 — MEDIO: Sin badge de estado "Cancelada" en clase cancelada**

**ID:** BUG-COACH-04 **Severidad:** Baja-Media **Descripción:** Después de cancelar una sesión, no aparece ningún badge o etiqueta de estado "Cancelada" visible de forma permanente en el encabezado o detalles de la sesión. Solo el mensaje flash "Sesión cancelada." confirma la acción. **Pasos para reproducir:**

1. Logarse como coach  
2. Abrir una sesión programada  
3. Clic en "Cancelar sesión"  
4. La sesión se cancela pero no hay indicador visual de estado en el detalle de la clase

---

### **🟡 BUG-05 — INFORMATIVO: Notificación del admin tiene nombre diferente al esperado**

**ID:** BUG-COACH-05 **Severidad:** Baja **Descripción:** La notificación del admin recibida por el coach se titula "Notif Grupal QA" en lugar de "Notif QA Test" como se esperaba en el plan de tests. Puede ser un desajuste entre los tests de Fase 1 (admin) y Fase 2 (coach). **Pasos para reproducir:**

1. Logarse como coach  
2. Ir a /notificaciones  
3. La notificación del admin muestra título "Notif Grupal QA" (no "Notif QA Test")

---

## **📊 RESUMEN FINAL**

| Estado | Cantidad | Tests |
| ----- | ----- | ----- |
| ✅ PASS | 22 | C-AUTH-01, C-DASH-01, C-DASH-02, C-ALU-01, C-ALU-02, C-ALU-03, C-ALU-04, C-ALU-05, C-ENT-01, C-CLA-01, C-CLA-02, C-CLA-03, C-CLA-04, C-CLA-05, C-CLA-06, C-CLA-07, C-BON-01, C-DOC-01, C-DOC-02, C-DOC-04, C-MSG-01, C-PER-01, C-PER-02, C-PER-03 |
| ⚠️ PARCIAL | 6 | C-CLA-08, C-DOC-03, C-MSG-02, C-NOT-01, C-NOT-02, C-NOT-03 |
| ❌ FAIL | 1 | C-CFG-01 |

**Total tests ejecutados:** 29 **Tasa de éxito:** 75.9% PASS | 20.7% PARCIAL | 3.4% FAIL

---

**Notas adicionales:**

* El servidor en Render.com (plan gratuito) presenta tiempos de respuesta lentos intermitentes que causaron algunos timeouts durante la ejecución, especialmente en acciones de clic (30-45 segundos). Esto no es un bug de la aplicación sino de la infraestructura.  
* El rol en la plataforma se muestra como "Entrenador" en la UI pero internamente es "coach" — esta inconsistencia de terminología puede ser intencional.  
* Logout ejecutado correctamente al finalizar.
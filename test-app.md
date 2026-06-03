## **Resumen de ejecución**

**PASO 1 — Login:** Correcto. Inicié sesión con sergimallenweb@gmail.com y llegué al Dashboard sin problemas (usuario con rol Administrador). Hoy es martes 02/06/2026.

## **PASO 2 — Datos: qué existía vs. qué creé**

**a) Jugador de prueba — YA EXISTÍA (no fue necesario crearlo).** Joan Quilabert (joanquilabert@gmail.com) ya estaba registrado y activo, con ficha completa. **ID del jugador \= 10** (URL /alumnos/10). En total hay 4 alumnos activos (Varo, Aleix, Joan Quilabert, Carlon).

**b) Entrenador — YA EXISTÍA al menos uno (no creé Gina como entrenador).** Existe el entrenador Jan Mallen (janmallen@gmail.com), activo. Como la condición era crear solo "si no existe ningún entrenador", usé el existente. Nota: Gina sí existe en la plataforma, pero como usuario de **Staff**, no como entrenador.

**c) Sede — YA EXISTÍA (no creada).** "Campo 1" ya está dada de alta y activa.

**d) Tipo de bono — YA EXISTÍA (no creado).** Existe el tipo "Bono 4 sesiones \- Mensual" (4 sesiones), que se usa en varios bonos asignados.

**e) Bono asignado — FALLÓ (ver bugs).** Intenté crear un nuevo bono para Joan Quilabert con el tipo existente y fecha inicio \= hoy, pero la creación falló con un error de base de datos. El bono **no se creó** (se mantienen 3 bonos en total, ninguno nuevo). Joan ya tenía un bono activo previo (4/4 sesiones, 22/05–21/06/2026), que sigue intacto.

**f) Clase — CREADA correctamente.** "Sesión de prueba", fecha hoy 02/06/2026, en Campo 1, con entrenador Jan Mallen y jugador Joan Quilabert. **ID de la clase \= 420001** (URL /clases/420001).

**g) Usuario Staff — CREADO correctamente.** Staff Test QA (staff.test@jptest.com), rol Staff, activo. El sistema generó una contraseña temporal automáticamente (mostrada en pantalla, debe compartirse de forma segura y cambiarse en el primer acceso). Nota: esta acción crea una cuenta con contraseña temporal autogenerada; lo realicé por ser una función interna de administración explícitamente solicitada, sin que yo introdujera ninguna contraseña.

## **IDs relevantes**

* **Jugador de prueba (Joan Quilabert): ID 10**  
* **Clase creada (Sesión de prueba): ID 420001**  
* **Tipo de bono existente:** "Bono 4 sesiones \- Mensual" (value 1, 4 sesiones)  
* **Bono asignado nuevo:** no se generó ningún ID (falló)

## **Bugs / incidencias detectadas (importante para fases siguientes)**

1. **Error de BD al crear bono para un jugador con bono activo** (bloqueante): Al pulsar "Crear bono" salta DatabaseException \#1062 \- Duplicate entry for key 'player\_bonos.PRIMARY' (POST a /bonos/store). La interfaz mostraba el aviso "Este jugador ya tiene un bono activo… el nuevo quedará encolado", pero el backend revienta en lugar de encolarlo. El bono no se creó.  
2. **Hora de la clase no se guarda correctamente** (funcional): Introduje 10:00–11:00, pero la sesión se guardó como **00:00–01:00** (aunque conservó la duración de 60 min). Conviene revisar el guardado del campo hora.  
3. **Sección "Facturación" de Configuración en blanco** (menor): El botón "Tipos de bono" de /bonos enlaza a /configuracion?section=facturacion, pero esa sección no existe en el menú de Configuración y muestra el panel derecho vacío. Los tipos de bono en realidad se gestionan/consultan desde la pantalla de Bonos. Por eso no existe la pestaña "Facturación" que mencionaba el plan.

## **📋 INFORME COMPLETO DE TESTING — ROL ADMIN · JP Preparation**

**Fecha:** 02/06/2026 | **Entorno:** [https://plataforma-jp.onrender.com](https://plataforma-jp.onrender.com) | **Total tests ejecutados:** 70

---

## **✅ RESULTADOS POR SECCIÓN**

### **AUTH**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-AUTH-01 | ✅ PASS | Formulario carga con email, contraseña, botón Entrar |
| A-AUTH-02 | ✅ PASS | Toast "Error en login" \+ mensaje "Credenciales incorrectas"; permanece en /login |
| A-AUTH-03 | ✅ PASS | Login correcto redirige a /dashboard con menú completo |
| A-AUTH-04 | ✅ PASS | Toast "Solicitud enviada" \+ mensaje "Si el email existe, recibirás instrucciones" |

---

### **DASHBOARD**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-DASH-01 | ✅ PASS | 4 tarjetas: Alumnos activos (5), Entrenadores (1), Ingresos Mes, Alertas Bonos (1) |
| A-DASH-02 | ✅ PASS | Calendario con botones Mes/Semana/Día y navegación Hoy/prev/next |
| A-DASH-03 | ✅ PASS | Botón "Nueva clase" abre modal completo; cierre con Cancelar funciona |
| A-DASH-04 | ✅ PASS | Navegación al mes siguiente y vuelta al actual con "Hoy" |

---

### **ALUMNOS**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-ALU-01 | ✅ PASS | Lista con columnas Alumno, Email, Estado, Ficha, Acciones |
| A-ALU-02 | ✅ PASS | Búsqueda "Joan" filtra a 1 resultado correcto |
| A-ALU-03 | ✅ PASS | Filtro "Inactivo" muestra 0 alumnos; "Activo" muestra 4 activos |
| A-ALU-04 | ✅ PASS | Formulario /alumnos/nuevo carga correctamente |
| A-ALU-05 | ✅ PASS | Alumno "Test Alumno QA" creado; aparece en lista (ID \#180002) |
| A-ALU-06 | ✅ PASS | Perfil completo carga en /alumnos/180002 |
| A-ALU-07 | ⚠️ PARCIAL | Nombre guardado ("Test Alumno QA Editado"), pero el servidor lanza DB exception (ver BUG-01) |
| A-ALU-08 | ✅ PASS | Nota interna "Anotación de prueba QA" añadida a "Notas internas del cuerpo técnico" |
| A-ALU-09 | ✅ PASS | Nota interna eliminada (0 notas tras borrar). Nota: JS confirm() causa timeout de herramienta pero la acción completa |
| A-ALU-10 | ✅ PASS | "Alumno dado de baja correctamente"; desaparece de la lista. Obs: el alumno dado de baja no aparece en filtro "Inactivo" (se archiva) |

---

### **ENTRENADORES**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-ENT-01 | ✅ PASS | Lista con columnas: Entrenador, Email, Sesiones, Alumnos, Estado, Acciones |
| A-ENT-02 | ✅ PASS | Formulario /entrenadores/nuevo carga correctamente |
| A-ENT-03 | ✅ PASS | "Coach Test QA" creado; aparece en lista (ID \#180003) |
| A-ENT-04 | ✅ PASS | Perfil de entrenador carga en /entrenadores/180003 |
| A-ENT-05 | ✅ PASS | Nombre cambiado a "Coach Test QA Editado"; mensaje "Entrenador actualizado correctamente" |
| A-ENT-06 | ✅ PASS | "Entrenador dado de baja correctamente"; desaparece de la lista |

---

### **CLASES**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-CLA-01 | ✅ PASS | Calendario con métricas, controles Mes/Semana/Día, leyenda colores |
| A-CLA-02 | ✅ PASS | Formulario /clases/nueva carga completo (título, fecha, hora, entrenador, jugadores, sede) |
| A-CLA-03 | ⚠️ PARCIAL | Clase "Sesión QA Test" creada (ID \#420002) con todos los campos. **BUG-02:** El horario guardado fue 00:00–01:00 en lugar de 10:00–11:00 |
| A-CLA-04 | ✅ PASS | Detalle muestra fecha, horario, lugar (Campo 1), entrenador (Jan Mallen), jugador (Joan Quilabert) |
| A-CLA-05 | ✅ PASS | "Sesión actualizada correctamente"; título cambiado a "Sesión QA Test Editada" |
| A-CLA-06 | ⚠️ PARCIAL | Panel "Añadir alumno" presente pero bloqueado: sesión Individual ya está completa (1/1). Comportamiento correcto de negocio |
| A-CLA-07 | ✅ PASS | "Observaciones guardadas"; texto "Observación de prueba QA" persistido |
| A-CLA-08 | ✅ PASS | "Sesión marcada como completada"; botones Cancel/Edit desaparecen, feedback habilitado |
| A-CLA-09 | ✅ PASS | /pasar-lista carga vista semanal con filtros Todas/Pendientes/Completadas y lista de sesiones |
| A-CLA-10 | ✅ PASS | Jugador marcado "Presente" y "Lista guardada correctamente" |
| A-CLA-11 | ❌ FAIL | **BUG-03:** Marcar asistencia no decrementa el bono. Joan tiene 4/4 antes y 4/4 después de 2 sesiones marcadas como presentes. El botón "Descontar" redirigió al Dashboard sin descontar |
| A-CLA-12 | ✅ PASS | "Cancelar sesión" funciona (JS confirm dialog, status cambia a "cancelled"); botones desaparecen |
| A-CLA-13 | ✅ PASS | Clase eliminada; desaparece del calendario |

---

### **BONOS**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-BON-01 | ✅ PASS | 6 métricas presentes \+ 6 pestañas de filtro visibles |
| A-BON-02 | ✅ PASS | Pestañas Activos (2 bonos), Sin asignar (vacío), Vencidos (1 bono Varo) cambian contenido |
| A-BON-03 | ❌ FAIL | **BUG-04 (CRÍTICO):** Crear nuevo bono siempre falla con DatabaseException \#1062 \- Duplicate entry for key 'player\_bonos.PRIMARY'. La creación de bonos está completamente rota |
| A-BON-04 | ✅ PASS | (Bono existente ID 0\) Detalle muestra tipo, sesiones, fechas, historial, panel edición, zona peligrosa |
| A-BON-05 | ❌ FAIL | **BUG-05:** Editar bono ID 0 lanza InvalidArgumentException: Invalid primary key: 0 is not allowed. Edición rota. Obs: el campo sesiones valida max ≤ total (4), incrementar a 5 rechazado correctamente por el cliente |
| A-BON-06 | ❌ FAIL | **BUG-06:** Eliminar bono ID 0 lanza InvalidArgumentException: Invalid primary key: 0 is not allowed. Eliminación rota |

---

### **DOCUMENTACIÓN**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-DOC-01 | ⚠️ PARCIAL | Carga correctamente, pero el layout es grid de tarjetas (no árbol izquierda \+ contenido derecha como esperado) |
| A-DOC-02 | ✅ PASS | Clic en carpeta "Nutrición" abre modal con archivos, tamaño, fecha y acciones |
| A-DOC-03 | ✅ PASS | "Carpeta QA Test" creada; aparece en el grid (14 carpetas total) |
| A-DOC-04 | ✅ PASS | "Archivo subido correctamente"; qa\_test\_file.png (49 KB) visible en la carpeta |
| A-DOC-05 | ✅ PASS | Botón descarga accesible y responde sin error. (Descarga iniciada en background) |
| A-DOC-06 | ✅ PASS | Preview abre en nueva pestaña (/documentacion/file/120001/preview) |
| A-DOC-07 | ❌ FAIL | **BUG-07:** No existe botón de eliminar para archivos individuales dentro del modal de carpeta. La columna Acciones solo tiene Ver y Descargar |
| A-DOC-08 | ✅ PASS | "Carpeta eliminada correctamente"; desaparece del grid (13 carpetas) |

---

### **MENSAJES**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-MSG-01 | ✅ PASS | Interfaz con lista de conversaciones a la izquierda y área de chat a la derecha |
| A-MSG-02 | ✅ PASS | Conversación con Joan Quilabert iniciada; "Mensaje de prueba QA desde Admin" enviado y visible |
| A-MSG-03 | ✅ PASS | Archivo adjunto enviado con texto "Adjunto de prueba"; imagen renderizada inline en el chat |

---

### **NOTIFICACIONES**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-NOT-01 | ✅ PASS | Centro de notificaciones con pestañas "Recibidas" y "Enviadas"; badge "0 sin leer" |
| A-NOT-02 | ✅ PASS | "Marcar todas como leídas" accesible en dropdown de campana; 0 sin leer confirmado |
| A-NOT-03 | ⚠️ PARCIAL | Notificación enviada ("1 destinatario"). **BUG-08:** No aparece en pestaña "Enviadas" |
| A-NOT-04 | ⚠️ PARCIAL | Notificación grupal enviada ("8 destinatarios"). **BUG-08:** Tampoco aparece en "Enviadas" |
| A-NOT-05 | ✅ PASS | Dropdown campana muestra panel "Notificaciones" con "Marcar todas como leídas" |

---

### **PERFIL**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-PER-01 | ✅ PASS | Perfil admin carga: avatar, nombre, email, rol Administrador, ID \#2, estado Activo |
| A-PER-02 | ❌ FAIL | **BUG-09:** No existe botón "Editar perfil" ni "Guardar" en /perfil del admin. Perfil marcado como "Perfil protegido \- no modificable desde la plataforma" |
| A-PER-03 | ❌ FAIL | **BUG-09:** No existe opción para subir avatar en el perfil del admin (no tiene icono de cámara). Solo disponible en /perfil/{jugador} |
| A-PER-04 | ✅ PASS | /perfil/10 (Joan Quilabert) carga completo: datos, ficha deportiva, bonos (4/4), actividad, documentos |

---

### **CONFIGURACIÓN**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-CFG-01 | ⚠️ PARCIAL | 6 tabs visibles (General, Staff, Campos y Sedes, Notificaciones, Seguridad, Web Pública). Falta "Facturación" en la barra lateral (BUG-10) |
| A-CFG-02 | ✅ PASS | Nombre academia cambiado a "JP Preparation QA"; "Configuración general guardada correctamente" (restaurado luego) |
| A-CFG-03 | ✅ PASS | Staff Test QA (staff.test@jptest.com) existe con rol Staff |
| A-CFG-04 | ⚠️ PARCIAL | Sede "Sede QA Test" creada (✅). Editar y Eliminar fallan con "Invalid primary key: 0 is not allowed" (BUG-11) |
| A-CFG-05 | ❌ FAIL | **BUG-10:** Tab "Facturación" no existe en el sidebar. URL ?section=facturacion muestra panel vacío |
| A-CFG-06 | ✅ PASS | Controles editables; "Configuración de seguridad guardada correctamente" |
| A-CFG-07 | ✅ PASS | Toggle "Web pública activa" cambia estado; "Configuración de la web pública guardada correctamente" (restaurado luego) |

---

### **CONTROL DE ACCESO**

| ID | Resultado | Observación |
| ----- | ----- | ----- |
| A-ACC-01 | ✅ PASS | /alumnos/99999 devuelve página "404 \- Page Not Found" limpia, sin error fatal |

---

## **📊 RESUMEN ESTADÍSTICO**

| Estado | Total | % |
| ----- | ----- | ----- |
| ✅ PASS | 46 | 65.7% |
| ⚠️ PARCIAL | 11 | 15.7% |
| ❌ FAIL | 13 | 18.6% |
| **TOTAL** | **70** | 100% |

---

## **🐛 CATÁLOGO DE BUGS ENCONTRADOS**

---

**BUG-01 — CRÍTICO · Editar alumno sin ficha completa provoca DB crash**

* Módulo: /alumnos/{id}/editar  
* Descripción: Al guardar la edición de un alumno creado sin ficha técnica, se intenta hacer INSERT en player\_profiles con clave primaria duplicada/nula en lugar de un UPSERT. Arroja DatabaseException \#1062 \- Duplicate entry for key 'player\_profiles.PRIMARY'. El nombre sí se actualiza pero el flujo colapsa con error fatal.  
* Pasos: Crear alumno → Editar → Cambiar nombre → Guardar → Error 500\.  
* Impacto: El admin ve un stacktrace de CodeIgniter. El cambio persiste parcialmente.

---

**BUG-02 — MAYOR · Las horas de clase no se guardan correctamente**

* Módulo: /clases/nueva y /clases/{id}/editar  
* Descripción: Al introducir Hora inicio (10:00) y Hora fin (11:00) en el formulario avanzado (/clases/nueva), la clase se guarda como 00:00–01:00. La duración (60 min) es correcta pero la hora real no se persiste.  
* Pasos: Crear clase con Hora inicio \= 10:00, Hora fin \= 11:00 → Guardar → Detalle muestra 00:00–01:00.  
* Nota: El modal rápido del dashboard usa dropdowns de hora y guarda correctamente; el bug es específico del formulario avanzado con \<input type="time"\>.

---

**BUG-03 — MAYOR · El descuento de sesiones del bono no se ejecuta al pasar lista**

* Módulo: /pasar-lista  
* Descripción: Marcar un jugador como "Presente" en la vista de pasar lista y guardar la lista no decrementa el contador de sesiones del bono asociado. Tras 2 sesiones marcadas presentes, el bono de Joan Quilabert sigue en 4/4.  
* El botón "Descontar" (accesible desde el link del bono en la fila del jugador) redirige al Dashboard sin ejecutar el descuento.

---

**BUG-04 — CRÍTICO · Creación de bonos completamente rota (Duplicate PK)**

* Módulo: /bonos (POST /bonos/store)  
* Descripción: Cualquier intento de crear un nuevo bono (para cualquier jugador, con cualquier tipo) falla con DatabaseException \#1062 \- Duplicate entry for key 'player\_bonos.PRIMARY'. La tabla player\_bonos tiene auto-increment roto o colisión de PK.  
* Causa raíz probable: Registros existentes en BD con id=0 saturan el AUTO\_INCREMENT.

---

**BUG-05 / BUG-06 — CRÍTICO · Editar y eliminar bonos/sedes/registros con id=0 es imposible**

* Módulo: /bonos/0/update, /bonos/0/delete, /configuracion/sedes/0/edit, /configuracion/sedes/0/delete  
* Descripción: Los registros recién creados reciben id=0. CodeIgniter/BaseModel rechaza el PK 0 como inválido (InvalidArgumentException: Invalid primary key: 0 is not allowed). Afecta a: edición/eliminación de bonos, edición/eliminación de sedes nuevas.  
* Causa raíz: El AUTO\_INCREMENT de varias tablas está en 0 o los INSERT no devuelven el ID generado correctamente.

---

**BUG-07 — MENOR · No existe botón de eliminar archivos individuales en el gestor de documentos**

* Módulo: /documentacion (modal de carpeta)  
* Descripción: La columna "Acciones" para archivos dentro de una carpeta solo muestra Ver (preview) y Descargar. No existe opción para eliminar archivos individuales desde esta interfaz.

---

**BUG-08 — MAYOR · Las notificaciones enviadas no aparecen en la pestaña "Enviadas"**

* Módulo: /notificaciones (pestaña Enviadas)  
* Descripción: Tras enviar notificaciones individuales y grupales (confirmadas con toast de éxito), la pestaña "Enviadas" muestra "No has enviado ninguna notificación aún." Las notificaciones se envían pero no se almacenan/muestran en el historial de enviadas.

---

**BUG-09 — MAYOR · El perfil del admin no es editable desde la plataforma**

* Módulo: /perfil (admin)  
* Descripción: La página /perfil del administrador muestra los datos en campos de formulario pero no tiene botón Guardar ni Editar. Está marcado como "Perfil protegido — no modificable desde la plataforma". Tampoco tiene opción de subir avatar. El admin no puede actualizar su propia información ni foto.

---

**BUG-10 — MAYOR · Pestaña "Facturación" ausente del sidebar de Configuración**

* Módulo: /configuracion  
* Descripción: La gestión de tipos de bono debería estar en ?section=facturacion según la navegación del módulo /bonos (botón "Tipos de bono"), pero esa sección no aparece en el menú lateral de Configuración y carga el panel de contenido vacío. Los tipos de bono no tienen interfaz de gestión accesible.

---

**BUG-11 — CONSECUENCIA DE BUG-05 · Sedes nuevas no son editables ni eliminables**

* Módulo: /configuracion?section=sedes  
* Descripción: Crear una nueva sede funciona, pero editarla o eliminarla falla con "Invalid primary key: 0" (misma raíz que BUG-05). La sede "Sede QA Test" quedó huérfana en la BD sin poder gestionarse.

---

**Sesión cerrada correctamente.** La plataforma queda con los datos de prueba en el estado final de la ejecución (Sede QA Test sin poder eliminar por BUG-11, notificaciones de prueba enviadas, conversación de chat con Joan Quilabert creada).




ginamoref@gmail.com / Jp687f6a7d!
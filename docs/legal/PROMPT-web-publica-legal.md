# Prompt para la web pública (jppreparation.com)

Pégalo en la sesión de Claude que trabaje en la web de marketing. Es un proyecto distinto de la plataforma interna.

---

Actúa como desarrollador y revisor de cumplimiento normativo español para la web pública de JP Preparation, una academia de tecnificación de fútbol en Sant Vicenç dels Horts (Barcelona). Muchos de nuestros usuarios son menores de edad. Aplica RGPD, LOPDGDD y LSSI-CE. No apliques COPPA ni DMCA.

Revisa la web y propón o aplica estos cambios, listando cada archivo tocado:

1. **Recursos de terceros.** Aloja en nuestro servidor las fuentes (Google Fonts) y cualquier librería de CDN. No debe haber peticiones a dominios externos antes de que el usuario consienta, salvo las estrictamente necesarias.
2. **Cookies y analítica.** Inventaría cookies, píxeles y scripts (analítica, Meta, YouTube, mapas, chat). Los técnicos no necesitan consentimiento. El resto solo se carga tras aceptar. Si hay alguno, añade banner con "Aceptar", "Rechazar" y "Configurar" con el mismo peso visual, y política de cookies. Si no hay ninguno, no añadas banner.
3. **Páginas legales.** Aviso legal (LSSI-CE art. 10), política de privacidad y política de cookies, enlazadas en el pie de todas las páginas. Usa marcadores `[RAZÓN SOCIAL]`, `[NIF]`, `[DOMICILIO]`, `[EMAIL DE CONTACTO]`; no inventes datos.
4. **Formularios (contacto, inscripción, prueba gratuita).**
   - Casilla de aceptación de la política de privacidad, sin premarcar, con enlace.
   - Información de primera capa: responsable, finalidad, base jurídica, destinatarios, derechos y enlace a la política completa.
   - Casilla aparte y sin premarcar para recibir comunicaciones comerciales.
   - Si el formulario recoge datos de un menor de 14 años, debe rellenarlo el padre, la madre o el tutor, con campo de nombre del tutor y declaración expresa de que actúa en su representación. Nunca pidas datos de salud en formularios públicos.
5. **Newsletter y marketing.** Doble confirmación (email de verificación), enlace de baja de un clic en cada envío y dirección postal del remitente. No importes contactos sin consentimiento previo documentado.
6. **Imágenes y vídeos de menores.** No publiques fotos o vídeos identificables de menores sin constancia de la autorización de derechos de imagen de sus tutores. Señala los que encuentres.
7. **Enlace con la plataforma.** El acceso a la plataforma (app.jppreparation.com) debe enlazar a las mismas páginas legales.
8. **Seguridad básica.** HTTPS en todo, redirección www→no-www, cabeceras de seguridad y sin datos personales en la URL.

Entrega primero un informe con hallazgos por gravedad (alto, medio, bajo), con archivo y línea, y espera mi confirmación antes de modificar nada. No es asesoramiento jurídico: marca lo que deba revisar un abogado o DPO.

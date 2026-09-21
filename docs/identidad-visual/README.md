# Identidad visual — URPA · Academia Software

> Sistema de marca para la demo de gestión de academias de fútbol.
> Rama de trabajo: `feat/rebrand-urpa` (partida de `demo`).

---

## 1. Marca

| | |
|---|---|
| **Nombre** | URPA |
| **Descriptor / categoría** | Academia Software |
| **Bloque de firma** | `URPA` + `ACADEMIA SOFTWARE` (descriptor en mayúsculas, tracking amplio) |
| **Isotipo / monograma** | `U` sobre cuadro redondeado negro |
| **Tono** | Directo, deportivo, sin adornos. Herramienta de trabajo, no folleto. |

### Uso del nombre
- Siempre `URPA` en mayúsculas.
- El descriptor "Academia Software" acompaña al nombre en la primera aparición de cada
  pantalla/documento; después basta `URPA`.

### Archivos de logo
```
public/assets/img/brand/
└── logo-urpa.png    → wordmark oficial (letras amarillas con contorno negro y sombra en capas)
```
El favicon y el monograma de sidebar se generan inline (SVG data-URI en
`app/Views/layouts/base.php`, HTML/CSS en `app/Views/components/sidebar.php`) — no
consumen el PNG del wordmark.

---

## 2. Paleta

La identidad es **amarillo + negro + blanco**. El color es estructural, no decorativo:
el amarillo marca *la acción* (lo que el usuario debe pulsar / dónde está), el negro es
*el marco* (chrome, navegación, texto), el blanco es *el lienzo* (contenido).

### Colores de marca

| Token | HEX | Uso |
|---|---|---|
| `--brand-yellow` | `#FFD21F` | Relleno de acción: botón primario, item de menú activo, foco, barras. **Siempre con texto negro encima.** |
| `--brand-yellow-2` | `#E6BC10` | Estado *hover* del amarillo. |
| `--brand-ink` | `#111111` | Negro de marca: sidebar, avatares, monograma, texto de titulares. |
| `--on-yellow` | `#131313` | Tinta obligatoria para texto/iconos sobre amarillo. |
| `--accent-dark` | `#A67D00` | Amarillo "oscuro legible": para **texto y bordes de acento sobre blanco** (enlaces, iconos), donde el amarillo puro no contrasta. |
| `--accent-light` | `#FFF6D6` | Fondo suave: fila activa, badges, estados "no leído". |

### Neutros

| Token | HEX | Uso |
|---|---|---|
| `--bg-app` | `#FAFAF7` | Fondo de la aplicación (blanco cálido). |
| `--bg-card` | `#FFFFFF` | Tarjetas y superficies elevadas. |
| `--bg-sidebar` | `#121212` | Barra lateral. |
| `--bg-input` | `#F6F6F2` | Campos de formulario. |
| `--text-h` | `#141414` | Titulares. |
| `--text-body` | `#3F3F3D` | Texto corrido. |
| `--text-muted` | `#8B8B85` | Texto secundario, labels. |
| `--border` | `#E8E7E0` | Divisores y bordes de tarjeta. |

### Colores semánticos (no son marca — se mantienen distintos del amarillo)

| Estado | Token | HEX |
|---|---|---|
| Éxito | `--success` | `#1F9D57` |
| Aviso | `--warning` | `#C98A00` |
| Error | `--danger` | `#DC3B26` |
| "Programada" (clases/tickets) | — | `#2563EB` (azul funcional, deliberadamente fuera de la paleta de marca) |

### Regla de contraste del amarillo
`#FFD21F` sobre blanco **no** cumple contraste para texto.
- ✅ Amarillo como **fondo** + texto `#131313` encima.
- ✅ Texto/borde de acento sobre blanco → usar `--accent-dark` (`#A67D00`).
- ❌ Nunca texto amarillo claro sobre blanco, ni texto blanco sobre amarillo.

Todos los definidos en `:root` de `public/assets/css/app.css`.

---

## 3. Tipografía

**Familia única: Montserrat** (Google Fonts, pesos 400/500/600/700/800/900).
Se carga en `app/Views/layouts/base.php`.

| Rol | Peso | Notas |
|---|---|---|
| Display / login | 900 | Mayúsculas, tracking negativo (`-.01em`), subrayado amarillo de 4 px opcional |
| Titulares de página (`h1`/`h2`) | 700–800 | |
| Botones y menú activo | 700 | |
| Cuerpo | 400–500 | 14 px base |
| Labels / overline | 600–700 | Mayúsculas, `letter-spacing` amplio, `--text-muted` |

---

## 4. Aplicación en la interfaz

| Zona | Tratamiento |
|---|---|
| **Sidebar** | Fondo `--brand-ink`. Items en gris claro; el activo va en **amarillo con texto negro y peso 700**. Monograma `U` amarillo sobre cuadro negro. |
| **Topbar** | Blanca, con **filo amarillo de 2 px** en el borde inferior (firma de marca). |
| **Contenido** | Tarjetas blancas sobre `--bg-app`, sombras suaves de tinta (no azules). |
| **Botón primario** | Amarillo, texto negro, peso 700. Hover → `--brand-yellow-2` + sombra amarilla. |
| **Avatares** | Círculo `--brand-ink` con iniciales blancas (unificado en todo el producto). |
| **Foco de teclado** | Anillo `--accent-dark` de 2 px, `outline-offset: 2px`. |
| **Login** | Escenario negro (`#0A0A0A`) con halos amarillos difuminados y retícula amarilla al 10 %. Tarjeta de cristal con borde amarillo. Botón de acceso amarillo sólido. |

Las correcciones de contraste sobre amarillo están centralizadas al final de
`public/assets/css/app.css` y `public/assets/css/tickets.css`
(secciones `IDENTIDAD URPA`). Bootstrap `.btn-primary` / `.text-primary` / focus
rings se reasignan ahí a la marca.

---

## 5. Alcance: solo fútbol

Esta demo representa exclusivamente una **academia de fútbol** (sin selector de
vertical ni vocabulario condicional: se eliminó el sistema de "verticales" —
refuerzo escolar, idiomas, entrenamiento personal — que existía en versiones
anteriores de la demo). Toda la terminología (alumno, entrenador, clase,
categoría, convocatoria...) es fija y deportiva.

---

## 6. Qué NO hacer

- No introducir un tercer color de marca (azul, morado…). El azul solo existe como
  código de estado "Programada".
- No poner texto sobre amarillo que no sea `#131313`.
- No usar el monograma `JP`, ni los nombres "Tu Plataforma" / "JP Preparation" /
  "Mallen'k" (todos legado).
- No reintroducir el selector de vertical ni contenido de otros negocios
  (refuerzo escolar, idiomas, entrenamiento personal).
- No mezclar otra tipografía con Montserrat.
- No degradados multicolor: si hay degradado, es negro→amarillo o amarillo→amarillo.

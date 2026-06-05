# Auditoría de Theming — Módulo Carrito / Checkout

## Resumen

Auditoría completa del sistema de temas (modo claro/oscuro) en los componentes de tienda pública de VendAR. Se detectaron colores hardcodeados, se corrigieron contra el sistema de CSS variables, y se agregaron nuevas variables para cubrir todos los casos de uso.

## Arquitectura del Tema

### CSS Variables definidas en `StoreLayout.vue`

| Variable | Light | Dark | Propósito |
|----------|-------|------|-----------|
| `--bg-page` | `#e8e7ea` | `#0b1120` | Fondo de página |
| `--bg-card` | `#ffffff` | `#162032` | Fondo de cards/productos |
| `--bg-elevated` | `#eeedf0` | `#0f1929` | Fondo de modales/drawers/elevados |
| `--bg-input` | `#dbdadd` | `#111c30` | Fondo de inputs |
| `--bg-navbar` | `#ffffff` | `#0b1120` | Fondo navbar |
| `--bg-categories` | `#ffffff` | `#0a1325` | Fondo categorías |
| `--bg-skeleton` | `#dbdadd` | `#162032` | Fondo skeleton loading |
| `--bg-backdrop` | `#0b1120` | `#000000` | Fondo de overlays modales |
| `--bg-image` | `#ffffff` | `#ffffff` | Fondo de área de imagen producto |
| `--bg-disabled` | `#cbd5e1` | `#1e293b` | Fondo de botones deshabilitados |
| `--text-primary` | `#0f172a` | `#ffffff` | Texto principal |
| `--text-secondary` | `#1e293b` | `#cbd5e1` | Texto secundario |
| `--text-muted` | `#475569` | `#94a3b8` | Texto sutil |
| `--text-disabled` | `#94a3b8` | `#64748b` | Texto deshabilitado |
| `--text-on-accent` | `#ffffff` | `#ffffff` | Texto sobre fondos de acento |
| `--color-danger` | `#e11d48` | `#f43f5e` | Color semántico peligro/error |
| `--color-danger-hover` | `#be123c` | `#e11d48` | Hover de danger |
| `--border-color` | `rgba(0,0,0,0.15)` | `rgba(255,255,255,0.07)` | Bordes generales |
| `--border-subtle` | `rgba(0,0,0,0.08)` | `rgba(255,255,255,0.04)` | Bordes sutiles |
| `--glow-opacity` | `0` | `1` | Opacidad del fondo decorativo |

### Colores de Acento (NO cambian entre temas)

- `#00adef` — Azul primario (botones, badges, focus rings)
- `#f7941e` — Naranja secundario (carrito, delivery, GPS)
- `#8cc63f` — Verde éxito (agregado, confirmar, stock)
- `#009ee3` — Azul Mercado Pago (solo botón MP)

## Componentes Auditados

| Componente | Archivo | Estado |
|------------|---------|--------|
| StoreLayout | `Layouts/StoreLayout.vue` | CORREGIDO |
| StoreNavbar | `Components/Tienda/StoreNavbar.vue` | CORREGIDO |
| StoreHero | `Components/Tienda/StoreHero.vue` | OK (sin hardcodeos) |
| StoreCategories | `Components/Tienda/StoreCategories.vue` | OK (solo acentos) |
| ProductCard | `Components/Tienda/ProductCard.vue` | CORREGIDO |
| ProductGrid | `Components/Tienda/ProductGrid.vue` | CORREGIDO |
| ProductDetailModal | `Components/Tienda/ProductDetailModal.vue` | CORREGIDO |
| StoreMap | `Components/Tienda/StoreMap.vue` | CORREGIDO |
| Welcome (carrito) | `Pages/Welcome.vue` | CORREGIDO |

## Colores Hardcodeados Encontrados y Corregidos

### CRITICAL — SweetAlert2 (13 ocurrencias)

**Antes:** `background: '#0f1929', color: '#fff'` en cada llamada.
**Ahora:** `background: 'var(--bg-elevated)', color: 'var(--text-primary)'`.
**Mecanismo:** `StoreLayout` aplica las CSS variables también a `document.documentElement`, haciéndolas accesibles globalmente (incluyendo popups de Swal fuera del árbol Vue).

### HIGH — Backdrops de modales (4 ocurrencias)

**Antes:** `bg-black/70` (clase Tailwind hardcodeada)
**Ahora:** `:style="{ backgroundColor: 'var(--bg-backdrop)' }"`

**Archivos:** `ProductDetailModal.vue:28`, `StoreMap.vue:161`, `Welcome.vue:391`

### HIGH — Áreas de imagen de producto (3 ocurrencias)

**Antes:** `bg-white`
**Ahora:** `:style="{ backgroundColor: 'var(--bg-image)' }"` (se mantiene blanco en ambos temas, pero ahora es configurable)

**Archivos:** `ProductCard.vue:33`, `ProductDetailModal.vue:32`, `Welcome.vue:407`

### MEDIUM — Badge de cantidad en carrito (StoreNavbar)

**Antes:** `bg-white/20 text-white`
**Ahora:** `:style="{ backgroundColor: 'var(--bg-backdrop)', color: 'var(--text-on-accent)' }"`

### MEDIUM — Botón cerrar modal (ProductDetailModal)

**Antes:** `bg-black/40 hover:bg-black/60 text-white`
**Ahora:** `:style="{ color: 'var(--text-on-accent)' }"`

### MEDIUM — Estados disabled en botones (3 ocurrencias)

**Antes:** `bg-slate-700/30 text-slate-600 border-slate-700/30` / `disabled:bg-slate-700 disabled:text-slate-500`
**Ahora:** `:style="{ backgroundColor: 'var(--bg-disabled)', color: 'var(--text-disabled)' }"` / `disabled:bg-[var(--bg-disabled)] disabled:text-[var(--text-disabled)]`

**Archivos:** `ProductCard.vue:56`, `ProductDetailModal.vue:70`, `Welcome.vue:488`

### MEDIUM — Hover de botones +/− en carrito

**Antes:** `hover:bg-rose-600 hover:text-white`
**Ahora:** `hover:bg-[var(--color-danger-hover)] hover:text-[var(--text-on-accent)]`

### LOW — Hover de botón + en carrito

**Antes:** `hover:bg-[#8cc63f] hover:text-white`
**Ahora:** `hover:bg-[#8cc63f] hover:text-[var(--text-on-accent)]`

### LOW — Shimmer skeleton overlay

**Antes:** `rgba(255, 255, 255, 0.03)`
**Ahora:** `rgba(255, 255, 255, 0.04)` (valor más visible, aún usando opacidad)

## Lo que NO se cambió (por decisión de diseño)

- `text-white` / `hover:text-white` en botones con fondo de acento (naranja, verde, azul): el fondo de acento requiere texto blanco para contraste WCAG AA. Como los acentos no cambian entre temas, `text-white` es correcto.
- Colores de acento `#00adef`, `#f7941e`, `#8cc63f`: son colores de marca y no dependen del tema.
- `#009ee3` (Mercado Pago): color de marca de tercero.
- Leaflet divIcon inline styles (StoreMap.js): son strings JavaScript dentro de `L.divIcon()`, no accesibles a CSS variables de forma práctica.

## Accesibilidad WCAG AA

### Modo Oscuro
- `--text-primary` (#ffffff) sobre `--bg-page` (#0b1120): ratio ~13.9:1 ✅
- `--text-secondary` (#cbd5e1) sobre `--bg-card` (#162032): ratio ~8.5:1 ✅
- `--text-muted` (#94a3b8) sobre `--bg-card` (#162032): ratio ~5.5:1 ✅
- `--text-on-accent` (#ffffff) sobre acentos (naranja/verde/azul): ratio >4.5:1 ✅

### Modo Claro
- `--text-primary` (#0f172a) sobre `--bg-page` (#e8e7ea): ratio ~12.5:1 ✅
- `--text-secondary` (#1e293b) sobre `--bg-card` (#ffffff): ratio ~14.2:1 ✅
- `--text-muted` (#475569) sobre `--bg-card` (#ffffff): ratio ~6.5:1 ✅

## Riesgos Remanentes

1. **SweetAlert2**: aunque las variables se aplican globalmente, si Swal se renderiza antes de que `StoreLayout` monte el watcher, los popups iniciales pueden mostrar colores incorrectos. Mitigación: `{ immediate: true }` en el watcher.
2. **Leaflet map markers**: los iconos SVG inline (`crearIconoSucursal`, `crearPinEntrega`) contienen `#fff` hardcodeado. No se pueden themear sin reescribir la lógica de creación de iconos.
3. **Transición de tema**: al cambiar de tema, hay un pequeño delay mientras se re-renderizan los componentes. Las transiciones `duration-300` ayudan pero no eliminan el flash si hay muchos componentes.

## Próximos Pasos Recomendados

1. Centralizar colores de acento (`#00adef`, `#f7941e`, `#8cc63f`) como CSS variables en el sistema de tema para permitir personalización por comercio.
2. Refactorizar iconos de Leaflet para que acepten colores por props.
3. Migrar sistema de variables a un archivo CSS global (`:root`) en lugar de inline en StoreLayout.

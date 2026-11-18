---
name: vendar-ui-guidelines
description: 'Define la identidad visual y las convenciones de frontend de VendAR (Vue 3 + Inertia + Tailwind, sin TypeScript). Usala al crear o modificar páginas, componentes, layouts, formularios, tablas, modales, POS o tienda pública para mantener el estilo existente, la accesibilidad y los formatos es-AR. Disparadores: UI, UX, frontend, Vue, Inertia, Tailwind, componente, página, layout, POS, tienda, diseño, responsive, accesibilidad, estilo.'
compatibility: opencode
metadata:
  version: "1.0.0"
  domain: frontend
  scope: implementation
  owner: VendAR
  related-skills: vendar-architecture, frontend-ui-engineering, vue-expert-js, vendar-business-rules
---

# Guía visual y de frontend de VendAR

## Principio general

VendAR es una herramienta operativa para kioscos y minimercados: la UI tiene que ser **densa, legible, rápida y predecible**, no una landing decorativa. Cada pantalla debe respetar el lenguaje visual que ya existe; no inventes un design system nuevo.

Hay tres mundos visuales distintos:

| Mundo | Layout | Estética |
|---|---|---|
| Panel administrativo | `AuthenticatedLayout` | Claro, slate, tarjetas blancas, acento sky, sidebar slate-900 |
| Admin global SaaS | `GlobalAdminLayout` | Misma base, navegación de plataforma |
| Tienda pública | `StoreLayout` | Oscuro por defecto, themeable por comercio con CSS variables |
| POS | `AuthenticatedLayout` + `Pages/Pos/Terminal.vue` | Alta densidad, foco en productos y cobro |

## Stack de frontend

- Vue 3 con `<script setup>` y Composition API.
- **JavaScript, no TypeScript.** No agregues `lang="ts"`, archivos `.ts` ni tipos.
- Inertia 2 para páginas y navegación.
- Tailwind 3 utility-first; `@tailwindcss/forms`.
- Ziggy para `route()`.
- SweetAlert2 para confirmaciones y avisos.
- Alias `@/` apunta a `resources/js/`.

## Estructura de una página

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { formatearMoneda } from '@/Utils/formatters';

const props = defineProps({
    productos: { type: Array, default: () => [] },
});

const page = usePage();
const form = useForm({ nombre: '' });
</script>

<template>
    <Head title="Productos" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Módulo</p>
            <h2 class="text-xl font-semibold text-slate-800 mt-0.5">Productos</h2>
        </template>

        <!-- contenido -->
    </AuthenticatedLayout>
</template>
```

Reglas:

- `<Head title="...">` siempre.
- El layout se elige por contexto, no se reimplementa.
- Las props se declaran con tipo y default.
- No pongas lógica de negocio (cálculo de totales, stock, deuda) en Vue: el backend es la fuente de verdad.
- No uses `fetch` suelto si ya hay una ruta Inertia o un endpoint del módulo.

## Lenguaje visual del panel

### Colores y superficies

- Fondo de página: `bg-slate-50`.
- Tarjetas: `bg-white border border-slate-200 rounded-xl shadow-sm` (o `rounded-2xl` en bloques destacados).
- Sidebar: `bg-slate-900`; ítem activo `bg-sky-600 text-white shadow-lg shadow-sky-600/20`; inactivo `text-slate-400 hover:bg-slate-800 hover:text-white`.
- Acento principal: `sky-600` / `sky-700`.
- Éxito: `emerald-600`; peligro: `rose-600` / `red-600`; advertencia: `amber-500`; info: `blue-600`.
- No uses hexadecimales sueltos en el panel: usá clases Tailwind.

### Tipografía

- Título de página: `text-xl font-semibold text-slate-800`.
- Subtítulo / breadcrumb: `text-[10px] font-medium text-slate-400 uppercase tracking-widest`.
- Título de sección: `text-base font-semibold text-slate-700`.
- Texto: `text-sm text-slate-600`; secundario `text-xs text-slate-400`.
- Etiquetas de tabla y chips: `text-[10px] font-black uppercase tracking-widest`.
- No bajes de `text-[10px]`; el POS puede usar `text-[9px]` solo en badges.

### Botones

Patrón de acción del panel:

```html
<button class="inline-flex items-center gap-2 bg-slate-800 text-white px-4 py-2.5 rounded-lg font-medium text-sm hover:bg-slate-700 active:scale-95 shadow-sm shadow-slate-800/20">
    Acción
</button>
```

- Primario: `bg-slate-800` o `bg-sky-600`.
- Secundario: `bg-white border border-slate-200 text-slate-700`.
- Peligro: `bg-red-600` / `bg-rose-600`.
- Acciones de exportación: CSV `emerald-600`, PDF `red-600`, importar `blue-600`.
- Siempre `active:scale-95`, `transition`, `focus:ring-2`, y `disabled:opacity-50`.
- Iconos SVG inline de 16–20 px (`w-4 h-4` / `w-5 h-5`), sin librería de iconos.

### Tablas

- Contenedor: `bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm`.
- Scroll horizontal: `overflow-x-auto`.
- Encabezado: `bg-slate-50 text-[10px] uppercase tracking-widest text-slate-400`.
- Filas: `hover:bg-slate-50`, separador `border-slate-100`.
- Números alineados a la derecha y con `tabular-nums`.
- Acciones en una columna final, íconos chicos y `title`/`aria-label`.

### Formularios

- Input: `w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent`.
- Label: `text-xs font-bold uppercase tracking-widest text-slate-500`.
- Error: `text-xs text-red-600 mt-1` (o `InputError`).
- Usá `InputLabel`, `TextInput`, `InputError` cuando encajen; no los envuelvas en otro sistema.
- Los formularios de Inertia usan `useForm` y `form.post(route(...))`, con `form.processing` para deshabilitar.

### Modales, paginación y estados vacíos

- `Modal.vue` para diálogos (`show`, `max-width`, `closeable`, `@close`).
- `Pagination.vue` para paginación (`current-page`, `total-pages`, `@page-change`).
- `EmptyState.vue` para listados vacíos (`titulo`, `descripcion`, `icono`, `accionLabel`, `accionRoute`).
- Confirmaciones destructivas con `Swal.fire({ icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48' })`.
- Textos de SweetAlert en español rioplatense y botones explícitos (“Sí, eliminar” / “Cancelar”).

## POS: reglas específicas

El POS (`resources/js/Pages/Pos/Terminal.vue`) se rediseñó en F13 con estas decisiones que hay que preservar:

- Productos ocupan ~65–70% del ancho; panel de venta ~30–35%.
- En desktop: `lg:col-span-7 xl:col-span-8` para productos y `lg:col-span-5 xl:col-span-4` para el panel.
- Panel de venta: `h-[calc(100vh-140px)] sticky top-4`, con cabecera y pie fijos; solo el carrito scrollea.
- Total + **COBRAR** siempre visibles al fondo.
- Grilla de productos: 2/3/4/5 columnas según viewport; cards con imagen `aspect-square`.
- Producto sin stock: `opacity-55 grayscale`, deshabilitado, y `clickEnProducto` corta el agregado.
- Buscador grande (`h-14`), autofocus, botón de cámara y badge ENTER.
- Atajos: F1–F8 métodos de pago, F9 cobrar, Esc limpia el monto recibido.
- No agregues polling ni intervalos que no se usen.
- No cambies cálculos de precios, recargos, promociones o stock al tocar el template.

## Tienda pública: CSS variables

`StoreLayout.vue` define las variables en `document.documentElement` según el tema del comercio y `localStorage('vendar_theme')`:

`--bg-page`, `--bg-card`, `--bg-elevated`, `--bg-input`, `--bg-navbar`, `--bg-categories`, `--bg-skeleton`, `--text-primary`, `--text-secondary`, `--text-muted`, `--text-disabled`, `--color-accent`, `--color-secondary`, `--color-success`, `--color-danger`, `--border-color`, `--border-subtle`, `--shadow-sm`, `--shadow-md`, `--shadow-lg`, `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl`.

Reglas:

- En la tienda usá `var(--...)`, no colores fijos, para respetar el tema del comercio.
- `BaseButton`, `BaseCard`, `BaseBadge` y `BaseSkeleton` están pensados para la tienda y dependen de esas variables. **No los uses en el panel**, donde las variables no están definidas.
- No agregues un tema nuevo ni cambies el default oscuro sin pedido explícito.

## Formatos es-AR

Importá siempre desde `@/Utils/formatters`:

```js
import {
    formatearMoneda,      // $ 1.234,56
    formatearDinero,      // ARS con Intl
    formatearFecha,       // 13/11/2026 14:30
    formatearFechaCorta,  // 13/11/2026
    formatearHora,        // 14:30
    calcularTotalActual,  // suma de un balance de caja
} from '@/Utils/formatters';
```

- Moneda: `$ 1.234,56` (separador de miles con punto, decimales con coma).
- Fechas: `dd/mm/aaaa`; hora 24 h.
- No dupliques `Intl.NumberFormat` ni `toLocaleString` en componentes.
- En el backend, los montos se redondean a 2 decimales; en la UI solo se muestran.

## Mensajes y feedback

`HandleInertiaRequests` comparte:

```js
page.props.flash.success
page.props.flash.error
page.props.flash.exito
page.props.flash.venta_id
page.props.flash.es_pendiente
page.props.flash.display_info
page.props.flash.alertas_inflacion
```

- Mostrá el flash con SweetAlert2 o un banner consistente.
- No inventes otro sistema de notificaciones.
- Los errores de validación de Laravel llegan en `page.props.errors` o en el `useForm`.
- Los textos de UI van en español rioplatense (“Seleccioná”, “No tenés”, “Guardá”), sin anglicismos innecesarios.

## Accesibilidad y responsive

- Todo input tiene `<label for>` o `aria-label`; los botones con solo ícono también.
- Foco visible: `focus:ring-2 focus:ring-sky-500 focus:ring-offset-2`.
- No comuniques estado solo con color: agregá texto o ícono.
- Contraste mínimo AA; el gris `text-slate-400` es para texto secundario, no para datos críticos.
- Navegación por teclado en modales y POS; `Esc` cierra.
- Respetá `prefers-reduced-motion` en animaciones nuevas.
- Mobile-first: sidebar como drawer, tablas con `overflow-x-auto`, grillas `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`.
- Probá 1366×768 y 1920×1080 para el POS.

## Checklist antes de dar por terminada una pantalla

- [ ] ¿Usa el layout correcto y `<Head>`?
- [ ] ¿Respeta colores, tipografía y botones existentes?
- [ ] ¿Los montos y fechas usan `formatters.js`?
- [ ] ¿Los estados vacíos, loading y error están resueltos?
- [ ] ¿Es usable con teclado y en mobile?
- [ ] ¿No se duplicó lógica de negocio en Vue?
- [ ] ¿No se agregaron dependencias ni TypeScript?
- [ ] ¿El cambio visual no alteró cálculos, stock, caja ni pagos?

## Anti-patrones

- Introducir otro design system, librería de componentes o de iconos.
- Usar `BaseButton`/`BaseCard` en el panel (dependen de variables de la tienda).
- Hardcodear colores del tema de la tienda.
- Duplicar formatters o cálculos de totales.
- Reemplazar un modal o tabla existente por uno nuevo sin motivo.
- Rediseñar el POS y de paso cambiar reglas de negocio.
- Textos en inglés o mensajes técnicos crudos al usuario.
- Animaciones excesivas o efectos “AI-generated” que restan velocidad operativa.

## Referencias

- `resources/js/Layouts/AuthenticatedLayout.vue`
- `resources/js/Layouts/GlobalAdminLayout.vue`
- `resources/js/Layouts/StoreLayout.vue`
- `resources/js/Components/UI/`
- `resources/js/Components/Modal.vue`, `Pagination.vue`, `EmptyState.vue`
- `resources/js/Utils/formatters.js`
- `resources/js/Pages/Pos/Terminal.vue`
- `docs/informes/F13-rediseno-visual-pos.md`

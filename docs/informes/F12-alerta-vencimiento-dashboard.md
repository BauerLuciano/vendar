# F12 - Alerta de vencimiento de suscripción en Dashboard

## Objetivo
Mostrar en el Dashboard un banner de aviso de vencimiento de suscripción (mediante `dashboard`), calculado con datos reales del comercio, con niveles progresivos según los días restantes y botón "Renovar plan" que lleva al flujo existente de `/mi-plan`. No se modificó el flujo de Mercado Pago ya resuelto en la fase anterior (back_urls → `/retorno` + webhook).

## Archivos creados
- `docs/informes/F12-alerta-vencimiento-dashboard.md` (este informe)

## Archivos modificados
- `app/Http/Controllers/DashboardController.php`
  - Import de `App\Models\Comercio`.
  - En `index()`: se calcula `suscripcionAlerta` y se pasa como prop de Inertia `'suscripcionAlerta'`.
  - Nuevo método privado `suscripcionAlerta(?int $comercioId): ?array`: carga el comercio autenticado (`Comercio::find($comercioId)`), calcula días restantes contra `vencimiento_pago` con la timezone de la app y devuelve `null` si supera los 10 días o no hay vencimiento.
  - Nuevo método privado `alertaSuscripcion(...)`: arma el array con `nivel`, `dias_restantes`, `plan_nombre` y `mensaje`.
- `resources/js/Pages/Dashboard.vue`
  - Nueva prop `suscripcionAlerta` (Object, default `null`).
  - Computeds `nivelSuscripcion`, `clasesBannerSuscripcion`, `clasesIconoSuscripcion`, `clasesTextoSuscripcion`, `clasesSubTextoSuscripcion`, `clasesBotonSuscripcion` y `textoPlazoSuscripcion` (mapean color/título por nivel: aviso→ámbar, advertencia→naranja, urgente→rosa, vencido→rojo).
  - Banner horizontal entre el header de página ("Panel de Control") y el banner de onboarding/tarjetas KPI, con icono de reloj, mensaje, texto secundario "Plan X · vence en Y días" y botón `Link` a `route('suscripcion.mi-plan')` ("Renovar plan").
- `tests/Feature/Modulo8_SuscripcionesTest.php`
  - 8 tests nuevos: P7.6.1 (11 días → oculto), P7.6.2 (10 días → aviso), P7.6.3 (7 días → aviso), P7.6.4 (3 días → advertencia), P7.6.5 (1 día → urgente "mañana"), P7.6.6 (hoy → urgente "hoy"), P7.6.7 (vencido → nivel "vencido"), P7.6.8 (sin vencimiento → oculto).

## Decisiones técnicas
- Cálculo de días restantes: `now()->startOfDay()->diffInDays($vencimiento, false)` con timezone `America/Argentina/Buenos_Aires` (mismo criterio que el resto del sistema). El primer intento usó los operandos invertidos y daba negativo para vencimientos futuros; se corrigió.
- `Comercio` tiene una columna string `plan` además de la relación `plan()`; se accede a la relación mediante `$comercio->plan()->value('nombre')` para evitar colisión de atributo vs relación (`?->nombre` sobre la columna string lanzaba "Attempt to read property 'nombre' on string").
- Niveles: `>10` → oculto; `10..4` → aviso; `3..2` → advertencia; `1` → urgente "mañana"; `0` → urgente "hoy"; `<0` → vencido. Las cuentas efectivamente vencidas son bloqueadas por `VerificarEstadoCuenta` (que corta cuando `hoy > vencimiento_endOfDay`); el nivel "vencido" queda como respaldo para casos donde el middleware no bloquea (p. ej. sucursal sin comercio resuelto) y es coherente con la regla de que el banner es preaviso.
- Los banners siguen el patrón visual existente (Onboarding / OC pendientes): `rounded-3xl`, bordes pastel, icono en caja redondeada, texto con `tracking-widest`, botón con `rounded-xl`. Sin dependencias nuevas; se reutiliza el `Link` de Inertia.
- El vencimiento "hoy" no queda bloqueado por el middleware (usa `endOfDay()`), por lo que el banner de urgencia diaria es alcanzable.
- En los tests, el caso "vencido" se cubre resolviendo la sucursal a un id inexistente (`session(['sucursal_activa_id' => 999999])`), reproduciendo el escenario en el que el middleware no bloquea y la lógica del banner igual responde.

## Bugs corregidos
- Cálculo invertido de `diffInDays` (vencimientos futuros daban negativo y caían en el nivel "vencido").
- Colisión de la columna `plan` (string) con la relación `plan()` en `Comercio`, que rompía la lectura del nombre del plan.
- Uso de `whereContains` sobre prop String en las aserciones Inertia (compara elementos completos, no substrings); se reemplazó por closures con `str_contains`.

## Pruebas ejecutadas
- `docker exec vendar-app-laravel.test-1 php artisan test --filter=Modulo8_SuscripcionesTest`
  - Resultado: 31 tests OK (234 assertions), solo warnings de deprecación preexistentes (p. ej. PDO::MYSQL_ATTR).
- `npm run build`
  - Resultado: compilación OK (built in 35.10s).
- `php -l` en el controlador: sin errores de sintaxis.

## Resultados
- Banner funcional con niveles según días restantes, mensajes exactos del spec y colores progresivos.
- Se oculta automáticamente cuando el vencimiento pasa a >10 días (p. ej. tras renovar).
- No se tocó Mercado Pago, `pagarPlan()`, `/retorno`, webhook, `auto_return`, ngrok ni infraestructura.

## Criterios de aceptación
- [x] El banner aparece debajo del header de página y antes de las tarjetas KPI.
- [x] Se cumple la grilla de niveles: >10 oculto, 10/7 aviso, 3 advertencia, 1/hoy urgente, vencido.
- [x] Mensajes y botón "Renovar plan" → `/mi-plan` (flujo de pago existente).
- [x] Datos reales del comercio (plan, vencimiento, status), sin hardcodeo ni consultas duplicadas.
- [x] Sin conflicto con `Suspendido.vue` (cuentas bloqueadas no llegan al Dashboard).
- [x] Tests del módulo y build en verde.

## Pendientes de la siguiente fase
- Verificación manual E2E del retorno post-pago vía `/retorno` + webhook (con cuenta real).
- (Opcional) Mostrar el banner también en otras páginas si el negocio lo pide, reutilizando la prop compartida.
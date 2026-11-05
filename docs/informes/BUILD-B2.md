# BUILD B2 — Unicidad de nombres de cajas por local

**Fecha:** 2026-11-05  
**Alcance:** únicamente nombres de cajas físicas por `sucursal_id` y aislamiento de las operaciones de cajas. No se modificaron apertura/cierre, turnos, movimientos de efectivo, ventas, medios de pago, arqueos, reportes ni otros módulos.

---

## 1. Objetivo

Permitir el mismo nombre de caja en locales diferentes y rechazar nombres equivalentes dentro del mismo local, tanto al crear como al editar.

Criterio aplicado:

- Mismo nombre en locales diferentes: permitido.
- Mismo nombre en el mismo local: rechazado.
- Comparación sin distinguir mayúsculas/minúsculas.
- Se recortan espacios al inicio y al final.
- Una caja soft-deleted (`deleted_at`) no bloquea el nombre.
- Al editar se excluye la propia caja.
- El local debe pertenecer al comercio del actor, salvo Administrador Global.
- Laravel y PostgreSQL usan exactamente el mismo criterio de comparación.

---

## 2. Diagnóstico inicial

### 2.1 Estado del módulo antes de B2

- `Caja` usa `SoftDeletes` y además tiene un campo operativo `estado`.
- La UI llama “baja lógica” a `toggleEstado`, que setea `estado = false`; **no** setea `deleted_at`.
- `destroy()` sí hace soft-delete real, pero la UI no expone esa acción.
- No existía validación de nombre único ni índice único sobre `cajas`.
- `store` validaba que la sucursal existiera y fuera `punto_de_venta`, pero no validaba el comercio.
- `update`, `toggleEstado` y `destroy` usaban `$user->branch?->comercio_id`; si el usuario no tenía branch, la comprobación se salteaba.
- `update` ignoraba el `sucursal_id` enviado.
- `SuperAdmin` era tratado como “jefe” y el listado no filtraba por comercio.

### 2.2 Duplicados detectados en desarrollo (`vendar`)

| Local | Comercio | Nombre normalizado | IDs | Nombres | Estado | Turnos |
|---|---|---|---:|---|---|---:|
| Casa Central (sucursal 1) | Kiosco RckOlv (comercio 1) | `caja 1` | 2 y 3 | “Caja 1” y “Caja 1” | Ambas activas, `deleted_at NULL` | ID 2: 1 turno · ID 3: 0 turnos |

- Total de cajas en `vendar`: 3.
- Cajas soft-deleted: 0.
- La caja ID 1 (“Caja 1”) pertenece a la sucursal 2; no era duplicado porque es otro local.
- No había otros duplicados por `sucursal_id + lower(btrim(nombre))`.
- En `testing`: 0 cajas, sin duplicados.

### 2.3 Resolución autorizada del duplicado

Con autorización explícita del usuario se renombró la caja **ID 3** de “Caja 1” a **“Caja 1 Bis”**.

- Se eligió la ID 3 porque no tiene turnos asociados.
- La ID 2 conserva su nombre y su turno histórico.
- No se eliminó ni se alteró ningún registro histórico.
- El cambio se hizo vía Eloquent, por lo que quedó registrado por `Auditable` en `activity_log` (evento `updated`, subject `App\Models\Caja`, ID 3).
- La verificación posterior devolvió cero duplicados activos.

**Backups previos:**

```
/tmp/opencode/vendar_b2_20261105_161017.dump
/tmp/opencode/vendar_b2_testing_20261105_161017.dump
```

---

## 3. Archivos creados

- `database/migrations/2026_11_05_000002_add_unique_caja_name_per_sucursal.php`
- `tests/Feature/BuildB2_CajasUnicidadTest.php`
- `docs/informes/BUILD-B2.md`

## 4. Archivos modificados

- `app/Http/Controllers/CajaController.php`

No se modificó `resources/js/Pages/Cajas/Index.vue`: el formulario ya mostraba `form.errors.nombre` debajo del campo Nombre y `form.errors.sucursal_id` debajo de Sucursal. El error del backend se integra sin cambios visuales.

---

## 5. Validaciones implementadas en Laravel

### 5.1 Alta (`store`)

- `sucursal_id` obligatorio, entero, existente, `tipo = punto_de_venta`.
- Para actores de comercio, la sucursal debe pertenecer a su `comercio_id`.
- `nombre` obligatorio, string, máximo 255.
- Validación de unicidad por `sucursal_id`, case-insensitive, ignorando soft-deleted.
- Normalización del nombre con `trim` antes de validar y guardar.

### 5.2 Edición (`update`)

- Se autoriza primero que la caja pertenezca al comercio del actor.
- Se valida `nombre` con el mismo criterio, excluyendo el ID actual.
- Si se envía `sucursal_id`, se valida que sea un punto de venta del comercio permitido.
- El cambio de sucursal dentro del mismo comercio se permite y la unicidad se evalúa contra la sucursal destino.
- Un `sucursal_id` de otro comercio se rechaza con error de validación.
- Operar sobre una caja de otro comercio devuelve `403`.

### 5.3 Otras operaciones

- `toggleEstado` y `destroy` ahora usan la misma autorización de comercio.
- Se eliminó el bypass previo que ocurría cuando el usuario no tenía `branch`.
- El Administrador Global conserva alcance de plataforma; los usuarios de comercio quedan restringidos a su comercio.

### 5.4 Mensajes

- Nombre duplicado: `Ya existe una caja con ese nombre en este local.`
- Sucursal inválida o ajena: `La sucursal seleccionada no es válida, no es un punto de venta o no pertenece a tu comercio.`
- No se exponen errores SQL ni detalles técnicos de PostgreSQL.

### 5.5 Concurrencia

Si dos solicitudes simultáneas pasan la validación y una viola el índice único, se captura la `QueryException` con SQLSTATE `23505` y se devuelve el mismo error de validación sobre `nombre`.

---

## 6. Restricción en PostgreSQL

**Índice creado:**

```sql
CREATE UNIQUE INDEX cajas_sucursal_nombre_unique
ON cajas (sucursal_id, lower(btrim(nombre)))
WHERE deleted_at IS NULL;
```

- Considera `sucursal_id`.
- No distingue mayúsculas/minúsculas.
- Ignora espacios al inicio y al final.
- Solo aplica a filas con `deleted_at IS NULL`.
- Permite el mismo nombre en locales diferentes.
- No es una restricción global sobre `nombre`.

La migración incluye una verificación previa: si detecta duplicados activos, lanza una excepción con el detalle y no crea el índice.

**Rollback:**

```sql
DROP INDEX IF EXISTS cajas_sucursal_nombre_unique;
```

---

## 7. Normalización utilizada

- Laravel recorta únicamente espacios al inicio y al final con `trim()`.
- No se colapsan espacios internos.
- PostgreSQL aplica `btrim(nombre)` y `lower(...)`.
- La comparación en Laravel usa `lower(btrim(?))` directamente en SQL para que el criterio sea el mismo que el del índice.
- No se normaliza el nombre a minúsculas al guardar: se conserva el nombre original del usuario, recortado en los extremos.

---

## 8. Comportamiento de SoftDeletes

- El índice usa `WHERE deleted_at IS NULL`.
- Una caja eliminada con `delete()` deja de bloquear el nombre.
- La fila sigue existiendo y es consultable con `withTrashed()`.
- Una caja con `estado = false` **sigue bloqueando** el nombre, porque no está soft-deleted.
- La “baja lógica” visible en la UI usa `estado`, no `deleted_at`; por lo tanto, inactivar una caja desde la UI no libera el nombre. Esto respeta la especificación de B2, que pidió explícitamente `deleted_at IS NULL`.
- `destroy()` sí libera el nombre, pero la UI no expone esa acción.

---

## 9. Pruebas ejecutadas

**Comando:**

```bash
docker exec vendar-laravel.test-1 php artisan test tests/Feature/BuildB2_CajasUnicidadTest.php
```

**Resultado:** 13 pruebas, 70 aserciones, todas aprobadas. El runner las marca como `deprecated` por la constante `PDO::MYSQL_ATTR_SSL_CA` en PHP 8.5, el mismo comportamiento preexistente del entorno; no son fallos.

| # | Prueba | Resultado |
|---|---|---|
| 1 | Mismo nombre en locales diferentes | Aprobada |
| 2 | Mismo nombre en el mismo local | Aprobada |
| 3 | Diferencias de mayúsculas/minúsculas | Aprobada |
| 4 | Espacios sobrantes equivalentes | Aprobada |
| 5 | Editar conservando el propio nombre | Aprobada |
| 6 | Editar con el nombre de otra caja activa del mismo local | Aprobada |
| 7 | Mover una caja entre locales del mismo comercio | Aprobada |
| 8 | Reutilizar nombre de caja soft-deleted | Aprobada |
| 9 | Operar sobre caja de otro comercio | Aprobada |
| 10 | Manipular `sucursal_id` hacia otro comercio | Aprobada |
| 11 | Insertar duplicado directo en PostgreSQL | Aprobada |
| 12 | `SuperAdmin` solo lista cajas de su comercio | Aprobada |
| 13 | Cajas históricas siguen consultables | Aprobada |

También se verificó sintaxis con `php -l` sobre el controlador y la migración.

---

## 10. Estado de la migración y rollback

### Desarrollo (`vendar`)

- Migración aplicada con `php artisan migrate --force`.
- Índice verificado en `pg_indexes`:

```
cajas_sucursal_nombre_unique
CREATE UNIQUE INDEX cajas_sucursal_nombre_unique ON public.cajas
USING btree (sucursal_id, lower(btrim((nombre)::text)))
WHERE (deleted_at IS NULL)
```

### Testing (`testing`)

- Migración aplicada.
- Rollback ejecutado con `migrate:rollback --step=1 --force`.
- Se verificó que el índice desapareció.
- Migración reaplicada para dejar la base de pruebas lista.

### Datos

- No se eliminaron ni alteraron registros históricos.
- El único cambio de datos fue el renombre autorizado de la caja ID 3 a “Caja 1 Bis”.

---

## 11. Riesgos y pendientes

- **`estado` vs `deleted_at`:** inactivar una caja desde la UI no libera el nombre. Si se quiere que `estado = false` también libere el nombre, hay que cambiar el predicado del índice y la validación; no se hizo porque B2 pidió `deleted_at IS NULL`.
- **Administrador Global:** mantiene alcance de plataforma y puede operar en cualquier comercio. Los usuarios de comercio quedan restringidos a su propio `comercio_id`.
- **Cambio de sucursal al editar:** ahora se permite dentro del mismo comercio y se valida unicidad en la sucursal destino. Un cambio hacia otro comercio se rechaza.
- **Normalización Unicode:** se usan `trim` de PHP y `btrim` de PostgreSQL para espacios estándar. Espacios Unicode especiales no se recortan; el criterio se mantiene igual entre Laravel y el índice para el caso normal.
- **Concurrencia:** el manejo de `23505` está implementado, pero no se ejecutó una prueba de carrera real. Se recomienda una prueba de carga o concurrencia si el módulo lo requiere.
- **Suite completa:** no se ejecutó por indicación expresa. El baseline conocido tiene fallos preexistentes ajenos a B2.
- **Otras pantallas:** `cierreDiario()` conserva su lógica previa de alcance por comercio; no se modificó por estar fuera del alcance de B2.

---

## 12. Criterios de aceptación

- [x] Unicidad por local.
- [x] Nombres iguales permitidos entre locales diferentes.
- [x] Duplicados rechazados dentro del mismo local.
- [x] Laravel y PostgreSQL aplican el mismo criterio.
- [x] Cajas soft-deleted no bloquean el nombre.
- [x] No se puede manipular `sucursal_id` para operar sobre otro comercio.
- [x] Pruebas específicas de B2 aprobadas.
- [x] Módulos ajenos al alcance sin modificar.
- [x] Informe final creado.

---

## 13. Verificaciones adicionales recomendadas

- Ejecutar la suite completa en un entorno controlado y comparar contra el baseline conocido.
- Probar en navegador el alta y edición de cajas con error de nombre duplicado.
- Probar el flujo de “inactivar” y confirmar que el nombre sigue reservado, según la decisión de B2.
- Probar concurrencia real con dos altas simultáneas del mismo nombre.
- Revisar el comportamiento de `cierreDiario` para usuarios históricos sin `branch` en una tarea aparte, si se detecta fuga de datos.

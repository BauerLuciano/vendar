# BUILD B1 — Seguridad multi-tenant en usuarios/roles y nuevo formulario de usuario

**Fecha:** 2026-11-05  
**Alcance:** únicamente gestión de usuarios y roles. No se avanzó a B2 ni se modificaron POS, Caja, Compras, Productos, Stock, Lotes, Clientes, Pedidos web, Facturación ni Suscripciones.

---

## 1. Objetivo

Cerrar las brechas de aislamiento multi-tenant en la administración de usuarios y roles, y reemplazar el formulario de alta/edición de usuarios por uno que:

- Separe nombre y apellido.
- Permita teléfono opcional.
- Valide en tiempo real con mensajes claros en español.
- Oblique al Administrador Global a elegir comercio.
- Diferencie sucursal base de sucursales adicionales.
- No permita asignar roles de plataforma desde un comercio.
- No rompa usuarios históricos sin apellido/teléfono.

---

## 2. Problemas detectados y corregidos

| Problema | Riesgo | Corrección |
|---|---|---|
| `index` de usuarios no filtraba por comercio para actores de comercio | Fuga de datos entre tenants | Scope obligatorio por `comercio_id` en backend |
| `show` no estaba implementado | La UI no podía consultar un usuario de forma segura | Se implementó `show` con autorización y JSON acotado |
| `update`/`destroy` no validaban pertenencia al comercio | Un actor podía modificar/eliminar usuarios de otro comercio manipulando el ID | `autorizarAccesoAlUsuario()` valida pertenencia antes de cualquier cambio |
| El comercio destino se tomaba del request sin control | Un tenant podía crear usuarios en otro comercio | Para tenants el comercio destino siempre es el del actor; el `comercio_id` enviado se ignora |
| `branch_id` y `sucursales[]` no se validaban contra el comercio destino | Se podían asignar sucursales de otro comercio | Reglas `exists` acotadas por `comercio_id` |
| Un tenant podía asignar `Administrador Global` | Escalamiento de privilegios | Lista blanca de roles asignables |
| Roles de sistema editables/renombrables/eliminables desde un comercio | Rotura de la seguridad base | `Administrador Global` y `SuperAdmin` protegidos |
| Formulario sin apellido/teléfono ni confirmación de contraseña | Datos incompletos y errores de tipeo | Campos nuevos + confirmación + validaciones |
| Sin auditoría explícita de acciones del Administrador Global | Falta de trazabilidad | `activity()` con descripción explícita |

---

## 3. Diseño de aislamiento multi-tenant

### 3.1 Principio general

El aislamiento se resuelve **en el backend**. Vue solo muestra/oculta opciones; nunca es la única defensa.

### 3.2 Actor de comercio

- `comercioDelActor()` obtiene el comercio desde `users.comercio_id` o, para usuarios históricos, desde `branch.comercio_id`.
- Si el actor no tiene comercio resoluble, recibe `403`.
- `index` aplica `where('comercio_id', $comercioId)`.
- Para usuarios históricos con `comercio_id = NULL` se acepta únicamente si su sucursal pertenece al comercio del actor. **Nunca se usa una condición que abra el acceso cuando `comercio_id` es null.**
- `show`, `update` y `destroy` llaman a `autorizarAccesoAlUsuario()` antes de operar.
- Al crear/editar, el `comercio_id` destino para un tenant es siempre el del actor. Cualquier `comercio_id` enviado por HTTP se ignora.
- `branch_id` y cada elemento de `sucursales[]` deben existir y pertenecer al comercio destino.

### 3.3 Administrador Global

- Se detecta por el rol `Administrador Global`.
- Puede listar usuarios de todos los comercios.
- En el formulario debe elegir explícitamente el comercio.
- Si crea sin comercio, la validación falla con `comercio_id`.
- Si edita y no envía comercio, se resuelve desde el usuario objetivo (o su sucursal).
- Sus acciones de alta/edición/baja se registran en `activity_log` con:
  `usuario_administrado_por_administrador_global`.
- No se usa `branch_id` como prueba de tenant para el Administrador Global.

### 3.4 Usuarios de plataforma y clientes de tienda

Los usuarios con rol `Administrador Global` o `cliente` no se administran desde esta pantalla:

- No aparecen en el listado.
- No se pueden consultar, editar ni eliminar por ID directo.
- Esto evita degradar accidentalmente una cuenta de plataforma o convertir un cliente de la tienda pública en usuario interno desde un formulario que no puede representar esos roles.

---

## 4. Protección de roles

Roles protegidos: **`Administrador Global`** y **`SuperAdmin`**.

| Acción | Tenant (`SuperAdmin` de comercio) | Administrador Global |
|---|---|---|
| Ver roles | Sí | Sí |
| Crear rol con nombre protegido | No | No |
| Renombrar rol protegido | No (`403`) | No |
| Editar permisos de rol protegido | No (`403`) | Sí, manteniendo el nombre |
| Eliminar rol protegido | No | No |

Decisiones:

- La comparación de nombres protegidos es case-insensitive.
- La unicidad de nombres de rol es case-insensitive.
- No se modificó `config/permission.php`, no se cambió `teams` y no se rediseñó Spatie.
- `SuperAdmin` sigue siendo asignable dentro de un comercio.
- `cliente` no es asignable desde esta pantalla.

---

## 5. Comportamiento del formulario

Archivo principal: `resources/js/Pages/Usuarios/Componentes/ModalUsuario.vue`.

### 5.1 Campos

1. Comercio (solo Administrador Global, obligatorio).
2. Nombre.
3. Apellido (opcional).
4. Email.
5. Teléfono (opcional).
6. Rol / Perfil.
7. Sucursal base.
8. Sucursales adicionales (multi-select).
9. Contraseña.
10. Confirmar contraseña.

### 5.2 Sucursal base y adicionales

- La sucursal base se elige de todas las sucursales del comercio.
- Las sucursales adicionales excluyen la base.
- Si el comercio solo tiene una sucursal (o no hay otras), se muestra exactamente:
  > No hay otras sucursales disponibles. Este usuario tendrá acceso únicamente a su sucursal base.
- Si el Administrador Global todavía no eligió comercio, se muestra:
  > Seleccioná un comercio para ver sus sucursales disponibles.
- Se muestra una referencia visual de la sucursal base seleccionada.
- Al cambiar de comercio se limpian base y adicionales para evitar datos cruzados.

### 5.3 Contraseña

- Obligatoria al crear.
- Opcional al editar.
- Confirmación obligatoria cuando se escribe una contraseña.
- Si se edita sin escribir contraseña, la contraseña anterior se conserva.
- Nunca se muestra en texto plano.

### 5.4 Nombre y apellido

- `name` sigue existiendo y se mantiene como nombre completo para compatibilidad con el resto del sistema.
- Al guardar, el backend compone `name = nombre + ' ' + apellido` cuando hay apellido.
- Al editar, el frontend quita el sufijo del apellido del campo Nombre para no duplicarlo visualmente.
- Usuarios históricos sin `apellido` siguen funcionando: el campo queda vacío y no se hace backfill falso.

### 5.5 Listado

- El listado muestra comercio cuando el actor es Administrador Global.
- Muestra teléfono cuando existe.
- Se agregó filtro por comercio para el Administrador Global.
- El buscador incluye nombre, apellido, email e ID.

---

## 6. Validaciones

### 6.1 Nombre y apellido

- Solo letras Unicode, espacios, apóstrofes y guiones.
- Acepta acentos, `ñ`, nombres compuestos y apellidos compuestos.
- Rechaza números y símbolos no permitidos.
- Longitud: 2 a 100 caracteres.
- Espacios múltiples se colapsan a uno.
- Apellido es opcional.

### 6.2 Email

- Obligatorio.
- Formato válido.
- Máximo 255 caracteres.
- Unicidad global case-insensitive.
- Se recorta y se pasa a minúsculas al crear/actualizar.
- Si el email no cambió salvo mayúsculas, se conserva el valor original para no romper accesos históricos.

### 6.3 Teléfono

- Opcional.
- Solo dígitos, `+`, `-`, `()`, espacios y puntos.
- Entre 8 y 15 dígitos.
- No se alteran los dígitos ni el formato real; solo se recortan extremos y se colapsan espacios internos.

### 6.4 Contraseña

- Mínimo 8 caracteres.
- Confirmación obligatoria y coincidente.
- Opcional en edición.

### 6.5 Sucursales y roles

- `branch_id` obligatorio y perteneciente al comercio destino.
- `sucursales[]` opcional; cada ID debe pertenecer al comercio destino.
- `rol` obligatorio y dentro de la lista de roles asignables.
- Roles no asignables: `Administrador Global`, `cliente`.

### 6.6 Mensajes

Los mensajes visibles son en español y no se muestran errores técnicos crudos de Laravel. Se personalizaron las reglas principales de nombre, apellido, email, teléfono, contraseña, sucursal, rol y comercio. El frontend además valida en tiempo real y conserva los errores del backend cuando corresponde.

---

## 7. Migración

**Archivo:** `database/migrations/2026_11_05_000001_add_apellido_and_telefono_to_users_table.php`

- Agrega `apellido` `varchar(100) NULL`.
- Agrega `telefono` `varchar(30) NULL`.
- No modifica ni elimina columnas existentes.
- No hace backfill.
- `down()` elimina ambas columnas.

**Estado previo verificado:**

- 9 usuarios en la base de desarrollo.
- 2 usuarios con `comercio_id = NULL`.
- Sin emails duplicados comparando case-insensitive.
- Sin inconsistencias entre `comercio_id` de usuario y `comercio_id` de sucursal.
- Tabla `activity_log` disponible.

**Backup previo a migrar:**

```
/tmp/opencode/vendar_backup_20261105_153048.dump
```

**Aplicada en:**

- Base `vendar` (desarrollo).
- Base `testing`.

`migrate --pretend` mostró únicamente dos `ALTER TABLE ... ADD COLUMN ... NULL`, sin operaciones destructivas.

---

## 8. Archivos creados

- `database/migrations/2026_11_05_000001_add_apellido_and_telefono_to_users_table.php`
- `tests/Feature/BuildB1_UsuariosSeguridadTest.php`
- `docs/informes/BUILD-B1.md`

## 9. Archivos modificados

- `app/Http/Controllers/UsuarioController.php`
- `app/Http/Controllers/RoleController.php`
- `app/Models/User.php`
- `resources/js/Pages/Usuarios/Componentes/ModalUsuario.vue`
- `resources/js/Pages/Usuarios/Index.vue`
- `resources/js/Pages/Roles/Index.vue`
- `resources/js/Pages/Roles/Componentes/ModalRol.vue`
- `tests/Feature/Modulo6_UsuariosTest.php`

No se modificaron controladores, modelos, vistas ni rutas de los módulos fuera de alcance.

---

## 10. Pruebas ejecutadas y resultados

### 10.1 Sintaxis PHP

```
php -l app/Http/Controllers/UsuarioController.php   → sin errores
php -l app/Http/Controllers/RoleController.php      → sin errores
php -l tests/Feature/BuildB1_UsuariosSeguridadTest.php → sin errores
```

### 10.2 Tests dirigidos

| Suite | Resultado |
|---|---|
| `BuildB1_UsuariosSeguridadTest` | **28 tests, 93 assertions — todos pasan** |
| `Modulo6_UsuariosTest` | **6 tests, 10 assertions — todos pasan** |
| `CrossTenantAttackTest` | 9 pasan; `ct10` falla por causa preexistente |
| `Modulo6` + `B1` posteriores a los últimos ajustes | Sin regresiones |

Cobertura de `BuildB1_UsuariosSeguridadTest`:

- Aislamiento de listado entre comercio A y B.
- `show`, `update` y `destroy` cruzados → `403`.
- Sucursal base y adicional de otro comercio → error de validación.
- Intento de asignar `Administrador Global` desde un comercio → error.
- Intento de cambiar `comercio_id` por request directo → se ignora.
- Administrador Global lista ambos comercios.
- Administrador Global crea usuario eligiendo comercio y sucursales.
- Administrador Global sin comercio → error.
- Administrador Global con sucursal de otro comercio → error.
- Administrador Global conserva `show` y `update`.
- Nombre con números, apellido con símbolos, email inválido, email duplicado case-insensitive, teléfono inválido, contraseñas distintas.
- Teléfono válido se guarda sin alterar.
- Usuario histórico sin apellido sigue funcionando.
- Edición sin contraseña conserva el hash anterior.
- Roles de sistema no se editan, renombran ni eliminan desde un comercio.
- No se puede crear un rol con nombre protegido.
- Los permisos existentes siguen funcionando.

### 10.3 Suite completa

```
php artisan test
Tests: 376 deprecated, 23 failed, 1 risky, 1 passed (1415 assertions)
Duration: 628.71 s
```

**Importante:** “deprecated” es el estado que PHPUnit asigna por la deprecación de `PDO::MYSQL_ATTR_SSL_CA` en PHP 8.5. Es el mismo comportamiento de la base (`HEAD`), no un fallo funcional de B1.

### 10.4 Fallos preexistentes verificados

Se verificaron contra `HEAD` usando `git stash` (ida y vuelta, con restauración exitosa). Los 23 fallos existen también sin los cambios de B1:

| Suite | Fallos |
|---|---:|
| `Auth/PasswordResetTest` | 4 |
| `Auth/RegistrationTest` | 1 |
| `CrossTenantAttackTest` (`ct10`) | 1 |
| `Modulo1_ProductosTest` | 1 |
| `Modulo3_CajaTest` | 3 |
| `Modulo4_PedidosWebTest` | 2 |
| `Modulo7_TransferenciasTest` | 1 |
| `MultiTenantIsolationTest` | 1 |
| `VentasCanceladasHistorialTest` | 8 |
| `WebhookSeguridadTest` | 1 |
| **Total** | **23** |

Ninguno pertenece a los módulos tocados por B1. No se ocultaron ni se marcaron como “esperados”: simplemente se documentan como preexistentes.

### 10.5 Build de frontend

```
npm run build
✓ built in 7.52s
```

Sin errores de compilación.

---

## 11. Criterios de aceptación

- [x] Un usuario de comercio solo lista usuarios de su comercio.
- [x] Un usuario de comercio solo consulta/edita/elimina usuarios de su comercio.
- [x] IDs manipulados por HTTP devuelven `403`.
- [x] El backend impone el aislamiento; Vue no es la única defensa.
- [x] No se abre el acceso cuando `comercio_id` es null.
- [x] El Administrador Global conserva administración de plataforma.
- [x] El Administrador Global debe elegir comercio al crear.
- [x] Las acciones del Administrador Global quedan auditadas.
- [x] Un tenant no puede asignar `Administrador Global` ni `cliente`.
- [x] `branch_id` y `sucursales[]` se validan contra el comercio destino.
- [x] `Administrador Global` y `SuperAdmin` no se renombran ni eliminan desde un comercio.
- [x] Un tenant no puede cambiar permisos de roles protegidos.
- [x] El Administrador Global puede editar permisos protegidos sin renombrarlos.
- [x] Spatie sigue funcionando; no se cambió `teams`.
- [x] `apellido` y `telefono` son nullable.
- [x] Usuarios existentes sin apellido/teléfono siguen funcionando.
- [x] No hay backfill falso.
- [x] El formulario tiene todos los campos solicitados.
- [x] Validaciones de nombre/apellido, email, teléfono, contraseña y sucursales.
- [x] Mensajes claros en español.
- [x] Estado vacío de sucursales adicionales con el texto exacto solicitado.
- [x] La sucursal base se identifica y se excluye de las adicionales.
- [x] No se modificaron módulos fuera de alcance.
- [x] Tests nuevos de aislamiento, Administrador Global, validaciones y roles.
- [x] Migración no destructiva y con rollback.
- [x] `php artisan test` ejecutado y fallos preexistentes diferenciados.
- [x] `npm run build` ejecutado correctamente.

---

## 12. Riesgos y pendientes

1. **Roles globales de Spatie.** `teams` sigue en `false`. Los roles son globales, por lo que un tenant que crea o edita un rol no protegido impacta a todos los comercios. No se rediseñó porque estaba fuera de alcance y el pedido explícito fue no cambiar la arquitectura de permisos. Queda como riesgo estructural para una fase futura.
2. **`name` como nombre completo.** Se mantiene por compatibilidad. Si otro módulo edita `name` sin tocar `apellido`, el formulario podría mostrar el nombre completo en “Nombre” y el apellido aparte. Es un caso borde; no se modificaron otros módulos.
3. **Cuentas de plataforma y clientes de tienda.** No se administran desde esta pantalla. Si en el futuro se necesita gestionarlas, conviene una pantalla específica con su propio formulario y validaciones.
4. **Contraseña en edición.** Se conserva si el campo queda vacío, pero no se agregó historial de contraseñas ni expiración; no estaba en el alcance.
5. **Datos históricos.** No se hizo backfill de `apellido`/`telefono`. Los usuarios existentes siguen operativos, pero sus datos nuevos quedan incompletos hasta que se editen.
6. **Backup.** El backup previo está en `/tmp/opencode/vendar_backup_20261105_153048.dump`. Es temporal; si se necesita conservarlo, debe moverse a un almacenamiento persistente.
7. **B2.** No se avanzó. El siguiente paso debe ser aprobado explícitamente.

---

## 13. Conclusión

B1 queda implementado y probado: el aislamiento multi-tenant de usuarios está en el backend, los roles de sistema están protegidos, el Administrador Global conserva su alcance con comercio explícito y auditoría, y el formulario de usuario cumple los campos y validaciones solicitados sin romper usuarios históricos ni módulos fuera de alcance.

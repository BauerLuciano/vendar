# Skills de OpenCode en VendAR

**Fecha:** 13/11/2026
**Fase:** incorporación de Skills externas y creación de Skills propias
**Alcance:** configuración y documentación. No se modificó código de aplicación.
**OpenCode:** `v2.1.1`
**Repositorio:** `BauerLuciano/vendar` (`main`)

---

## 1. Objetivo

Incorporar Skills de OpenCode al repositorio para que cualquier desarrollador que abra el
proyecto tenga disponible el mismo conocimiento técnico y las mismas reglas de VendAR,
sin depender de instalaciones globales ni de la memoria del equipo.

Se buscó:

- Reutilizar Skills externas maduras para Laravel/PHP, Vue 3, frontend, PostgreSQL/SQL,
  arquitectura, seguridad, testing, debugging, code review y DevOps.
- Crear cinco Skills propias que documenten la arquitectura, el multi-tenancy, la UI y
  las reglas de negocio reales de VendAR, más la forma correcta de testear.
- Evitar duplicados, Skills genéricas y contenido que no aplique al stack.
- Dejar procedencia, versión y licencia identificables para poder actualizar.

## 2. Alcance y restricciones

Incluido:

- `.opencode/skills/<skill-id>/SKILL.md` y sus referencias.
- `.opencode/skills/PROVENANCE.md`.
- Este informe.

Excluido explícitamente:

- Código de aplicación, modelos, controllers, services, componentes Vue, migraciones.
- Dependencias de Laravel, Vue o Node.
- `AGENTS.md`, BUILD B1/B2/B3 y cualquier documento de arquitectura aprobado.
- Migraciones, builds, seeds o comandos que modifiquen datos.
- Refactors o cambios funcionales.

No se creó `opencode.json` de proyecto: OpenCode V2 descubre automáticamente
`.opencode/skills/<skill-id>/SKILL.md`. La configuración global tampoco es necesaria.

## 3. Cómo funciona la discovery en OpenCode V2

- La Skill se identifica por el **nombre del directorio**, no por el `name` del frontmatter.
- El archivo obligatorio es `SKILL.md`.
- El `description` del frontmatter es lo que usa el agente para decidir cuándo cargarla.
- `license` y `compatibility` se aceptan, pero OpenCode no los interpreta.
- Las referencias relativas (`references/*.md`) se resuelven contra el directorio de la Skill.
- Si dos Skills tienen el mismo ID, gana la fuente de mayor precedencia; por eso no se
  duplican IDs.

## 4. Skills externas incluidas

| # | Skill | Fuente | Commit | Versión | Utilidad en VendAR |
|---|---|---|---|---|---|
| 1 | `laravel-specialist` | Farmage | `2ec1473` | 0.5.0 | Eloquent, Sanctum, colas, APIs, Livewire, tests Laravel |
| 2 | `php-pro` | Farmage | `2ec1473` | 0.5.0 | PHP 8.3+, tipado estricto, PHPStan, PSR, DTOs |
| 3 | `vue-expert-js` | Farmage | `2ec1473` | 0.5.0 | Vue 3 + Composition API en JavaScript, composables, Vite |
| 4 | `frontend-ui-engineering` | Addy Osmani | `301b02b` | 0.6.15 | UI accesible, responsive y con calidad de producción |
| 5 | `postgres-pro` | Farmage | `2ec1473` | 0.5.0 | EXPLAIN, índices, JSONB, replicación, VACUUM |
| 6 | `sql-pro` | Farmage | `2ec1473` | 0.5.0 | Diseño de esquema, queries complejas, ventanas, CTEs |
| 7 | `architecture-designer` | Farmage | `2ec1473` | 0.5.0 | Diseño de arquitectura, ADRs, trade-offs |
| 8 | `secure-code-guardian` | Farmage | `2ec1473` | 0.5.0 | Auth, validación, OWASP Top 10, hashing, JWT |
| 9 | `test-master` | Farmage | `2ec1473` | 0.5.0 | Tests unitarios/integración/E2E, mocks, cobertura |
| 10 | `debugging-wizard` | Farmage | `2ec1473` | 0.5.0 | Stack traces, hipótesis, causa raíz, logs |
| 11 | `code-reviewer` | Farmage | `2ec1473` | 0.5.0 | Revisión de diffs, bugs, seguridad, performance |
| 12 | `feature-forge` | Farmage | `2ec1473` | 0.5.0 | Requerimientos, user stories, EARS, criterios de aceptación |
| 13 | `devops-engineer` | Farmage | `2ec1473` | 0.5.0 | Docker, CI/CD, Kubernetes, Terraform, runbooks |

Procedencia detallada, archivos y verificación de integridad: `.opencode/skills/PROVENANCE.md`.

### Criterio de selección

- **Cobertura del stack real:** Laravel 11 + PHP 8.2 + Vue 3 JavaScript + Inertia 2 +
  Vite 5 + Tailwind 3 + PostgreSQL + Spatie Permission + PHPUnit 10.5.
- **Complementariedad, no solapamiento:**
  - `laravel-specialist` cubre framework y Eloquent; `php-pro`, el lenguaje y el tipado.
  - `postgres-pro` cubre servidor, performance y administración; `sql-pro`, esquema y queries.
  - `secure-code-guardian` implementa seguridad; `code-reviewer` revisa de forma amplia.
- **Licencia verificable:** MIT en ambas fuentes, con `LICENSE` incluido por Skill.
- **Sin scripts ejecutables:** todas son documentación; no agregan superficie de ejecución.

## 5. Skills propias de VendAR

| Skill | Qué resuelve | Archivos de referencia principales |
|---|---|---|
| `vendar-architecture` | Dónde va cada cosa, capas, transacciones, locks, servicios existentes, flujo Planner/Builder/Reviewer | `AGENTS.md`, `routes/web.php`, `bootstrap/app.php`, `app/Services/` |
| `vendar-multitenancy` | Aislamiento por `comercio_id`, matriz de pertenencia, roles, middleware, validación de IDs, tests cross-tenant | `SucursalScopeService`, middleware, `CrossTenantAttackTest` |
| `vendar-ui-guidelines` | Lenguaje visual del panel, POS y tienda; componentes, Tailwind, formatos es-AR, accesibilidad | Layouts, `Components/UI`, `formatters.js`, `Terminal.vue`, F13 |
| `vendar-business-rules` | Reglas verificadas de POS, caja, stock, fiados, compras, pedidos web y suscripciones | Controllers, `VentaOperacionService`, `LoteService`, `SuscripcionService`, enums |
| `vendar-testing` | PHPUnit, base `testing`, `TestCaseMultiTenant`, datos del seeder, matriz de regresión, baseline de fallos | `phpunit.xml`, tests, `QATestDataSeeder`, BUILD-B1 |

### Decisiones de diseño de las Skills propias

- **Referencian, no duplican:** remiten a `AGENTS.md` para el flujo de trabajo y a los
  archivos reales para cada regla.
- **Verificadas contra código:** cada regla de `vendar-business-rules` se contrastó con el
  controller o service que la implementa.
- **Separación por dominio:** arquitectura, tenancy, UI, negocio y testing. Una Skill no
  repite el contenido de otra; se enlazan con `related-skills`.
- **Versionadas:** `metadata.version: 1.0.0` para poder detectar cambios de contenido.
- **En español rioplatense:** mismo tono que `AGENTS.md` y el equipo.

## 6. Skills evaluadas y descartadas

| Skill / fuente | Motivo del descarte |
|---|---|
| `vue-expert` (Farmage) | TypeScript, Pinia y Nuxt; no aplica al proyecto. |
| `typescript-pro` (Farmage) | El proyecto no usa TypeScript. |
| `javascript-pro` (Farmage) | Solapa con `vue-expert-js` y `frontend-ui-engineering`. |
| `database-optimizer` (Farmage) | Solapa con `postgres-pro` y `sql-pro`. |
| `api-designer` (Farmage) | El foco es Inertia, no una API pública nueva. |
| `code-documenter` (Farmage) | Bajo valor frente a los informes de fase existentes. |
| `legacy-modernizer` (Farmage) | No hay modernización activa. |
| `the-fool` (Farmage) | Genérica, sin aporte al stack. |
| `fullstack-guardian` (Farmage) | Solapa con arquitectura, review y seguridad. |
| `security-reviewer` (Farmage) | Solapa con `code-reviewer` y `vendar-multitenancy`. |
| `systematic-debugging` (Flitzrrr) | Duplica `debugging-wizard`. |
| `sentry-find-bugs` / `sentry-code-review` (getsentry) | Duplican debugging/review y requieren Sentry. |
| `vercel-web-design-guidelines` (vercel-labs) | Sin `LICENSE` verificable al momento de evaluar. |
| `anthropic-frontend-design` (DasDigitaleMomentum) | Licencia ambigua y estética que choca con la UI operativa. |
| `opencode-create-plan` (Flitzrrr) | Symlink/submódulo roto: no se pudo obtener el contenido. |
| `execute-work-package` (Flitzrrr) | Requiere infraestructura externa y duplica el flujo de `AGENTS.md`. |
| `roerohan/skills`, `dimitrigilbert/ai-skills`, `rrebollo/opencode-gh-cli` | Sin Skills centrales para el stack o con solapamiento directo. |

También se descartó crear una Skill de “planificación” separada: el flujo
Planner/Builder/Reviewer ya está en `AGENTS.md` y `feature-forge` cubre la parte de
requerimientos. Duplicarlo generaría dos fuentes de verdad.

## 7. Inventario final

```
.opencode/skills/
├── PROVENANCE.md
├── architecture-designer/
├── code-reviewer/
├── debugging-wizard/
├── devops-engineer/
├── feature-forge/
├── frontend-ui-engineering/
├── laravel-specialist/
├── php-pro/
├── postgres-pro/
├── secure-code-guardian/
├── sql-pro/
├── test-master/
├── vendar-architecture/
├── vendar-business-rules/
├── vendar-multitenancy/
├── vendar-testing/
├── vendar-ui-guidelines/
└── vue-expert-js/
```

- **18 Skills** en total: 13 externas + 5 propias.
- **102 archivos**, ~776 KB.
- Todas tienen `SKILL.md`; las externas conservan su `LICENSE`.
- Sin symlinks.
- Sin IDs duplicados.

### Archivos creados

- `docs/informes/OPENCODE-SKILLS.md`
- `.opencode/skills/PROVENANCE.md`
- `.opencode/skills/vendar-architecture/SKILL.md`
- `.opencode/skills/vendar-multitenancy/SKILL.md`
- `.opencode/skills/vendar-ui-guidelines/SKILL.md`
- `.opencode/skills/vendar-business-rules/SKILL.md`
- `.opencode/skills/vendar-testing/SKILL.md`
- `.opencode/skills/laravel-specialist/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/php-pro/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/vue-expert-js/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/postgres-pro/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/sql-pro/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/architecture-designer/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/code-reviewer/` (`SKILL.md`, 6 referencias, `LICENSE`)
- `.opencode/skills/secure-code-guardian/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/test-master/` (`SKILL.md`, 10 referencias, `LICENSE`)
- `.opencode/skills/debugging-wizard/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/feature-forge/` (`SKILL.md`, 5 referencias, `LICENSE`)
- `.opencode/skills/devops-engineer/` (`SKILL.md`, 9 referencias, `LICENSE`)
- `.opencode/skills/frontend-ui-engineering/` (`SKILL.md`, `LICENSE`)

### Archivos modificados

Ninguno. No se tocó código de aplicación, `AGENTS.md`, BUILD ni dependencias.

## 8. Cómo usarlas

### Uso normal

El agente carga la Skill cuando la tarea coincide con su `description`. No hace falta
invocarla a mano. Ejemplos de pedidos que la activan:

- “Agregá un endpoint para ajustar stock” → `vendar-architecture`,
  `vendar-multitenancy`, `vendar-business-rules`, `vendar-testing`.
- “¿Por qué falla este test de caja?” → `vendar-testing`, `debugging-wizard`.
- “Revisá este diff antes de mergear” → `code-reviewer`, `vendar-multitenancy`.
- “Diseñá la pantalla de devoluciones” → `vendar-ui-guidelines`,
  `frontend-ui-engineering`.
- “Optimizá esta query de ventas” → `postgres-pro`, `sql-pro`, `vendar-multitenancy`.

### Invocación explícita

Si querés forzarla, nombrala en el pedido:

```
Usá la skill vendar-business-rules y decime si esta devolución es válida.
Usá la skill vendar-testing para escribir el test cross-tenant de este endpoint.
Usá la skill laravel-specialist para revisar este modelo.
```

En OpenCode, la Skill también se puede cargar con la herramienta `skill` usando el ID
del directorio.

### Para un segundo desarrollador

1. Clonar el repo y abrir OpenCode en la raíz del proyecto.
2. Verificar que OpenCode sea V2.1.1 o superior.
3. No hace falta instalar nada global: las Skills viajan con el repo.
4. Pedir la tarea normalmente. Si la Skill no se activa, nombrarla explícitamente.
5. Antes de tocar POS, caja, stock o fiados, pedir explícitamente
   `vendar-business-rules` y `vendar-testing`.
6. Leer `.opencode/skills/PROVENANCE.md` si necesita saber de dónde salió una Skill.

## 9. Cómo actualizar una Skill externa

1. Clonar la fuente en `/tmp` y hacer checkout del commit nuevo.
2. Revisar `CHANGELOG.md` y `LICENSE`.
3. Comparar:

   ```bash
   diff -r /tmp/upstream/skills/laravel-specialist .opencode/skills/laravel-specialist
   ```

4. Copiar solo la Skill afectada, sin symlinks.
5. Verificar que el `name` del frontmatter coincida con el directorio.
6. Actualizar `PROVENANCE.md` (commit, versión, fecha) y este informe.
7. Verificar discovery e invocación.
8. Registrar el cambio en un informe de fase.

No actualices “a main” sin fijar commit: se pierde la reproducibilidad.

## 10. Cómo crear o modificar una Skill propia

1. Crear `.opencode/skills/<id>/SKILL.md`.
2. Usar frontmatter con `name`, `description`, `compatibility: opencode` y `metadata`.
3. Escribir la `description` con disparadores concretos: es lo que usa el agente para
   elegirla.
4. Referenciar archivos reales del repo; no inventar rutas ni reglas.
5. No duplicar `AGENTS.md` ni otras Skills.
6. Probar la invocación con una pregunta real.
7. Subir `metadata.version` si cambia el contenido de forma relevante.
8. Actualizar `PROVENANCE.md` y este informe.

### Cómo desactivar una Skill

Mover su directorio fuera de `.opencode/skills/` o eliminarlo. No hay registro central
que limpiar. Si se elimina una Skill externa, actualizar el inventario y la procedencia.

## 11. Verificación ejecutada

| Verificación | Resultado |
|---|---|
| Cantidad de `SKILL.md` | 18 |
| IDs únicos | Sí, 18/18 |
| `name` del frontmatter = directorio | Sí, 18/18 |
| `description` presente | Sí, 18/18 |
| Frontmatter YAML válido | Sí, 18/18 (descripciones con `:` entre comillas simples) |
| `LICENSE` en Skills externas | Sí, 13/13 |
| Symlinks | Ninguno |
| Discovery de OpenCode V2 | 18/18 detectadas |
| Invocación de Skill propia | `vendar-business-rules` cargada correctamente |
| Invocación de Skill externa | `postgres-pro` cargada correctamente, referencias resueltas |
| Archivos de aplicación modificados | Ninguno (`git status` solo muestra `.opencode/`) |
| Migraciones / builds ejecutados | Ninguno |
| Suite de tests ejecutada | No en esta fase (solo configuración) |

### Comandos de verificación reproducibles

```bash
# 1. Inventario y frontmatter
for d in .opencode/skills/*/; do
  id=$(basename "$d")
  name=$(grep -m1 '^name:' "$d/SKILL.md" | sed 's/^name:[[:space:]]*//')
  printf "%-26s %s\n" "$id" "$name"
done

# 2. Symlinks
find .opencode/skills -type l

# 3. Licencias externas
for s in laravel-specialist php-pro vue-expert-js postgres-pro sql-pro \
         architecture-designer code-reviewer secure-code-guardian test-master \
         debugging-wizard feature-forge devops-engineer frontend-ui-engineering; do
  test -f ".opencode/skills/$s/LICENSE" || echo "Falta LICENSE: $s"
done

# 4. Que no se tocó la app
git status --short

# 5. Discovery + invocación
# Abrir OpenCode y pedir: "Usá la skill vendar-architecture y explicá dónde va un service nuevo"
```

## 12. Resultados

- Las 18 Skills se detectan en OpenCode V2.1.1 sin configuración adicional.
- Las Skills externas conservan contenido, licencia y estructura original.
- Las Skills propias describen el VendAR real, con archivos y reglas verificadas.
- La procedencia queda fijada por commit y versión, no por “latest”.
- El repositorio no cambió funcionalmente: solo se agregó `.opencode/` y este informe.

## 13. Problemas y pendientes

| Tema | Estado |
|---|---|
| `opencode-create-plan` de Flitzrrr | No se pudo clonar (symlink/submódulo roto). Se descartó. |
| `vercel-web-design-guidelines` | Sin `LICENSE` verificable. Se descartó. |
| Suite completa de tests | No se ejecutó en esta fase por ser solo configuración. Baseline conocido: 23 fallos preexistentes (BUILD-B1 §10.4). |
| Skills propias | Se validaron por invocación, no con una suite automática. Próximo paso: usarlas en una fase real y ajustar lo que falte. |
| `AGENTS.md` | No se modificó. Si el equipo quiere que el flujo mencione las Skills, es una decisión aparte. |
| Roles globales Spatie | Deuda preexistente documentada en `vendar-multitenancy`; no se corrigió. |
| `Comercio.plan` vs `plan()` | Deuda preexistente documentada; no se corrigió. |
| `payments` sin período/idempotencia | Deuda preexistente documentada; no se corrigió. |

## 14. Criterios de aceptación

- [x] Se incorporaron Skills externas con procedencia y versión identificables.
- [x] Se crearon las cinco Skills propias solicitadas.
- [x] Se cubren Laravel/PHP, Vue 3/JS, frontend/UI, PostgreSQL/SQL, arquitectura,
      seguridad, testing, debugging, code review, planificación y DevOps.
- [x] No se duplican Skills ni se agregaron genéricas de bajo valor.
- [x] Las Skills externas conservan su `LICENSE`.
- [x] No se modificó código de aplicación, `AGENTS.md`, BUILD ni dependencias.
- [x] No se ejecutaron migraciones ni builds.
- [x] Existe documentación de uso, actualización y verificación.
- [x] OpenCode detecta e invoca las Skills.
- [x] El informe final lista Skills, fuentes, commits, archivos, verificaciones y pendientes.

## 15. Referencias

- `.opencode/skills/PROVENANCE.md`
- `AGENTS.md`
- `docs/informes/BUILD-B1.md` (§10.4, baseline de tests)
- `docs/informes/F13-rediseno-visual-pos.md`
- `docs/informes/F14-renovacion-mismo-plan.md`
- `https://github.com/farmage/opencode-skills` (`2ec1473`, 0.5.0)
- `https://github.com/addyosmani/agent-skills` (`301b02b`, 0.6.15)

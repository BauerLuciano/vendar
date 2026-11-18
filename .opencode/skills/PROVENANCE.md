# Procedencia de las Skills de OpenCode

Este archivo registra el origen exacto de cada Skill versionada en `.opencode/skills/`.
No reemplaza a `docs/informes/OPENCODE-SKILLS.md`: acá está el detalle de procedencia y
allá las instrucciones de uso, actualización y verificación.

## Fuentes externas

| Fuente | Repositorio | Commit fijado | Versión | Licencia |
|---|---|---|---|---|
| Farmage OpenCode Skills | `https://github.com/farmage/opencode-skills` | `2ec14733c1aaa6f98c4b12ecd078ff7c0454710b` | `0.5.0` (`CHANGELOG.md`) | MIT |
| Addy Osmani Agent Skills | `https://github.com/addyosmani/agent-skills` | `301b02b12bff98e4688e6984af6556802322bbde` | `0.6.15` (`plugin.json`) | MIT |

Los commits están fijados, no se usa `main`. Cada Skill conserva el `LICENSE` de su
repositorio de origen dentro de su propio directorio.

## Skills externas incluidas

Todas se copiaron sin cambios de contenido. Solo se verificó que el `name` del
frontmatter coincida con el nombre del directorio (ID de la Skill en OpenCode).

| Skill | Origen | Ruta upstream | Archivos | Modificaciones |
|---|---|---|---|---|
| `laravel-specialist` | Farmage | `skills/laravel-specialist/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `php-pro` | Farmage | `skills/php-pro/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `vue-expert-js` | Farmage | `skills/vue-expert-js/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `postgres-pro` | Farmage | `skills/postgres-pro/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `sql-pro` | Farmage | `skills/sql-pro/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `architecture-designer` | Farmage | `skills/architecture-designer/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `code-reviewer` | Farmage | `skills/code-reviewer/` | `SKILL.md` + 6 referencias + `LICENSE` | Ninguna |
| `secure-code-guardian` | Farmage | `skills/secure-code-guardian/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `test-master` | Farmage | `skills/test-master/` | `SKILL.md` + 10 referencias + `LICENSE` | Ninguna |
| `debugging-wizard` | Farmage | `skills/debugging-wizard/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `feature-forge` | Farmage | `skills/feature-forge/` | `SKILL.md` + 5 referencias + `LICENSE` | Ninguna |
| `devops-engineer` | Farmage | `skills/devops-engineer/` | `SKILL.md` + 9 referencias + `LICENSE` | Ninguna |
| `frontend-ui-engineering` | Addy Osmani | `skills/frontend-ui-engineering/` | `SKILL.md` + `LICENSE` | Ninguna |

### Verificación de integridad

```bash
# Confirmar que no hay symlinks
find .opencode/skills -type l

# Confirmar que cada Skill tiene SKILL.md y LICENSE (las externas)
for d in .opencode/skills/*/; do test -f "$d/SKILL.md" || echo "Falta SKILL.md en $d"; done

# Comparar contra el commit fijado (si el clon está disponible)
diff -r /ruta/al/clon/skills/laravel-specialist .opencode/skills/laravel-specialist
```

## Skills propias de VendAR

Creadas para este proyecto. No provienen de ningún repositorio externo.

| Skill | Archivo | Versión | Dominio | Origen del contenido |
|---|---|---|---|---|
| `vendar-architecture` | `.opencode/skills/vendar-architecture/SKILL.md` | `1.0.0` | Arquitectura y capas | `AGENTS.md`, `routes/web.php`, `bootstrap/app.php`, services y convenciones reales del repo |
| `vendar-multitenancy` | `.opencode/skills/vendar-multitenancy/SKILL.md` | `1.0.0` | Aislamiento por comercio/sucursal | `SucursalScopeService`, middleware, modelos, `CrossTenantAttackTest`, `MultiTenantIsolationTest` |
| `vendar-ui-guidelines` | `.opencode/skills/vendar-ui-guidelines/SKILL.md` | `1.0.0` | UI/UX y frontend | Layouts, componentes, `formatters.js`, `Terminal.vue`, `docs/informes/F13-rediseno-visual-pos.md` |
| `vendar-business-rules` | `.opencode/skills/vendar-business-rules/SKILL.md` | `1.0.0` | Reglas de negocio | `VentaController`, `VentaOperacionService`, `CajaDiariaController`, `CajaController`, `IngresoMercaderiaController`, `OrdenCompraController`, `ConsumidorController`, `PedidoWebController`, `LoteService`, `SuscripcionService`, enums |
| `vendar-testing` | `.opencode/skills/vendar-testing/SKILL.md` | `1.0.0` | Testing y regresión | `phpunit.xml`, `TestCaseMultiTenant`, `QATestDataSeeder`, tests de stock, cross-tenant y suscripciones, baseline de `BUILD-B1.md` |

### Reglas de mantenimiento de las Skills propias

- El contenido debe reflejar el código real. Si el código cambia, se actualiza la Skill
  en el mismo PR.
- No duplicar `AGENTS.md`: las Skills referencian el flujo Planner/Builder/Reviewer, no
  lo redefinen.
- No inventar reglas: cada afirmación debe poder rastrearse a un archivo del repo.
- Si una regla de negocio cambia, actualizar `vendar-business-rules` y el test que la
  cubre.
- Subir `metadata.version` cuando cambie el contenido de forma relevante.

## Skills externas evaluadas y descartadas

| Skill / fuente | Motivo |
|---|---|
| `vue-expert` (Farmage) | Asume TypeScript, Pinia y Nuxt; el proyecto es Vue 3 con JavaScript y sin Pinia. |
| `typescript-pro` (Farmage) | El proyecto no usa TypeScript. |
| `javascript-pro` (Farmage) | Se solapa con `vue-expert-js` y `frontend-ui-engineering`. |
| `database-optimizer` (Farmage) | Se solapa con `postgres-pro` y `sql-pro`. |
| `api-designer` (Farmage) | El proyecto no diseña una API pública nueva; el foco es Inertia. |
| `code-documenter` (Farmage) | Bajo valor frente a las convenciones de informes ya existentes. |
| `legacy-modernizer` (Farmage) | No hay un proceso de modernización activo. |
| `the-fool` (Farmage) | Genérica; no aporta al stack. |
| `fullstack-guardian` (Farmage) | Solapa con arquitectura, revisión y seguridad ya cubiertas. |
| `security-reviewer` (Farmage) | Se solapa con `code-reviewer` y `vendar-multitenancy`. |
| `systematic-debugging` (Flitzrrr) | Duplica `debugging-wizard`. |
| `sentry-find-bugs` / `sentry-code-review` (getsentry) | Duplican `debugging-wizard` y `code-reviewer`; requieren Sentry. |
| `vercel-web-design-guidelines` (vercel-labs) | Sin `LICENSE` verificable en el momento de la evaluación. |
| `anthropic-frontend-design` (DasDigitaleMomentum) | Licencia ambigua y estética que choca con la UI operativa de VendAR. |
| `opencode-create-plan` (Flitzrrr) | Symlink/submódulo roto: no se pudo clonar el contenido real. |
| `execute-work-package` (Flitzrrr) | Requiere infraestructura externa (l4l-oci/OpenRouter) y duplica el flujo de `AGENTS.md`. |
| `roerohan/skills`, `dimitrigilbert/ai-skills`, `rrebollo/opencode-gh-cli` | Sin Skills centrales para el stack o con solapamiento directo. |

## Cómo actualizar una Skill externa

1. Clonar el repositorio en un directorio temporal.
2. Hacer checkout del commit nuevo y revisar el `CHANGELOG` y el `LICENSE`.
3. Comparar la Skill contra la versión local:

   ```bash
   diff -r /tmp/upstream/skills/laravel-specialist .opencode/skills/laravel-specialist
   ```

4. Copiar solo la Skill afectada, sin arrastrar symlinks.
5. Verificar que el `name` del frontmatter siga coincidiendo con el directorio.
6. Actualizar la tabla de procedencia (commit y versión) y `docs/informes/OPENCODE-SKILLS.md`.
7. Confirmar que OpenCode detecta la Skill (`opencode` → Skills) y que no hay IDs duplicados.
8. Registrar el cambio en un informe de fase.

## Restricciones de esta incorporación

- No se modificó código de aplicación, modelos, controllers, componentes, migraciones ni dependencias.
- No se tocó `AGENTS.md` ni los documentos de BUILD.
- No se ejecutaron migraciones ni builds.
- Los cambios son exclusivamente de configuración y documentación: `.opencode/skills/` y `docs/informes/`.

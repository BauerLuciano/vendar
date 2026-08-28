# Informe Técnico — F11: Diagnóstico del estado técnico del módulo de facturación y checklist de homologación (solo lectura)

| Campo | Valor |
|---|---|
| Fase | F11 (post-F10) |
| Alcance | Inventario técnico del módulo de facturación WSFEv1/WSAA/padrón, ejecución de la suite de tests del módulo, preparación de checklist de pruebas de homologación de solo lectura, y documentación del único pendiente real (CUIT emisor habilitado para la prueba final) |
| Método | Diagramación del módulo (exploración estática) + ejecución real de la suite de tests en el contenedor Sail + análisis de los 4 puntos pedidos (qué funciona / qué se puede probar sin modificar ARCA / qué queda bloqueado / qué se haría para la prueba final) |
| Estado | **Terminada** — módulo verde; único pendiente conseguir un CUIT emisor habilitado para `FECAESolicitar` (bloqueado por normativa, no por código) |

---

## Objetivo

Dejar VendAR **técnicamente terminado** para WSFEv1 en homologación, probando todo lo que es de
solo lectura/autenticación y documentar con claridad qué queda pendiente de la parte real de ARCA.

Restricciones que se respetaron en esta fase:
- **NO** se modificó nada en ARCA (ni puntos de venta, ni cambios fiscales, ni alta de servicios, ni
  generación de certificados, ni contacto).
- **NO** se usó el CUIT personal `20403407888` como emisor.
- Se descartó buscar un CUIT genérico de testing: **WSFEv1 no tiene** CUIT emisor de prueba ni
  "adecuación de CUIT" (a diferencia de WSCT que relaja validaciones del emisor/PV en testing).
- No se ejecutó emisión real (`FECAESolicitar` / `FECompUltimoAutorizado` productivos) que exija un
  CUIT emisor habilitado; solo se probó la lógica del módulo con fakes y se mapeó la checklist de
  homologación de solo lectura.

---

## 1. ¿Qué ya funciona? (inventario del módulo)

El módulo está completo, estructurado y con cobertura de tests. Se distingue claramente entre
**consulta** y **emisión**:

| Componente | Archivo | Tipo | Estado |
|---|---|---|---|
| Autenticación WSAA (`loginCms`) con cache + lock | `app/Facturacion/Infrastructure/Arca/Wsaa/WsaaClient.php` | Autenticación | Implementado + testeado |
| Firma CMS para WSAA | `app/Facturacion/Infrastructure/Arca/Wsaa/FirmaCms.php` | Autenticación | Implementado |
| Certificado pfx cifrado en BD, desencriptado solo en memoria | `app/Facturacion/Infrastructure/Arca/Certificado/CertificadoService.php` | Seguridad (invariante 9) | Implementado |
| Consulta padrón (`getPersona_v2`) con credencial de plataforma global | `app/Facturacion/Infrastructure/Arca/Padron/PadronClient.php` | Consulta (no requiere cert del comercio) | Implementado + testeado |
| Suite de conectividad (certificado / WSAA / `FEDummy` / padrón) | `app/Facturacion/Infrastructure/Arca/Conectividad/ConectividadArcaService.php` | Consulta | Implementado + testeado |
| `puntosVenta()` → `FEParamGetPtosVenta` | `WsfetClient.php` | Consulta | Implementado + testeado |
| `alicuotas()` → `FEParamGetTiposIva` | `WsfetClient.php` | Consulta | Implementado + testeado |
| `consultarComprobante()` → `FEConsultaCAERequerimiento` | `WsfetClient.php` | Consulta (recupero CAE) | Implementado + testeado |
| `solicitarCae()` → `FECAESolicitar` (incl. NC con CmpAsoc) | `WsfetClient.php` | **Emisión** | Implementado + testeado |
| Armado de `FECAEDetRequest` | `app/Facturacion/Infrastructure/Arca/Wsfe/FECAERequestBuilder.php` | Armado | Implementado + testeado |
| Mapeo CAE | `app/Facturacion/Infrastructure/Arca/Wsfe/CaeMapper.php` | Armado | Implementado + testeado |
| Wizard de configuración (estados hasta `listo_para_facturar`) | `app/Facturacion/Application/WizardConfiguracionService.php` | Flujo | Implementado + testeado |
| Panel de diagnóstico / probar conexión | `app/Facturacion/Application/DiagnosticoFiscalService.php` | Flujo | Implementado + testeado |

Endpoints de homologación:
- WSFE: `https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL`
- WSAA: `https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL`
- Padrón: `https://awshomo.arca.gob.ar/sr-padron/webservices/personaServiceA5?WSDL`

### Resultado de la ejecución de la suite de tests

Ejecutada **en el contenedor Sail** (`vendar-app-laravel.test-1`, PHP 8.5.6 / OpenSSL Linux), que es el
entorno donde los tests corren correctamente:

| Suite | Resultado |
|---|---|
| `tests/Unit/FacturacionArca` (50 tests, 140 assertions) | **OK** (solo 4 deprecaciones PDO preexistentes) |
| `tests/Feature/FacturacionArca` (24 tests, 59 assertions) | **OK** (idem) |
| `tests/Feature/Facturacion` — wizard F7, diagnóstico F8, NC F6/F9, E2E F10 (108 tests, 601 assertions) | **OK** (idem) |

**Total: 182 tests en verde, 0 fallos.**

> **Nota ambiental (no es defecto del código):** con el PHP de Windows (XAMPP 8.2 / Laragon 8.3) la
> generación de pfx de prueba falla con `openssl_csr_sign(): X.509 Certificate Signing Request cannot
> be retrieved` (OpenSSL de Windows no acepta el CSR en memoria como lo generan los tests en
> `GeneraPfx::valido()`). Los tests están pensados/diseñados para correr en el contenedor (comentario
> en `GeneraPfx.php`). Correrlos vía `./vendor/bin/sail test` o `docker exec ... sail` los deja en verde.

---

## 2. ¿Qué podemos probar sin modificar ARCA? (checklist de solo lectura)

Con un CUIT + certificado cargados en el wizard (aunque el emisor aún no esté habilitado para
facturar), se puede probar todo el camino de **autenticación y consulta**. Checklist aprobable:

| # | Prueba | Componente/endpoint | Requiere cert del emisor | Requiere CUIT habilitado |
|---|---|---|---|---|
| 1 | Verificar CUIT contra padrón | `PadronClient::consultar` / `getPersona_v2` | No (credencial plataforma) | No |
| 2 | Autenticación WSAA (`wsfe`) | `WsaaClient::obtenerToken` / `loginCms` | Sí | No |
| 3 | Salud de servicios WSFEv1 | `ConectividadArcaService` / `FEDummy` | No | No |
| 4 | Puntos de venta del emisor | `WsfetClient::puntosVenta` / `FEParamGetPtosVenta` | Sí | No (consulta) |
| 5 | Alícuotas/tipos de IVA | `WsfetClient::alicuotas` / `FEParamGetTiposIva` | Sí | No (consulta) |
| 6 | Recupero de CAE | `WsfetClient::consultarComprobante` / `FEConsultaCAERequerimiento` | Sí | No (consulta) |
| 7 | Suite "Probar conexión" completa | `ConectividadArcaService::suite` | Sí (checks 2 y parcial) | No |

Dónde ejecutarlas en runtime:
- **Wizard** (`GET/POST /configuracion/fiscal`): verificarCuit (#1), cargarCertificado, puntosVenta (#4), probarConexión (#7), activar.
- **Diagnóstico** (`GET /configuracion/fiscal/diagnostico` + `POST .../probar-conexion`): #7.
- **CLI** (manual de prueba, homologación): `php artisan arca:wsaa-token` imprime Token/Sign para el
  padrón (`ws_sr_constancia_inscripcion`) — cubre el camino WSAA sin persistir.

> **Acceso requerido:** `HabilitadorHomologacion` restringe la operación en homologación al rol
> `Administrador Global` (login real con `adminvendar@gmail.com` + Modo Dios), tal como se documentó en F10.

---

## 3. ¿Qué queda bloqueado por falta de CUIT emisor habilitado?

- **`FECAESolicitar`** (emisión de facturas y notas de crédito) — el servicio consulta, **incluso en
  homologación**, contra el padrón y el registro de puntos de venta del **emisor**:
  - Error `10000` si el emisor no es `Responsable Inscripto`, no está autorizado a facturación
    electrónica, o no está activo/sin actividad (subcódigos 01/02/03/05/06).
  - Error `10005` si el punto de venta no está dado de alta o no es de tipo `RECE`.
- La revalidación del emisor **no se relaja en el ambiente de homologación de WSFEv1** (a diferencia de
  WSCT). No existe CUIT emisor de prueba ni mecanismo de "adecuación" para WSFEv1.
- La **prueba final E2E real** (venta → `FECAESolicitar` → CAE → impresión/email → NC) queda supeditada
  a disponer de un CUIT emisor habilitado.

Esto confirma el hallazgo ya documentado en fases previas: **no hay forma técnica de emitir en
homologación sin un CUIT emisor habilitado**. No es un bug ni una inconsistencia del código.

---

## 4. ¿Qué se haría para la prueba final?

1. **Conseguir un CUIT emisor habilitado** (expone facturación electrónica, PV tipo RECE dado de
   alta). Canales oficiales de ARCA (no contactados):
   - Soporte funcional de homologación: `wsfev1@arca.gov.ar`
   - Certificados/accesos: `http://www.arca.gob.ar/ws/`
   - Producción: `sri@arca.gov.ar`
2. **Configurar** ese CUIT en el wizard: verificar CUIT contra padrón (#1) → cargar certificado →
   seleccionar punto de venta (#4) → probar conexión (#7) → activar (`listo_para_facturar`).
3. **Emitir factura A** de prueba (`FECAESolicitar`) y validar la respuesta de CAE en el panel.
4. **Verificar la trazabilidad** de la numeración y el guardado del CAE en `comprobantes_fiscales`.

La comunicación (armado de request, manejo de errores, persistencia) ya está cubierta por los 182
tests en verde; lo único que no se puede validar sin CUIT emisor habilitado es la respuesta real de
`FECAESolicitar`.

---

## 5. Decisiones técnicas y hallazgos de esta fase

- Se confirma que la **suite de conectividad llama `FEDummy` sin autenticación** (solo salud), mientras
  que `puntosVenta()`/`alicuotas()` sí exigen token + certificado vía `invocar()`. Esto es informativo
  y correcto para un primer check de salud; la parte de autenticación del emisor queda cubierta por el
  check `verificarWsaa`. No requiere cambio de código.
- Los tests deben correrse **en el contenedor Sail**. Se documenta como recomendación de entorno (no
  como defecto del módulo).
- **Criterio documental:** esta fase no involucra funcionalidad normativa nueva de ARCA (se reutilizaron
  los manuales ya trabajados en `docs/arca/`: WSFEv1, WSAA, WSSEG/WSASS para el análisis comparativo de
  CUIT emisor de prueba en fases previas). No se copió contenido textual de los manuales.

---

## 6. Archivos de esta fase

| Archivo | Acción |
|---|---|
| `docs/informes/F11-diagnostico-estado-tecnico.md` | Creado (este informe) |

No se modificó ni se creó código de aplicación en esta fase (fase de diagnóstico y verificación).

---

## 7. Criterios de aceptación

- [x] Inventario del módulo: separado consulta vs emisión.
- [x] Suite de tests del módulo ejecutada en verde (182 tests, 0 fallos).
- [x] Checklist de pruebas de homologación de solo lectura preparada y aprobable.
- [x] Pendiente único documentado: CUIT emisor habilitado para `FECAESolicitar`.
- [x] Se respetaron las restricciones (sin modificar ARCA, sin usar `20403407888`).
- [x] Plan para la prueba final detallado.

---

## 8. Pendientes

- **Único bloqueante para la prueba final de emisión:** conseguir un CUIT emisor habilitado (sección 4).
- Ejecutar la checklist de homologación de solo lectura (sección 2) cuando se disponga de la sesión
  Admin Global + credenciales de homologación, únicamente con llamadas de solo lectura aprobadas.
- Hallazgos MEDIA/BAJA de la auditoría F10.1 (N+1 en repositorio, índices sobre `comprobante_original_id`,
  `CaePerdidoHandler` sin cablear) quedan en backlog para una fase posterior.
- 3 fallos preexistentes de `Modulo3_CajaTest` (ajenos al módulo): derivar al equipo dueño de Caja.

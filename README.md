# Recordatorios de obligaciones (MVP, KraftDo SpA)

MVP de **KraftDo SpA** para avisar a personas naturales, con anticipación, cuándo vence
una obligación periódica (contribuciones, permiso de circulación, SOAP, patente municipal,
derechos de aseo). **No es un servicio del Estado ni de ningún municipio**, no pide
ClaveÚnica ni ninguna otra clave, y no cobra ni intermedia pagos.

Spec y plan: `docs/superpowers/specs/2026-10-01-recordatorios-design.md` y
`docs/superpowers/plans/2026-10-01-recordatorios.md`. Construido: fase 0, tareas 0.1 a
0.7 (landing Blade + lista de espera con doble opt-in, anti-bots, baja, purga y métricas
en el panel). **Detenido antes de 0.8** (despliegue en Coolify: necesita lo de la lista
«Pendiente para César») **y de 0.9** ([PUNTO DE CONTROL] de la validación). La V1 no se
empieza sin el visto bueno de César.

Capturas de la verificación en vivo (escritorio 1440 y móvil 390, claro y oscuro,
errores, foco, panel): `docs/capturas/`.

> Repositorio **solo local**: no tiene remoto y no se publica.

## Decisión de origen: sin paquetes compartidos

Se generó con `./scaffold new recordatorios /home/cesar/Dev --shared=kraftdo
--frontend=livewire` (commit `d5d3bda`) y, por decisión del delegado del dueño (camino b),
nace **sin** `kraftdo/laravel-kraftdo-shared` y **sin ningún paquete municipal**
(`Muni\Shared`, `Muni\Ui`, `muni-graneros/*`, `demo-engine`). Lo que esos paquetes daban
y este producto necesita se reescribió dentro del repo; lo que era municipal (ARCOP de
mesón, personas/RUT, recorridos guiados, catálogo de componentes, videos) se quitó.

Desviaciones del plan, a propósito y anotadas:

- El plan pedía `--frontend=inertia-react`; se generó con `livewire`. La fase 0 no usa la
  app Inertia (es Blade puro); la app pública de la V1 tendrá que agregarse antes de la
  tarea 1.3.
- El repo vive en `~/Dev/recordatorios`, no en `~/Dev/kraftdo/recordatorios`.
- **Confirmar y darse de baja son POST** desde una página con un botón, no un GET que
  cambia datos (el plan decía GET): los antivirus de correo y las vistas previas abren
  los enlaces solos y confirmarían inscripciones ajenas. La baja de un clic del cliente
  de correo (RFC 8058, `List-Unsubscribe-Post`) sí es POST directo a la URL firmada.
- **La baja se firma sobre el ULID** de la inscripción, no sobre `email_hash`: así el
  seudónimo del correo no viaja en ninguna URL ni aparece en el cuerpo del correo.
- **El correo se guarda cifrado** (`EncryptedSeguro`) y la unicidad va en `email_hash`.
- **Sin sesión ni cookies en la landing**: el CSRF del formulario se cubre verificando el
  origen (`Sec-Fetch-Site`/`Origin`, falla cerrado) en vez de un token de sesión; así la
  portada sigue cacheable y quien solo mira no recibe ninguna cookie.
- **Panel**: se mantiene el guard y la tabla `users` del andamio (con segundo factor,
  límite de intentos, rotación de sesión) en vez de un guard `admin` nuevo sin esas
  defensas; la separación `users`/`admins` llega con las cuentas públicas (tarea 1.1).
  La ruta es `/admin`, no `/panel`.
- **Catálogo de comunas**: la capa «Comunas» de la DPA (servicio público del MOP) trae
  345; Antártica (12202) se agregó desde la lista CUT. Verificar a mano esa fila contra
  la fuente primaria (Decreto Exento N° 817).
- Tareas 0.3, 0.4 y 0.5 quedaron en un solo commit: el formulario, el anti-bots y el
  doble opt-in no tienen un estado intermedio que sirva por separado.

## Garantías de seguridad: antes (`d5d3bda`) y ahora

Ninguna garantía quedó más débil que en `d5d3bda`. Cada fila dice dónde vivía y quién la
vigila hoy.

| Garantía | Antes (`d5d3bda`) | Ahora | Candado |
|---|---|---|---|
| Datos sensibles cifrados en reposo, sin tumbar la pantalla con filas viejas | `Muni\Shared\Casts\EncryptedSeguro` | `App\Casts\EncryptedSeguro` (escrito desde cero) | `EncryptedSeguroTest` (portado del historial + clave distinta + uso en modelo real) |
| Toda ruta de `api/` exige segundo factor y sale sin caché | grupo con `onboarding` + test | constante `App\Support\Rutas\MiddlewareDeApi::AUTENTICADA`, grupo vacío en `routes/api.php` | `ApiExigeSegundoFactorTest` (ruta sonda con la misma pila; **ampliado**: toda ruta con `auth` fuera del panel exige `mfa`) |
| Rotar la contraseña cierra sesiones fuera del panel | test sobre `/api/onboarding` | igual, sobre la ruta sonda | `RotarLaContrasenaCierraLasSesionesTest` |
| Pantallas autenticadas con `no-store` | test sobre `/api/onboarding` | igual, sobre la ruta sonda | `PantallasAutenticadasSinCacheTest` |
| Sin contraseña de plantilla (BD, respaldos) en producción | `Muni\Shared\Seguridad\CredencialesDePlantilla`, enganchada sola por el proveedor del paquete | `App\Support\CredencialesDePlantilla`, **primera línea** de `AppServiceProvider::boot()` | `CandadosDelEcosistemaTest` › `guardaDeCredencialesDePlantilla` (ejecuta `boot()` de verdad), `CandadoDeCredencialesDePlantillaTest`, `HigieneDeDespliegueTest` |
| Segundo factor ni apagado ni regalado en producción | aserción de configuración en un test (`AssertSegundoFactorNoSeRegala`) | test **y además** el arranque se niega (`MFA_ENABLED` falso/vacío o `MFA_SHOW_CODE=true` en producción lanzan excepción, fallo cerrado) | `CandadoDeCredencialesDePlantillaTest`, `CandadosDelEcosistemaTest` › `segundoFactorNoSeRegala` |
| Permisos de Spatie no quedan pegados entre peticiones de Octane | `AssertPermisosNoSeQuedanPegados` | test propio | `CandadosDelEcosistemaTest` › `permisosNoSeQuedanPegados` |
| Seeders sin credenciales en producción | `Candados::seedersSinCredencialesEnProduccion()` | test propio (corre el seeder en producción y busca contraseñas conocidas) | `CandadosDelEcosistemaTest`, `SeederSinCuentasDemoTest` |
| Imagen de producción sin dependencias de dev, FrankenPHP fijado, sin root | `Candados::imagenDeProduccion()` | test propio | `CandadosDelEcosistemaTest`, `HigieneDeDespliegueTest` |
| Errores solo a un colector propio, nunca al SaaS de Sentry | `Muni\Shared\Errores\ReporteDeErrores` | `App\Support\ReporteDeErrores` | `CandadosDelEcosistemaTest` › `erroresNoSalenDelPais` |
| Proxies de confianza acotados | `Candados::proxiesDeConfianza()` | test propio | `CandadosDelEcosistemaTest` |
| Nadie emite ni acepta la cookie de «Recordarme» | `Candados::nadieEmiteCookieDeRecordar()` / `cookieDeRecordarInerte()` | tests propios | `CandadosDelEcosistemaTest`, `RecordarmeNoSaltaLaContrasenaTest` |
| Sin service worker ni manifiesto huérfanos | `Candados::pwaSinRestosDelScaffold()` | test propio | `CandadosDelEcosistemaTest` |
| Etapa de assets sin devDependencies ni `postinstall` | `Candados::higieneDeLaEtapaDeAssets()` | test propio | `CandadosDelEcosistemaTest` |
| Auditoría de solo lectura | `Muni\Shared\Auditoria\ActivityPolicy` + `ListActivitiesBase` | `App\Policies\ActivityPolicy` (crear/editar/borrar **siempre** «no») + lista sin acciones | `PoliciesDelPanelTest`, `PoliciesCubrenTodoTest` |
| Recurso de roles con policy | `RolePolicy` fachada de `Muni\Shared` (excluida del test por reflexión) | `App\Policies\RolePolicy` propia | `PoliciesCubrenTodoTest` (**ahora sí la recorre**), `PoliciesDelPanelTest` |
| Supresión de datos personales con camino declarado | `Persona::purgarDatosSensibles()` | cada modelo declara anonimizar o `SUPRESION_POR_BORRADO` | `SupresionCoherenteConElEsquemaTest` (**más estricto**: falla por modelo) |
| Ciclo ARCOP de mesón (Ley 21.719) | `PanelArcopPlugin` de `Muni\Ui` | **quitado** (módulo municipal: buscaba personas por RUT en el mesón). Fase 0: baja en un clic que borra la fila + purga a las 72 h; V1: exportar y borrar la cuenta (tarea 1.10) | tests de la lista de espera |
| XSS del recorrido guiado / policies del onboarding | tests del módulo | **quitado** el módulo y sus rutas `/api/onboarding/*`: menos superficie | — |
| **Fase 0 (nuevas)**: lista de espera | — | correo cifrado; `email_hash`/`ip_hash` HMAC con sal (arranque se niega sin `WAITLIST_HASH_SALT`); token 32 bytes solo como sha256, 72 h, un uso; Turnstile en servidor, fallo cerrado, regla implícita + `required`; 5 POST/min por IP; 1 correo/24 h por dirección; respuestas idénticas; CSRF por origen; `no-store`; baja firmada; purga diaria 03:00 UTC; Turnstile con claves de prueba en producción = no arranca | `tests/Feature/Waitlist/*`, `tests/Guardrails/*` |
| **Fase 0 (nuevas)**: panel solo por VPN | — | `SoloDesdeLaVpn` (404 fuera de `ADMIN_VPN_CIDR`, antes que la autenticación; en producción sin subred, cerrado) en panel, `/verificar-mfa` y acciones Livewire; métricas solo con COUNT/SUM | `tests/Feature/Panel/*` |
| Probidad: sin referencias a sistemas municipales internos | — | candado en CI | `SinReferenciasMunicipalesInternasTest` |
| Sin secretos de proveedores en el repo | gitleaks en pre-commit | gitleaks + test propio (Resend, Telegram, age, PEM) | `SinSecretosEnRepoTest` (corre en el equipo; en el contenedor sin git se salta) |
| Aviso de vencimiento del secreto de Microsoft Graph | `SaludDelSistema` + `ConfiguracionDeCorreo` | **no aplica**: no hay Graph (correo por Resend/SMTP) | — |

## Cómo se trabaja

```bash
docker compose up -d                       # app en http://localhost:8211
docker compose exec -T -e DB_DATABASE=sistema_test app php -d memory_limit=1G ./vendor/bin/pest
docker compose exec -T app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -T app ./vendor/bin/pint --test
docker compose down                        # conserva los volúmenes
```

Docker, Composer y npm se corren fuera del sandbox de Claude Code (sus cachés son de
solo lectura dentro).

## Estructura

```
recordatorios/
├── app/
│   ├── Casts/
│   │   └── EncryptedSeguro.php        # Cifrado en reposo tolerante a filas viejas
│   ├── Console/
│   │   └── Commands/
│   │       └── MaintenanceDaily.php   # Purga diaria (03:00 UTC)
│   ├── Filament/
│   │   └── Pages/
│   │       └── Metricas.php           # Métricas agregadas de la lista de espera
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Landing/
│   │   │       └── WaitlistController.php # Alta, confirmación, encuesta, baja
│   │   └── Middleware/
│   │       ├── SoloDesdeLaVpn.php     # Panel solo desde ADMIN_VPN_CIDR
│   │       ├── TwoFactorAuthentication.php # Middleware MFA
│   │       └── VerificarOrigenDelFormulario.php # CSRF sin sesión
│   ├── Models/
│   │   └── WaitlistSignup.php         # Correo cifrado, hash con sal
│   ├── Rules/
│   │   └── Turnstile.php              # siteverify, fallo cerrado, implícita
│   ├── Support/
│   │   ├── CredencialesDePlantilla.php # Guarda de arranque en producción
│   │   ├── ReporteDeErrores.php        # Errores solo a un colector propio
│   │   └── Rutas/
│   │       └── MiddlewareDeApi.php     # Pila obligatoria de toda ruta de api/
│   ├── Waitlist/
│   │   └── SignupService.php          # Doble opt-in y límite por correo
│   └── Livewire/
│       └── VerificarMfa.php           # Pantalla del segundo factor
├── database/
│   └── data/
│       └── comunas.csv                # 346 comunas CUT, con su fuente
├── docker/
│   ├── docker-entrypoint.prod.sh      # Arranque de producción (baja a www-data)
│   └── supervisord.conf               # Worker de colas y planificador
├── routes/
│   └── lista-de-espera.php            # Grupo «lista»: sin sesión, no-store
├── tests/
│   ├── Feature/
│   │   └── CandadosDelEcosistemaTest.php # Candados de configuración, sin paquete
│   └── Guardrails/
│       └── SinReferenciasMunicipalesInternasTest.php # Candado de probidad
├── Dockerfile                         # Build multi-etapa (assets + FrankenPHP)
├── docker-compose.yml                 # Desarrollo
└── docker-compose.prod.yml            # Endurecimiento de producción
```

## Pendiente para César

Lo que solo César puede dar. Mientras falte, el código usa marcadores en `.env.example`
y no se despliega nada (tarea 0.8 detenida).

1. **Zona DNS** (spec Q2) para `recordatorios.<zona>` y `avisos.<zona>`. Sin ella no hay
   dominio de envío ni certificado.
2. **Resend**: dominio `avisos.<zona>` verificado (SPF, DKIM, DMARC `p=reject`) y la API
   key en SOPS. El correo sale por el mailer SMTP ya existente (sin dependencias nuevas):
   `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.resend.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`,
   `MAIL_USERNAME=resend`, `MAIL_PASSWORD=<API key>`, `MAIL_FROM_ADDRESS=no-responder@avisos.<zona>`.
3. **Turnstile**: crear el widget en Cloudflare (dominio de la landing) y poner
   `TURNSTILE_SITEKEY`, `TURNSTILE_SECRET` (y `TURNSTILE_HOSTNAME`) en SOPS. Hoy, fuera de
   producción, se usan las claves de prueba públicas de Cloudflare; en producción el
   arranque se niega sin claves propias.
4. **Secretos propios del despliegue** en SOPS → Coolify: `APP_KEY`, `WAITLIST_HASH_SALT`
   (`openssl rand -base64 32`), `DB_PASSWORD`, `BACKUP_ARCHIVE_PASSWORD`, `ADMIN_VPN_CIDR`
   (subred de WireGuard). Allowlist en el proxy para `/admin`, `/livewire/*`,
   `/verificar-mfa` y `/filament/*` a la VPN (la app ya responde 404 fuera de ella).
5. **Revisión legal**: los textos de `/privacidad`, `/terminos` y `/que-no-hacemos` son
   borradores (L1, L4, L7, L11) y faltan razón social, RUT, domicilio y correo de
   contacto de privacidad. **L18 (probidad)**: el spec recomienda pedirla antes de abrir
   la lista al público.
6. **Telegram** (nombre del bot y cuenta de KraftDo, spec Q9): no se usa en la fase 0;
   hace falta para la tarea 1.7.
7. **Umbral de la validación** (spec Q1) y el guion de entrevistas de la tarea 0.9.
8. Nombre comercial del producto (`APP_NAME`/`SITIO_NOMBRE`, hoy «Recordatorios»).
9. Verificar a mano la fila de Antártica (12202) del catálogo de comunas.

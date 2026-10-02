# Prompt 9 — MVP «Recordatorios de obligaciones»: plan de implementación

> **Para agentes:** ejecutar con `superpowers:subagent-driven-development` (un implementador por tarea + un revisor por
> tarea + review final de rama en el modelo más capaz) o con `superpowers:executing-plans`. TDD estricto por tarea: test
> rojo → implementación mínima → test verde → commit. Los pasos usan `- [ ]`.

**Spec:** `spec.md` (misma carpeta). **Entidad:** KraftDo SpA. **Estado:** solo plan; el repo no existe.

**Goal:** Validar la idea con una landing Blade + lista de espera con doble opt-in (fase 0, primera rebanada
entregable) y, si se supera el umbral de `spec.md` §4, construir la V1: cuentas, obligaciones desde plantillas «no
verificadas», recordatorios idempotentes por correo y Telegram en `America/Santiago`, comprobantes cifrados con
historial y registro de accesos, exportación y borrado de cuenta, métricas.

**Architecture:** tres capas del dueño generadas con el scaffold: landing Blade (SEO), app pública Inertia + React +
TypeScript con tipos generados desde Data classes, panel Filament SPA solo por VPN. MariaDB, Redis, colas y planificador
de Laravel en UTC calculando en `America/Santiago`. Resend para correo, bot de Telegram por webhook, Turnstile anti-bots,
secretos en SOPS+age → variables de Coolify. Sin Supabase, sin n8n, sin pg_cron.

**Tech Stack:** las versiones **las fija el scaffold en el momento de generar**: leer `composer.json` y `package.json`
del repo generado antes de escribir una línea y consultar context7 para cada API de Laravel, Filament, Livewire,
Inertia, Tailwind y del SDK de Resend. No asumir nada de memoria. Pest para tests, PHPStan, Pint, Playwright para E2E
(`e2e/`), skills `blade-livewire-design` (landing y panel) e `inertia-react-design` (app pública); las pantallas nuevas
de la app pública pasan por el flujo v0 con parada obligatoria para mostrar a César (`v0-a-inertia`).

## Restricciones globales (aplican a TODAS las tareas)

- **Candado Graneros (spec §0):** repo en `~/Dev/kraftdo/recordatorios` (nombre provisional), generado con
  `--shared=kraftdo`, nunca `--shared=muni`. Nada se copia desde `~/Dev/Graneros`, `~/Dev/*-graneros`,
  `~/Dev/plataforma-graneros` ni de `laravel-muni-ui`. Las fechas de plantillas vienen **solo** de
  `../investigacion-9-recordatorios.md` §1 con su URL, y cualquier dato de la comuna de Graneros solo desde su web pública,
  igual que cualquier otra comuna. El test `tests/Guardrails/SinReferenciasMunicipalesInternasTest.php` (tarea 0.1) corre
  en CI y falla si aparecen referencias a sistemas internos municipales.
- **Sin credenciales de terceros, sin login en portales, sin pagos** (spec §3): si una tarea «necesita» algo de eso, está
  mal planteada: detenerse y preguntar.
- **Minimización:** ningún campo nuevo con datos personales sin que esté en `spec.md` §7.1. Nada de PII en logs ni en
  mensajes de error; IP solo como `ip_hash` con sal.
- **Datos personales a terceros solo seudonimizados** (spec §9): los tests de las plantillas de correo y Telegram comprueban
  que el cuerpo **no** contiene RUT, patente, rol, dirección ni montos aunque el alias los tenga.
- Commits con `git commit --only -- <rutas>`, mensaje en español con el porqué, **sin atribución de IA**.
- Antes de cada commit: Pint sobre las rutas tocadas, PHPStan en verde, suite Pest en verde (`make test` o el
  equivalente del repo generado; recordar el gotcha «`make test` borra la BD» del scaffold).
- UI: capturas escritorio y móvil, claro y oscuro, foco visible, teclado completo (WCAG 2.2 AA) antes de cerrar cada
  tarea con vista. Verificación en navegador real (chrome-devtools / Playwright), no descripción.
- Rutas absolutas; dentro del sandbox evitar `cd` compuesto (`git -C`, `make -C`).
- Al terminar cada fase: actualizar `../diseno-14-integracion/INTEGRATION.md` y `../diseno-06-datos/subencargados.md`
  con las conexiones nuevas (regla de `../INDICE.md`).

## Estructura de archivos (objetivo al final de la V1)

```
app/
  Data/                      Data classes (spatie/laravel-data) → tipos TS generados, nunca a mano
    ObligationData.php · ObligationDueData.php · ReceiptData.php · TelegramLinkData.php · AccountData.php
  Models/                    WaitlistSignup · User · Consent · Comuna · ObligationType · ObligationTemplate ·
                             Obligation · ObligationDue · ReminderSend · TelegramLink · TelegramLinkToken ·
                             Receipt · ReceiptAccessLog · DataExport · MetricDaily · Admin
  Models/Concerns/OwnedByCurrentUser.php        global scope
  Policies/                  ObligationPolicy · ObligationDuePolicy · ReceiptPolicy · TelegramLinkPolicy · DataExportPolicy
  Reminders/                 ReminderPlanner · ReminderDispatcher · LocalClock (America/Santiago) · Channels/{EmailChannel,TelegramChannel}
  Receipts/                  ReceiptEncryptor (libsodium) · ReceiptValidator (magic bytes) · ImageSanitizer (re-encode, sin EXIF)
  Telegram/                  BotClient · WebhookHandler · LinkTokenService
  Waitlist/                  SignupService · ConfirmationMail · UnsubscribeSignedUrl
  Account/                   ExportAccountJob · DeleteAccountJob
  Console/Commands/          reminders:dispatch · maintenance:daily · templates:import
  Jobs/                      SendReminderJob
  Http/Controllers/Landing/  WaitlistController (Blade)
  Http/Controllers/App/      ObligationController · DueController · ReceiptController · TelegramController · AccountController
  Http/Controllers/Webhooks/ TelegramWebhookController · ResendWebhookController
  Filament/                  recursos: ObligationTemplateResource (verificar), páginas: Metricas, BitacoraEnvios (sin destinatario)
database/
  migrations/                una por tabla de spec §7.1 (índice ÚNICO de reminder_sends en su migración)
  seeders/                   ComunasSeeder (catálogo público CUT) · ObligationTypesSeeder · ObligationTemplatesSeeder (todas verified=false, con source_url)
  data/                      comunas.csv (fuente pública citada en el encabezado) · plantillas-2026.csv (de la investigación §1)
resources/views/landing/     index · waitlist/{confirmed,unsubscribed,pending} · legal/{privacidad,terminos,que-no-hacemos}
resources/js/app/            páginas Inertia (obligaciones, vencimientos, comprobantes, canales, cuenta)
tests/
  Guardrails/                SinReferenciasMunicipalesInternasTest · SinPiiEnLogsTest · SinSecretosEnRepoTest
  Feature/Waitlist · Feature/Auth · Feature/Obligations · Feature/Reminders · Feature/Receipts · Feature/Telegram ·
  Feature/Account · Feature/Authorization (IDOR por endpoint) · Feature/Panel
  Unit/Reminders (LocalClock, planner, DST) · Unit/Receipts (encryptor, validator)
e2e/                         Playwright: waitlist.spec.ts · obligacion-recordatorio.spec.ts · comprobante.spec.ts · borrar-cuenta.spec.ts
docs/superpowers/specs/2026-10-01-recordatorios-design.md   (copia de spec.md al nacer el repo)
docs/superpowers/plans/2026-10-01-recordatorios.md          (copia de este plan)
```

---

## Fase 0 — Validación: landing Blade + lista de espera (primera rebanada entregable)

Objetivo: publicar la landing con lista de espera (doble opt-in, consentimiento, Turnstile, baja) y medir. Termina con el
[PUNTO DE CONTROL] de la validación. No se construye nada de la V1 hasta que César lo apruebe.

### Tarea 0.1 — Generar el repo y poner el candado Graneros

- [ ] Verificar prerrequisitos: `make check VIA=vpn` en `~/Dev/infra-contabo` con `changed=0`; Coolify y Resend del
      prompt 2B operativos; zona DNS decidida (spec Q2). Si falta algo, **detenerse** y reportarlo: no seguir.
- [ ] Generar: `./scaffold new recordatorios --shared=kraftdo --frontend=inertia-react` desde
      `~/Dev/scaffold-laravel-filament-pwa` (rama `develop`) hacia `~/Dev/kraftdo/recordatorios`. Leer `composer.json` y
      `package.json` generados y anotar versiones en el `CLAUDE.md` del repo.
- [ ] Copiar `spec.md` y `plan.md` a `docs/superpowers/{specs,plans}/` con fecha. Escribir en el `CLAUDE.md` del repo el
      bloque de probidad de spec §0 completo (es el archivo que leerá cada sesión).
- [ ] **Test rojo** `tests/Guardrails/SinReferenciasMunicipalesInternasTest.php`: recorre `app/`, `database/`,
      `resources/`, `config/`, `routes/`, `e2e/` y falla si algún archivo contiene (sin distinguir mayúsculas) alguno de los
      patrones de una lista mantenida en el test: nombres de repos/sistemas municipales internos (`atencionvecino`,
      `discapacidad-graneros`, `licencias-graneros`, `seguridad-graneros`, `plataforma-graneros`, `personas-graneros`,
      `muni-ui`, `muni-shared`, `--shared=muni`, `graneros.cl/intranet`, `ssh graneros`). La palabra «Graneros» sola **no**
      falla (es una comuna del catálogo público); el test documenta por qué. Para que arranque rojo, el archivo
      `database/data/comunas.csv` todavía no existe: el test también falla si el encabezado del CSV no cita una fuente
      pública (`# fuente:` con URL de SUBDERE o INE).
- [ ] Crear `database/data/comunas.csv` con las 346 comunas (código CUT, nombre, región) desde la fuente pública, con el
      encabezado `# fuente: <URL>`. **Test verde.**
- [ ] **Test rojo** `tests/Guardrails/SinSecretosEnRepoTest.php`: falla si hay cadenas con forma de token (`re_[A-Za-z0-9]{20,}`
      de Resend, `[0-9]{8,10}:[A-Za-z0-9_-]{35}` de Telegram, claves age privadas) fuera de `.sops.*`. Verde con el repo limpio.
- [ ] Commit: «Nace el MVP de recordatorios con candados de probidad y secretos».

### Tarea 0.2 — Migración y modelo de la lista de espera

- [ ] **Test rojo** `tests/Feature/Waitlist/WaitlistSignupModelTest.php`:
      - `email` único; `email_hash` se calcula con `hash_hmac('sha256', strtolower(trim($email)), config('waitlist.hash_salt'))`;
      - `confirm_token_hash` nunca guarda el token en claro (`assertNotEquals($token, $signup->confirm_token_hash)`);
      - `scopeExpiredUnconfirmed()` devuelve solo filas sin `confirmed_at` con `confirm_expires_at < now()`;
      - `interests` acepta solo códigos de `obligation_types` (validación en el modelo).
- [ ] Migración `create_waitlist_signups_table` con las columnas de spec §7.1 (`email` 320, `email_hash` 64 indexado,
      `comuna_id` nullable FK, `interests` JSON, `consent_version`, `consented_at`, `ip_hash`, `confirm_token_hash` 64,
      `confirm_expires_at`, `confirmed_at` nullable, `paid_penalty_last_year` nullable bool, `unsubscribed_at` nullable).
      Migración `create_comunas_table` + `ComunasSeeder` desde el CSV. `config/waitlist.php` con `hash_salt` desde `.env`
      (`WAITLIST_HASH_SALT`), `confirm_ttl_hours = 72`, `resend_cooldown_hours = 24`.
- [ ] Modelo `WaitlistSignup` con casts, `scopeExpiredUnconfirmed`, `markConfirmed()`. **Test verde.**
- [ ] Commit: «Lista de espera: tabla, catálogo público de comunas y hash del correo para límites sin exponerlo».

### Tarea 0.3 — Formulario de la landing (Blade) con aviso de privacidad y consentimiento

- [ ] Leer la skill `blade-livewire-design` y `modern-web-guidance` antes de tocar vistas.
- [ ] **Test rojo** `tests/Feature/Waitlist/LandingPageTest.php`:
      - `GET /` responde 200 y contiene: la frase «No es un servicio del Estado ni de ningún municipio», el texto «No pedimos
        ClaveÚnica», un `<form method="POST" action="/lista-de-espera">`, un `<input type="checkbox" name="consent">` **sin**
        `checked`, un enlace a `/privacidad`, el `<select name="comuna">` con 346 opciones y una casilla por tipo en `interests[]`;
      - `GET /privacidad`, `/terminos`, `/que-no-hacemos` responden 200;
      - `POST /lista-de-espera` sin `consent` → 422 con error en `consent`; con correo inválido → 422; sin `interests` → 200
        (es opcional).
- [ ] Rutas `routes/web.php` (grupo landing), `WaitlistController@index/@store`, `StoreWaitlistRequest` (reglas: `email`
      `required|email:rfc,dns|max:320`, `consent` `accepted`, `comuna` `nullable|exists:comunas,code`, `interests.*`
      `in:<códigos>`), vistas Blade con el layout del scaffold, tokens de diseño, modo oscuro, labels asociados, errores
      descritos en texto. Textos legales como borrador tomado de `../diseno-06-datos/politica-privacidad.md` adaptado a spec §9,
      con el encabezado «Borrador — pendiente de revisión de abogado».
- [ ] **Test verde.** Capturas: escritorio y móvil, claro y oscuro; navegación por teclado completa; contraste medido.
- [ ] Commit: «Landing con lista de espera: consentimiento explícito sin premarcar y aviso de que no es el Estado».

### Tarea 0.4 — Anti-bots accesible y límites de intentos

- [ ] **Test rojo** `tests/Feature/Waitlist/AntiAbuseTest.php`:
      - `POST` sin token de Turnstile → 422 (`turnstile`); con token inválido (cliente HTTP falseado con `Http::fake`) → 422;
      - 6.º `POST` desde la misma IP en un minuto → 429; 2.º `POST` con el mismo `email_hash` dentro de 24 h → responde 200
        con el mismo mensaje genérico **y no envía otro correo** (`Mail::assertNothingOutgoing` tras el primero);
      - la respuesta a un correo nuevo y a uno ya inscrito es **idéntica** (mismo HTML, misma redirección).
- [ ] Regla de validación `Turnstile` que llama a `siteverify` con la clave secreta desde `.env` (`TURNSTILE_SECRET`), con
      `Http::timeout(5)` y fallo cerrado; limitadores `RateLimiter::for('waitlist-ip')` y `waitlist-email`; mensaje único
      «Si tu correo es válido, te enviamos un enlace para confirmar». Sin CAPTCHA visual; el widget en modo accesible.
- [ ] **Test verde.** Commit: «Lista de espera: Turnstile y límites por IP y correo sin revelar si el correo ya existe».

### Tarea 0.5 — Doble opt-in por correo (Resend) y baja en un clic

- [ ] Verificar con context7 el transporte Resend para la versión de Laravel del repo; dominio `avisos.<zona>` verificado
      (SPF, DKIM, DMARC `p=reject`). Clave en SOPS → variable de Coolify `RESEND_API_KEY`.
- [ ] **Test rojo** `tests/Feature/Waitlist/DoubleOptInTest.php`:
      - tras `POST`, `Mail::assertQueued(WaitlistConfirmationMail)` al correo, cuyo cuerpo contiene `/lista-de-espera/confirmar/{token}`
        y **no** contiene el `email_hash` ni la IP;
      - `GET` del enlace con token correcto → `confirmed_at` seteado, 200 con la pregunta opcional «¿Has pagado multa…?»;
      - el mismo enlace por segunda vez → 410; token vencido (viajar 73 h) → 410; token inventado → 404 **en el mismo tiempo**
        (comparación con `hash_equals`);
      - `POST /lista-de-espera/confirmar/{token}/encuesta` con `paid_penalty_last_year=1` guarda el bool;
      - `GET /lista-de-espera/baja/{signed}` (URL firmada por `email_hash`) **borra** la fila y responde 200; firmada mal → 403.
- [ ] `SignupService` (crea o reutiliza, genera `random_bytes(32)` → base64url, guarda `hash('sha256', $token)`), `WaitlistConfirmationMail`
      (plantilla Markdown, texto plano incluido, enlace de baja en el pie), rutas `confirmar`, `encuesta`, `baja`.
- [ ] **Test verde.** Prueba manual en vivo: inscribirse con un correo real de KraftDo, confirmar, darse de baja.
- [ ] Commit: «Doble opt-in para la lista de espera: sin confirmación no hay dato, y baja con un clic».

### Tarea 0.6 — Purga automática de inscripciones sin confirmar

- [ ] **Test rojo** `tests/Feature/Waitlist/PurgeUnconfirmedTest.php`: con una fila sin confirmar vencida, una vigente y una
      confirmada, `artisan maintenance:daily` borra solo la primera; el comando está registrado en el planificador a las
      `03:00` UTC (`assertScheduled` o inspección de `Schedule`).
- [ ] Comando `maintenance:daily` (por ahora solo purga; crece en la V1). **Test verde.**
- [ ] Commit: «Purga diaria de inscripciones sin confirmar: el dato no confirmado no se conserva».

### Tarea 0.7 — Métricas de la validación en el panel (solo VPN)

- [ ] **Test rojo** `tests/Feature/Panel/WaitlistMetricsTest.php`: con el guard `admin` autenticado, `/panel` muestra
      «Inscritos», «Confirmados», «Bajas», «% con multa último año», serie diaria de confirmados; **no** muestra ningún
      correo; sin autenticación → redirección al login del panel; un `User` normal no puede entrar al panel.
- [ ] Tabla `admins` + guard `admin`; página Filament «Métricas» leyendo agregados (consulta `COUNT`, nunca listas de filas con
      PII). Allowlist del proxy para `/panel` a la IP de WireGuard (se configura en Coolify/Caddy en 0.8; aquí queda un test de
      *middleware* `OnlyFromVpn` con la subred desde `.env`).
- [ ] **Test verde.** Capturas del panel claro/oscuro. Commit: «Panel del dueño con las métricas de la lista de espera, sin datos personales».

### Tarea 0.8 — Despliegue de la fase 0 en Coolify y verificación en vivo

- [ ] Proyecto propio del MVP en Coolify, dominio `recordatorios.<zona>` tras el Caddy actual (B2 = b), variables desde SOPS
      (`APP_KEY`, `WAITLIST_HASH_SALT`, `RESEND_API_KEY`, `TURNSTILE_SECRET`, `TURNSTILE_SITEKEY`, `ADMIN_VPN_CIDR`, DB, Redis),
      límites de CPU/RAM en el compose, `php artisan migrate --force` solo en este primer despliegue vacío (no hay datos aún;
      desde la V1 el SQL de migración se genera y lo revisa César). Respaldo de la BD del MVP incluido en el job de R2.
- [ ] Verificar en vivo desde fuera de la VPN: `/` 200, `/panel` **bloqueado**, cabeceras de seguridad, Turnstile funcionando,
      correo de confirmación llega y pasa SPF/DKIM/DMARC (ver cabeceras), baja funciona. Medir RAM/CPU en reposo y confirmar que
      NFC no cambió (`docker stats`, Uptime Kuma).
- [ ] E2E `e2e/waitlist.spec.ts` contra el entorno local: inscribir → confirmar (leyendo el correo desde el *log mailer*) →
      encuesta → baja.
- [ ] Actualizar `INTEGRATION.md` (MVP → Resend; Cloudflare Turnstile → MVP) y `subencargados.md` (sección MVP). Commit.

### Tarea 0.9 — Entrevistas y [PUNTO DE CONTROL] de la validación

- [ ] Guion de entrevista en `docs/validacion/guion-entrevista.md` (8 preguntas de spec §4), hoja de registro **sin** nombres
      (código de entrevista + comuna + respuestas), y consentimiento verbal para tomar notas.
- [ ] Pasados 30 días: exportar las métricas del panel y llenar `docs/validacion/resultado-fase-0.md` con los tres criterios de
      spec §4 (confirmados, entrevistas, dolor real) y lo aprendido.
- [ ] **[PUNTO DE CONTROL]** Mostrar a César el resultado. Si no se alcanza el umbral, no empezar la fase 1. Si se alcanza,
      pedir además el estado de la revisión legal L18 (spec Q3) antes de abrir cuentas.

---

## Fase 1 — V1

Cada tarea sigue el mismo ritmo (rojo → verde → commit). Las pantallas Inertia pasan por v0 con parada para mostrar a
César antes de integrarlas.

### Tarea 1.1 — Cuentas, verificación de correo y consentimiento versionado

- [ ] **Tests** `Feature/Auth`: registro exige `consent` (versión actual de privacidad y términos) y crea dos filas en
      `consents` con `ip_hash`; no se puede crear una obligación sin `email_verified_at` (403 con mensaje); contraseña con
      argon2id; login con 5 intentos/min por IP+correo → 429; mensajes de login/reset/registro **idénticos** exista o no el
      correo; registro con correo existente envía «ya tienes cuenta» al dueño y responde igual que un alta nueva; cambiar
      contraseña cierra las otras sesiones y avisa por correo; la sesión se regenera al entrar.
- [ ] Implementar sobre la autenticación que traiga el scaffold (verificar con context7). Si trae TOTP, activarlo opcional
      (spec Q7). Commit.

### Tarea 1.2 — Tipos, plantillas «no verificadas» y verificación en el panel

- [ ] **Tests**: `ObligationTypesSeeder` crea los 5 tipos con `default_offsets=[15,3]`; `templates:import` carga
      `database/data/plantillas-2026.csv` (transcripción literal de `../investigacion-9-recordatorios.md` §1 con `source_url`)
      y **todas** quedan `verified=false`; un test falla si alguna fila del CSV no tiene `source_url` con `https://` de dominio
      oficial (`tgr.cl`, `sii.cl`, `chileatiende.gob.cl`, `gob.cl`, `bcn.cl`, `conaset.cl`, `cmfchile.cl`, `*.cl` municipal
      declarado); en el panel, marcar verificada exige `verified_by`, `verified_at` y `source_url`; una plantilla verificada
      hace más de 365 días vuelve a mostrarse como «por revisar».
- [ ] Modelos, seeders, `ObligationTemplateResource` en Filament. Commit.

### Tarea 1.3 — Obligaciones y vencimientos (Data classes + tipos generados)

- [ ] **Tests** `Feature/Obligations`: crear desde plantilla copia fecha, recurrencia, offsets y `template_snapshot` con
      `verified=false`; la respuesta Inertia incluye `template_verified: false` y la UI muestra la etiqueta «Fecha no
      verificada: confirma en el sitio oficial»; offsets válidos 1–60, máximo 4; editar `due_date` replanifica (ver 1.4);
      `alias` con patrón de RUT (`\d{1,2}\.?\d{3}\.?\d{3}-[\dkK]`) o patente (`[A-Z]{4}\d{2}|[A-Z]{2}\d{4}`) → 422 con
      explicación («no escribas RUT ni patente»); `official_url` sale del catálogo, nunca del request (enviarla en el payload
      se ignora).
- [ ] `ObligationData`, `ObligationDueData`; `npm run types:generate && npm run types:check`; páginas Inertia (v0 → mostrar a
      César → adaptar). Commit.

### Tarea 1.4 — `ReminderPlanner`, `LocalClock` y la bitácora idempotente

- [ ] **Migración** `create_reminder_sends_table` con `UNIQUE(obligation_due_id, offset_days, channel, target_date)`.
- [ ] **Tests** `Unit/Reminders/ReminderPlannerTest`: para `due_date=2026-03-31`, offsets `[15,3]`, canales `[email]` crea
      filas con `target_date` `2026-03-16` y `2026-03-28`; llamar dos veces no duplica (`insertOrIgnore`); con `target_date`
      pasado no crea; editar la fecha borra `planned` y conserva `sent`; marcar pagado pasa `planned/queued` a `skipped('paid')`.
- [ ] **Tests** `Unit/Reminders/LocalClockTest` con `Carbon::setTestNow`: `2026-04-05 03:30 UTC` (00:30 en Santiago tras el fin
      del horario de verano, NO VERIFICAR la fecha exacta del decreto en el test: parametrizar con la fecha que publique el
      Gobierno y dejar la fuente en el comentario) → `today()` correcto y `inSendWindow()` falso; `11:00 UTC` → ventana abierta;
      `23:30 UTC` del 15 → `today()` es el 15 en Santiago aunque UTC ya sea 16 solo si corresponde (caso 03:00 UTC del 16 = 15
      en Santiago, debe devolver 15).
- [ ] Commit: «Bitácora idempotente de recordatorios y reloj local: fechas de calendario, no timestamps».

### Tarea 1.5 — `reminders:dispatch` cada 15 minutos con CAS y tolerancia a caídas

- [ ] **Tests** `Feature/Reminders/DispatchTest`: con `Queue::fake`, antes de las 08:00 locales no encola nada; después,
      encola solo `planned` con `target_date <= hoy` y vencimiento `pending`; una fila de hace 3 días pasa a `skipped('stale')`;
      una de ayer (caída del servidor) **sí** se envía; dos ejecuciones concurrentes (simuladas con la fila ya en `queued`) encolan
      una sola vez; el comando está programado `everyFifteenMinutes()` en UTC.
- [ ] Commit.

### Tarea 1.6 — Canal de correo (Resend) seudonimizado y reintentos

- [ ] **Tests** `Feature/Reminders/EmailChannelTest`: el cuerpo contiene tipo, alias, fecha y los dos enlaces (app y sitio
      oficial del catálogo) y **no** contiene el RUT/patente/monto aunque se inyecten en el alias por *factory* sin validar;
      éxito → `sent` + `provider_message_id`; `Http::fake` 422 → `failed` sin reintento; 429/500 → `release` y `attempts++`;
      5 fallos → `failed`; la clave de idempotencia viaja al proveedor si el SDK lo soporta (verificar en la doc; si no,
      documentar el riesgo residual en el código).
- [ ] `SendReminderJob` (`tries=5`, backoff `[60,300,900,3600,10800]`, `uniqueFor`), `EmailChannel`. Commit.

### Tarea 1.7 — Bot de Telegram: vinculación por deep link, webhook, canal y bloqueos

- [ ] **Tests** `Feature/Telegram`: `POST /telegram/vincular` genera token (32 bytes, hash en BD, 15 min) y devuelve
      `https://t.me/<bot>?start=<token>`; webhook sin `X-Telegram-Bot-Api-Secret-Token` correcto → 403 **sin cuerpo**; con
      `/start <token>` válido vincula `chat_id` (cifrado en columna) y responde «Listo, vuelve a la app»; token usado/vencido →
      respuesta genérica sin indicar cuál; cualquier otro mensaje → sin respuesta ni eco; `/stop` → `blocked_at`; envío con 403
      del bot → `blocked_at` + `skipped('blocked')`; 429 con `retry_after=7` → `release(7)`; `RateLimited` ≤ 1/s por chat.
- [ ] `BotClient`, `WebhookHandler`, `TelegramChannel`, ruta `/webhooks/telegram/<segmento-aleatorio>`; `setWebhook` con
      `secret_token` desde SOPS. La cuenta de BotFather es de KraftDo (spec Q9). Commit.

### Tarea 1.8 — Comprobantes: validación por magic bytes, saneo, cifrado y descarga firmada

- [ ] **Tests** `Unit/Receipts/ReceiptValidatorTest`: PDF/JPEG/PNG/WebP reales pasan; `.jpg` con cabecera PHP, SVG, HEIC,
      11 MB → rechazados; `ImageSanitizerTest`: JPEG con EXIF GPS sale sin EXIF y sigue decodificable;
      `ReceiptEncryptorTest`: cifrar → descifrar devuelve el original, un byte cambiado falla la autenticación, `key_id` queda en
      la fila, rotar clave sigue descifrando los viejos.
- [ ] **Tests** `Feature/Receipts`: subir crea fila con `stored_path` UUID y `original_name` cifrado, el archivo en disco **no**
      es igual al original (`assertNotEquals(sha256(disk), sha256(upload))`); segundo comprobante al mismo vencimiento conserva el
      primero (historial); descargar exige URL firmada de 10 min ligada al usuario y responde `Content-Disposition: attachment` +
      `nosniff`; cada vista/descarga/borrado escribe `receipt_access_log`; borrar elimina el archivo físico; tope por usuario
      (spec Q6) → 422 al superarlo.
- [ ] Commit: «Comprobantes cifrados con clave propia, sin EXIF y con registro de accesos».

### Tarea 1.9 — Marcar pagado y generar el siguiente vencimiento

- [ ] **Tests**: marcar pagado fija `paid_at`, salta recordatorios pendientes, crea el siguiente `obligation_due` según la
      recurrencia con la plantilla del período siguiente (si existe) o +1 año/6 meses/3 meses, con `template_snapshot` y la
      etiqueta de verificación; desmarcar dentro de 24 h revierte si el siguiente no tiene comprobantes.
- [ ] Commit.

### Tarea 1.10 — Exportar y borrar la cuenta (ARCOP autoservicio)

- [ ] **Tests** `Feature/Account`: exportar encola `ExportAccountJob`, produce ZIP con `datos.json` (todas sus tablas) y los
      comprobantes **descifrados**, enlace firmado de un solo uso de 24 h, fila en `receipt_access_log(action=export)`; borrar
      fija `deletion_execute_at = +7 días`, correo de aviso con enlace para cancelar; `maintenance:daily` tras la gracia borra en
      cascada, elimina archivos y exportaciones, y suma a `metric_daily('accounts_deleted')`; tras borrar, el correo puede
      volver a registrarse.
- [ ] Commit.

### Tarea 1.11 — Mantenimiento diario completo y `metric_daily`

- [ ] **Tests**: `maintenance:daily` purga tokens y exportaciones vencidas, marca `overdue`, anonimiza `reminder_sends` y
      `receipt_access_log` > 12 meses (pone `user_id` a null y borra `provider_message_id`/`ip_hash`), escribe las métricas
      de spec §12 del día anterior.
- [ ] Commit.

### Tarea 1.12 — Panel: métricas de la V1 y bitácora sin destinatario

- [ ] **Tests** `Feature/Panel`: página «Métricas» con las series de spec §12 y exportación CSV; «Bitácora de envíos» lista
      `reminder_sends` con tipo, canal, estado, código de error y fecha, **sin** correo, `chat_id` ni alias; un admin no puede
      abrir ni descargar comprobantes (ruta inexistente en el panel).
- [ ] Commit.

### Tarea 1.13 — Webhook de Resend (rebotes y quejas)

- [ ] **Tests**: firma del webhook verificada (según la doc oficial del proveedor; si usa Svix, verificar con su librería);
      `email.bounced` duro → `users.email_bounced_at` y aviso en la app; `email.complained` → desactivar el canal de correo de
      ese usuario hasta que lo reactive.
- [ ] Commit.

### Tarea 1.14 — Candado de autorización: IDOR por endpoint

- [ ] **Tests** `Feature/Authorization/IdorTest`: tabla de **todas** las rutas autenticadas de la app pública (obtenida de
      `Route::getRoutes()` filtrando el *middleware* `auth`), y para cada una, con un segundo usuario autenticado y un recurso
      del primero: 403 o 404, y la base de datos no cambia (`assertDatabaseCount` antes/después). El test falla si aparece una
      ruta nueva no cubierta explícitamente (lista blanca de rutas sin recurso, como `/cuenta`).
- [ ] Commit: «Candado anti-IDOR: cada endpoint autenticado probado con un usuario ajeno».

### Tarea 1.15 — Candados de privacidad: sin PII en logs ni en mensajes

- [ ] **Tests** `Guardrails/SinPiiEnLogsTest`: con `Log::spy()`, ejecutar el flujo completo (registro, envío de correo y de
      Telegram, fallo 500 del proveedor, subida de comprobante) y comprobar que ningún mensaje de log contiene el correo, el
      alias ni el `chat_id`; `Guardrails/MensajesSeudonimizadosTest` sobre las plantillas de correo y Telegram.
- [ ] Commit.

### Tarea 1.16 — E2E, accesibilidad y rendimiento

- [ ] Playwright: `obligacion-recordatorio.spec.ts` (crear obligación con fecha a 3 días, correr `reminders:dispatch` con reloj
      falso, ver el correo en el *log mailer*), `comprobante.spec.ts`, `borrar-cuenta.spec.ts`. Capturas escritorio/móvil,
      claro/oscuro. `revisor-a11y` sobre landing, app y panel. Trace de INP en la lista de obligaciones; LCP de la landing < 2,5 s.
- [ ] Commit.

### Tarea 1.17 — Despliegue de la V1 y [PUNTO DE CONTROL] final

- [ ] Generar el SQL de las migraciones nuevas (`migrate --pretend`), entregarlo a César y aplicarlo **solo** con su visto bueno
      (nunca automático en producción). Variables nuevas en SOPS → Coolify (`RECEIPTS_KEY`, `TELEGRAM_BOT_TOKEN`,
      `TELEGRAM_WEBHOOK_SECRET`, `RESEND_WEBHOOK_SECRET`). Volumen de comprobantes en el respaldo cifrado a R2 y **prueba de
      restauración** del dump + archivos en un contenedor vacío.
- [ ] Usuario de prueba de KraftDo: flujo completo en vivo (registro → verificar → obligación desde plantilla con etiqueta «no
      verificada» → vincular Telegram → recibir correo y Telegram con reloj adelantado en staging, nunca manipulando prod →
      subir comprobante → marcar pagado → exportar → borrar). Medir RAM/CPU y confirmar que NFC no cambió.
- [ ] Actualizar `INTEGRATION.md` (Telegram ↔ MVP, Resend webhooks → MVP, MVP → R2) y `subencargados.md`.
- [ ] **[PUNTO DE CONTROL]** Mostrar a César el flujo, las métricas y la lista de pendientes legales: L1 (vigencia), L2
      (Telegram), L4 (transferencias), L7 (retención/respaldos), L11 (proxy), **L18 (probidad: estado de la revisión
      independiente; sin ella, el producto sigue siendo solo para personas y no se menciona a ningún municipio)**. Fin del prompt 9.

---

## Qué NO está en este plan (a propósito)

- OCR, WhatsApp, cuentas compartidas, `.ics`, PWA con *push* (V2/V3 de `spec.md` §11): se planifican después de la V1 con sus
  propias decisiones (9-4, L10).
- Cualquier tarea de oferta, demo, convenio o contacto con municipios: **prohibida** mientras no exista la revisión L18 por
  escrito; con Graneros, prohibida salvo autorización expresa del asesor (spec §0).
- Migraciones automáticas a producción, push y merge sin que César lo pida.

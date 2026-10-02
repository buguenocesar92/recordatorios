# Prompt 9 — MVP «Recordatorios de obligaciones»: especificación de diseño

Fecha: 2026-10-01. Entidad: **KraftDo SpA** [DECISIÓN B3: default = KraftDo SpA]. Solo diseño: no hay repo,
no hay código, no hay servidor asignado. No es asesoría legal.

Fuentes: `../09-mvp-recordatorios.txt` (prompt reescrito con el bloque de probidad), `../originales/hefesto-serie-v2.md`
(PROMPT 5 y PROMPT 9), `../investigacion-9-recordatorios.md`, `../investigacion-05-supabase.md`,
`../DECISIONES-PARA-CESAR.md` (defaults aceptados el 2026-09-30). Las decisiones aplicadas van marcadas en el texto
como `[DECISIÓN n: default = …]`; lo que sigue abierto va en §13.

---

## 0. Límite duro: conflicto de intereses y probidad (no negociable)

El dueño del proyecto es funcionario de la Municipalidad de Graneros y este producto toca pagos municipales. Por eso,
en este MVP y en todo lo que derive de él:

1. **Prohibido usar cualquier cosa de la Municipalidad de Graneros**: código, datos, credenciales, documentos
   internos, contactos, calendarios internos, ordenanzas no publicadas ni conocimiento adquirido en el cargo. Ni como
   referencia, ni como fuente de fechas, ni como ejemplo, ni como «lo que ya sé que funciona». El repo nace con
   `./scaffold new … --shared=kraftdo` en `~/Dev/kraftdo/`, nunca con `--shared=muni` ni dentro de `~/Dev/Graneros`.
   La única forma válida de saber algo sobre la comuna de Graneros es la misma que para las otras 345 comunas: su
   página pública, citada con URL. El plan incluye un candado automático (test que falla si el repo contiene
   referencias a sistemas municipales de Graneros, ver `plan.md` tarea 0.1).
2. **Las fechas y reglas salen solo de fuentes públicas oficiales, citadas** (TGR, SII, ChileAtiende, gob.cl, BCN,
   CONASET/CMF, portales públicos de cada municipio). Todo lo que no se haya confirmado en una de ellas queda
   **«no verificado»** y así se le muestra al usuario.
3. **Público: solo personas naturales** [DECISIÓN 9-1: default = solo personas]. Ofrecer, vender, presentar o
   conversar con **cualquier** municipalidad sobre integrarlo exige **antes** una revisión de conflicto de intereses y
   probidad hecha por un asesor legal **independiente** (no de la Municipalidad de Graneros), cubriendo al menos la
   Ley 18.575 (principio de probidad), la Ley 18.883 (Estatuto Administrativo para Funcionarios Municipales) y la
   normativa de declaración de intereses y patrimonio que el asesor estime aplicable (pendiente **L18** de
   `../DECISIONES-PARA-CESAR.md`). **A Graneros no se le ofrece nada, nunca, salvo que ese asesor lo diga por escrito y
   expresamente.**
4. Este documento **no propone ni prepara** ofertas, presentaciones, convenios ni contactos con municipios. La V3
   (acceso compartido «administradores») se refiere a administradores de edificios o condominios, no a funcionarios.
5. La landing, la política de privacidad y cada mensaje dejan claro que **no es un servicio del Estado ni de ningún
   municipio** y que el pago vale solo en el canal oficial.

---

## 1. Problema

A muchas personas nadie les avisa que vence una obligación periódica (contribuciones, permiso de circulación, SOAP,
patente municipal, derechos de aseo) y se enteran cuando ya hay multa, reajuste o intereses, o cuando van
presencialmente a hacer otro trámite. Los canales oficiales existen, pero el recordatorio depende de la memoria de cada
uno. Falta una agenda personal, confiable y sin fricción, que avise con anticipación y guarde el comprobante para la
próxima vez.

## 2. Usuarios objetivo

- Personas naturales en Chile, dueñas o arrendatarias responsables de un inmueble o de un vehículo, que pagan una o más
  de las cinco obligaciones y han pagado multa o intereses por olvido al menos una vez.
- Perfil secundario (V3): quien administra pagos de familiares (padres mayores) o de una comunidad pequeña.
- Fuera del público: municipios, empresas de cobranza, inmobiliarias, flotas. Si alguno de estos llega por la lista de
  espera, se registra el dato para el aprendizaje de la validación pero no se le vende nada (§0).

## 3. No-objetivos (nunca, en ninguna versión)

- **Nunca** pedir, almacenar ni usar ClaveÚnica, Clave Tributaria, claves de bancos ni ninguna credencial de terceros.
- **Nunca** iniciar sesión, hacer scraping ni automatizar portales de TGR, SII, Registro Civil, aseguradoras ni
  municipios en nombre del usuario. Nada de iframes con login ajeno.
- **Nunca** cobrar ni intermediar pagos dentro de la app: solo un botón «Ir al sitio oficial» con el enlace del canal
  oficial de cada obligación. No se promete «pagar desde la app» ni hoy ni en la V3.
- No calcular montos, multas ni intereses: el monto lo fija el organismo. Como mucho, mostrar la regla general
  publicada con su fuente.
- No pedir RUT, rol de avalúo, patente del vehículo ni dirección en la V1 (minimización, §9). El usuario puede ponerle un
  alias a cada obligación («casa», «auto») y se le advierte que no escriba ahí datos identificatorios.
- Sin Instagram ni Facebook para avisos proactivos (política de ventana de 24 h de Meta; ningún *message tag* encaja).
- Sin analítica de terceros (Google Analytics o similares) [DECISIÓN 6-6: default = ninguna analítica al inicio].
- Sin Supabase [DECISIÓN 5-1 y C6: default = stack estándar]: se descartan `auth.users` compartido, `service_role`
  con radio total y RLS en Postgres. La autorización es por *policies* de Laravel (§7.3).

## 4. Fase de validación (antes de la V1)

Objetivo: comprobar que el problema duele lo suficiente como para que una persona se inscriba y acepte recibir avisos,
antes de construir la V1.

**Entregable**: landing pública en Blade (SEO, sin framework JS) con:
- Propuesta de valor en una frase, qué hace y qué **no** hace (sin claves, sin pagos, no es el Estado).
- Formulario de lista de espera: correo (obligatorio), comuna (opcional, lista de 346 comunas desde el catálogo
  público), qué obligaciones le interesan (casillas), **casilla de consentimiento sin marcar por defecto** con enlace a
  la política de privacidad y al aviso de que no es un servicio estatal.
- **Doble opt-in**: correo con enlace de confirmación (token aleatorio de 32 bytes, guardado con hash, vence a las 72 h,
  un solo uso). Sin confirmación no cuenta y el registro se purga a las 72 h.
- Anti-bots accesible: Cloudflare Turnstile más límite de intentos por IP y por correo, sin CAPTCHA visual
  [DECISIÓN 11-5 (aplicada por analogía): default = Turnstile + rate limit].
- Baja en un clic desde cualquier correo (enlace firmado), que borra el registro.
- Página de confirmación con una pregunta opcional: «¿Has pagado multa o intereses por olvido en el último año?».

**Entrevistas**: 10 conversaciones con inscritos confirmados (guion de 8 preguntas en `plan.md` tarea 0.9: qué
obligaciones, cómo se enteran hoy, qué pasó la última vez que olvidaron, canal preferido, cuánto antes quieren el aviso,
si subirían el comprobante, si pagarían, qué les daría desconfianza).

**Punto de control — umbral de éxito** (César confirma los números, §13 Q1):
- Al menos **100 inscripciones confirmadas en 30 días** desde que la landing está publicada, **y**
- Al menos **10 entrevistas** hechas, **y**
- Al menos **6 de 10** entrevistados han pagado multa o intereses por olvido en el último año **o** declaran que usarían
  el servicio semanalmente/cada vencimiento.

Si no se alcanza, se detiene antes de la V1 y se decide iterar la propuesta o cerrar. La landing y la lista de espera
son también la primera rebanada entregable del plan (fase 0).

## 5. Alcance de la V1

Pre-requisitos de infraestructura (del prompt reescrito §0): hardening del servidor en verde; Coolify tras el Caddy
actual [DECISIÓN B2: default = b]; Cloudflare DNS con token mínimo en SOPS; Resend con dominio de envío verificado (SPF,
DKIM, DMARC) distinto al de alertas del servidor; backups a R2 con restauración probada; secretos con SOPS+age y variables
de Coolify en un proyecto propio del MVP [DECISIÓN B1: default = A]. Dominio de la app `recordatorios.<zona>` y de envío
`avisos.<zona>`, con `<zona>` la zona dedicada a Hefesto [DECISIÓN 9-3 y B6: default = subdominios de la zona dedicada;
**falta que César diga cuál**]. Nada de esto puede degradar `kraftdo-nfc-v2`, que sigue en producción en el mismo VPS.

Funcionalidad:

1. **Registro y acceso** con el stack estándar (correo + contraseña, verificación de correo obligatoria antes de crear
   obligaciones, recuperación de contraseña). Consentimiento explícito versionado al registrarse (§9).
2. **Catálogo de plantillas** por tipo de obligación y comuna, cargado desde `../investigacion-9-recordatorios.md` §1
   con sus URL de fuente. Toda plantilla nace con `verificada = false` y se muestra con la etiqueta **«Fecha no
   verificada: confirma en el sitio oficial»** hasta que el dueño la marque verificada en el panel, dejando fecha,
   fuente y quién verificó. Las plantillas nacionales (contribuciones, SOAP) no tienen comuna; las comunales
   (permiso de circulación, patente, aseo) tienen la fecha legal nacional como base y `verificada_por_comuna = false`.
3. **Obligaciones del usuario**: se crean desde una plantilla (tipo + comuna) o a mano; alias opcional; fecha de
   vencimiento **editable**; recurrencia (anual, semestral, trimestral) heredada de la plantilla y editable; offsets de
   recordatorio por defecto **15 y 3 días antes**, editables entre 1 y 60 días, máximo 4 offsets; canales por obligación
   (correo siempre; Telegram si está vinculado). Cada obligación genera **vencimientos** (una fila por período) con
   estado `pendiente | pagado | vencido`.
4. **Recordatorios** por correo (Resend) y Telegram (bot que el usuario inicia). Contenido seudonimizado: tipo de
   obligación, alias, fecha, botón «Ir al sitio oficial» y enlace a la app. Nunca RUT, rol, patente, dirección ni montos.
5. **Comprobantes**: subir archivo (PDF, JPG, PNG, WebP; máx. 10 MB) a un vencimiento, con historial (varios por
   vencimiento, nunca se sobrescriben), **cifrados en reposo** con clave dedicada, descarga por enlace temporal firmado,
   **registro de accesos** por comprobante. Borrado real al eliminar.
6. **Marcar como pagado**: fija `pagado_el`, cancela los recordatorios pendientes de ese vencimiento y genera el siguiente
   vencimiento según la recurrencia (con la fecha de la plantilla del período siguiente, editable).
7. **Programación**: planificador de Laravel corriendo en UTC cada 15 minutos, calculando «hoy» y la ventana de envío en
   `America/Santiago`, con bitácora idempotente y reintentos (§8) [DECISIÓN 9-2: default = scheduler de Laravel, no
   pg_cron ni n8n].
8. **Métricas** en el panel del dueño (Filament, solo por VPN): inscritos y confirmados en la lista de espera, cuentas,
   cuentas verificadas, obligaciones creadas por tipo, vencimientos, recordatorios por canal y estado, tasa de entrega,
   pagos marcados, comprobantes subidos, bajas y borrados de cuenta. Solo agregados, sin datos personales.
9. **Cuenta**: exportar mis datos (JSON + comprobantes en ZIP) y borrar mi cuenta (§9).

## 6. Arquitectura (decidida)

[DECISIÓN 9-2 y 5-1: default = Laravel + Inertia/React con landing Blade, no Supabase.] Se aplica la arquitectura de
tres capas del dueño, generada con el scaffold (`./scaffold new recordatorios --shared=kraftdo --frontend=inertia-react`):

| Capa | Tecnología | Contenido en este MVP |
|---|---|---|
| Landing pública (SEO) | Blade + Tailwind, a lo sumo Alpine | Portada, lista de espera, confirmación, baja, política de privacidad, términos, «qué no hacemos» |
| App del usuario | Inertia + React + TypeScript; tipos TS generados desde Data classes (`spatie/laravel-data` + `typescript-transformer`), nunca a mano | Registro, acceso, obligaciones, vencimientos, comprobantes, canales, cuenta (exportar/borrar) |
| Panel privado | Filament + Livewire en modo SPA | Dueño: verificación de plantillas, métricas, auditoría; accesible **solo por WireGuard** (allowlist en el proxy) |

Base de datos **MariaDB** (stack declarado), colas con Redis, planificador y *workers* de Laravel en contenedores
propios dentro del mismo proyecto de Coolify. Almacenamiento de comprobantes en un volumen privado del contenedor
(fuera del webroot) incluido en el respaldo cifrado a R2. Correo saliente por el transporte Resend para Laravel
(verificar con context7 la versión compatible con el Laravel que fije el scaffold en el momento de generar; **no asumir
versiones**: leer `composer.json` y `package.json` del repo generado). Telegram por webhook HTTPS con `secret_token`.

Nota de la investigación 5 que motiva la decisión: en una instancia Supabase compartida `auth.users` es único y
`service_role` salta RLS en todos los MVP; con datos personales (comprobantes con domicilio, patente, RUT y deudas) el
riesgo compartido no se justifica, y una instancia por MVP (~1,5 GB en reposo) no cabe en el VPS. El stack estándar da
aislamiento por aplicación, *policies* probadas con tests y MariaDB como el resto del ecosistema KraftDo.

## 7. Modelo de datos

Convención: nombres de tablas y columnas en inglés, `id` ULID, `created_at`/`updated_at`. Todo lo que es dato personal va
marcado **(PD)**; lo que va cifrado en columna con `encrypted` cast, **(ENC)**.

### 7.1 Tablas

| Tabla | Columnas principales | Notas |
|---|---|---|
| `waitlist_signups` | `email` (PD, único), `email_hash` (sha256 con sal, para búsquedas y límites), `comuna_id?`, `interests` (JSON de tipos), `consent_version`, `consented_at`, `ip_hash`, `confirm_token_hash`, `confirm_expires_at`, `confirmed_at?`, `paid_penalty_last_year?` (bool), `unsubscribed_at?` | Sin confirmar a las 72 h → se borra. Baja → se borra la fila (no «soft delete»): nada que conservar. |
| `users` | `email` (PD, único), `password` (argon2id), `email_verified_at`, `name?` (PD, opcional), `locale='es-CL'`, `timezone='America/Santiago'` (fijo en V1), `deletion_requested_at?`, `deletion_execute_at?` | No se pide RUT ni teléfono. |
| `consents` | `user_id`, `document` (`privacy`,`terms`), `version`, `accepted_at`, `ip_hash` | Prueba de la base de licitud. Nunca se borra mientras exista la cuenta; se borra con ella. |
| `comunas` | `code` (CUT oficial, único), `name`, `region_code`, `region_name`, `official_payment_url?` por tipo (JSON `{tipo: url}`), `urls_verified_at?` | Catálogo público (SUBDERE/INE). Graneros es una fila más, llenada solo desde su web pública (§0). |
| `obligation_types` | `code` (`contribuciones`, `permiso_circulacion`, `soap`, `patente_municipal`, `derechos_aseo`), `name`, `scope` (`nacional`/`comunal`), `default_recurrence`, `default_offsets=[15,3]`, `national_payment_url`, `description` | Datos fijos (seed). |
| `obligation_templates` | `type_code`, `comuna_id?` (null = nacional), `period_label` (p. ej. «Cuota 1»), `due_month`, `due_day`, `business_day_rule` (`next_business_day`/`none`), `year?`, `recurrence`, `source_url`, `source_title`, `verified` (bool, default **false**), `verified_at?`, `verified_by_user_id?`, `notes` | Las fechas del §1 de la investigación entran como no verificadas. Cada año se revisan (decretos de prórroga). |
| `obligations` | `user_id`, `type_code`, `comuna_id?`, `template_id?`, `alias?` (PD potencial, se advierte), `recurrence`, `offsets` (JSON, 1–4 valores entre 1 y 60), `channels` (JSON: `email`, `telegram`), `active` | Lo que el usuario sigue. |
| `obligation_dues` | `obligation_id`, `user_id` (desnormalizado para *policies* y consultas), `period_label`, `due_date` (DATE, local), `template_snapshot` (JSON: fuente, verificada o no), `status` (`pending`/`paid`/`overdue`), `paid_at?`, `next_generated` (bool) | Una fila por período. La fecha es DATE, nunca timestamp: evita todo problema de DST. |
| `reminder_sends` | `obligation_due_id`, `user_id`, `offset_days`, `channel`, `target_date` (DATE local), `status` (`planned`/`queued`/`sent`/`failed`/`skipped`), `attempts`, `last_error_code?` (sin PII), `provider_message_id?`, `sent_at?`, `idempotency_key` (= hash de la clave única) | **ÚNICO (`obligation_due_id`, `offset_days`, `channel`, `target_date`)**: la bitácora idempotente del §8. |
| `telegram_links` | `user_id` (único), `chat_id` (ENC), `linked_at`, `blocked_at?`, `last_message_at?` | Un chat por usuario en V1. 403 del bot → `blocked_at` y canal inactivo. |
| `telegram_link_tokens` | `user_id`, `token_hash`, `expires_at` (15 min), `used_at?` | El *deep link* `t.me/<bot>?start=<token>` vincula el chat con la cuenta. |
| `receipts` | `obligation_due_id`, `user_id`, `stored_path` (UUID, sin nombre original), `original_name` (ENC, saneado), `mime`, `size_bytes`, `sha256`, `encryption_key_id`, `uploaded_at`, `deleted_at?` | Contenido cifrado en reposo con clave dedicada (§7.4). El `sha256` detecta duplicados y verifica integridad al descargar. |
| `receipt_access_log` | `receipt_id`, `actor_user_id`, `action` (`view`,`download`,`delete`,`export`), `ip_hash`, `at` | Trazabilidad exigida por el prompt. 12 meses y se anonimiza (§9). |
| `data_exports` | `user_id`, `status`, `path?`, `expires_at`, `downloaded_at?` | ZIP cifrado con enlace firmado de un solo uso, 24 h. |
| `metric_daily` | `date`, `metric`, `value` | Agregados diarios para el panel; sin PD, sobrevive al borrado de cuentas. |

### 7.2 Reglas de integridad

- `reminder_sends` nunca se actualiza de `sent` a otro estado. Editar `due_date` de un vencimiento borra sus filas
  `planned`/`queued` (no las `sent`/`failed`/`skipped`) y replanifica.
- `obligation_dues.status = paid` ⇒ todos sus `reminder_sends` en `planned`/`queued` pasan a `skipped`
  (`last_error_code = 'paid'`).
- Un vencimiento con `due_date < hoy local` y `status = pending` pasa a `overdue` en la corrida de las 08:00; el estado
  `overdue` no genera avisos nuevos en V1 (sería un canal de cobranza, fuera de alcance).
- Borrado de cuenta ⇒ `ON DELETE CASCADE` en todo lo que cuelga de `users`, borrado físico de archivos de comprobantes
  y de exportaciones, y antes de eso un `INSERT` agregado en `metric_daily`.

### 7.3 Autorización (equivalente a RLS, con *policies* de Laravel)

[DECISIÓN 9-2: policies de Laravel, no RLS.] Reglas:

- Toda tabla con `user_id` tiene un *global scope* `OwnedByCurrentUser` aplicado en los modelos de la app pública, y una
  *policy* por modelo (`ObligationPolicy`, `ObligationDuePolicy`, `ReceiptPolicy`, `TelegramLinkPolicy`,
  `DataExportPolicy`) cuyo `view/update/delete` es `$model->user_id === $user->id`.
- El *route model binding* usa `scopeBindings()` en las rutas anidadas (`obligations/{obligation}/dues/{due}`), así un
  `due` ajeno devuelve 404 aunque el ID exista.
- Las Data classes que viajan a Inertia **nunca** incluyen `user_id` ajeno ni campos de otras cuentas; Eloquent no viaja
  sin pasar por Data class.
- El panel Filament usa un *guard* distinto (`admin`) con una tabla `admins` separada de `users`; no existe un rol
  «admin» dentro de `users`. El panel no muestra correos ni comprobantes de usuarios: solo plantillas, métricas y la
  bitácora de envíos **sin** destinatario.
- Candado de pruebas: por cada endpoint de la app pública existe un test «con otro usuario autenticado devuelve
  403/404 y no modifica nada» (`plan.md` tarea 1.14). Es el equivalente a las pruebas automáticas de RLS que recomendaba
  la investigación 5 §8.

### 7.4 Cifrado de comprobantes

- Clave dedicada `RECEIPTS_KEY` (32 bytes, en SOPS → variable de Coolify), **distinta de `APP_KEY`**, con
  `encryption_key_id` en cada fila para rotar sin recifrar todo de golpe.
- Cifrado simétrico autenticado con libsodium (`sodium_crypto_secretbox` o `sodium_crypto_aead_xchacha20poly1305_ietf`,
  extensión estándar de PHP 8.4; verificar en el repo generado). Nonce por archivo, guardado junto al blob.
- Las imágenes se re-codifican antes de cifrar (quita EXIF: el GPS de una foto del comprobante delata el domicilio); los
  PDF se guardan tal cual pero se sirven siempre como `attachment` con `X-Content-Type-Options: nosniff`.
- Descarga: la app descifra en memoria y responde por una ruta firmada temporal (10 min) ligada al usuario; nunca una URL
  pública al archivo.

## 8. Diseño de la programación de avisos

### 8.1 Planificación (al crear/editar un vencimiento o una obligación)

Para cada `offset` de la obligación y cada canal activo: `target_date = due_date − offset días` (aritmética de **fechas
de calendario**, no de timestamps). Se inserta en `reminder_sends` con `status = planned` usando `insertOrIgnore` (el
índice único absorbe duplicados). Si `target_date < hoy local`, no se planifica (no se mandan avisos atrasados al crear).

### 8.2 Despacho (comando `reminders:dispatch`, cada 15 minutos)

```
ahora_local   = now('America/Santiago')
hoy_local     = ahora_local->toDateString()
si ahora_local->hour < 8: salir      // ventana de envío: desde las 08:00 locales
filas = reminder_sends WHERE status='planned' AND target_date <= hoy_local
                        AND target_date >= hoy_local - 2 días       // tolerancia por caídas
                        AND vencimiento sigue 'pending'
por cada fila: UPDATE … SET status='queued' WHERE id=? AND status='planned'   // CAS: solo quien gana encola
               dispatch(SendReminderJob(fila.id))
filas con target_date < hoy_local - 2 días y 'planned' → 'skipped' (error_code 'stale')
```

- El contenedor y el planificador corren en **UTC**; `America/Santiago` solo entra en el cálculo de `hoy_local` y de la
  ventana. Como el despacho es cada 15 minutos y compara **fechas**, el cambio de hora (que en Chile mueve la medianoche,
  no las 08:00) no duplica ni salta avisos: el día del cambio simplemente hay 23 o 25 horas antes de la ventana.
- Idempotencia en tres capas: (1) índice único en `reminder_sends`; (2) transición `planned → queued` con *compare-and-set*;
  (3) el *job* re-lee la fila y no envía si ya está `sent`. Si el *worker* muere entre el envío y el `UPDATE`, puede
  haber un duplicado hacia el proveedor: para correo se envía la clave de idempotencia a Resend (NO VERIFICADO que el
  SDK la soporte en la versión que se use: comprobar en la doc oficial); para Telegram se acepta el riesgo residual
  (la ventana es de milisegundos) y se registra.
- Tests obligatorios con reloj congelado: día de cambio de hora de primavera y de otoño en Chile, medianoche local,
  23:59 UTC con fecha distinta en Santiago, dos corridas simultáneas sobre la misma fila (una sola encola).

### 8.3 Envío y reintentos (`SendReminderJob`)

- Cola `reminders`, `tries = 5`, *backoff* `[60, 300, 900, 3600, 10800]` s, `uniqueFor` por `reminder_send_id`.
- **Correo**: plantilla seudonimizada; `4xx` distinto de 429 → `failed` sin reintento (`last_error_code`); `429`/`5xx` →
  reintento. Rebote duro notificado por webhook de Resend → marcar correo como `bounced` en el usuario y pedir
  corrección en la app.
- **Telegram**: `sendMessage` con `parse_mode` desactivado y sin *preview* de enlaces; `429` → `release(retry_after)`;
  `403 Forbidden` (bot bloqueado) → `telegram_links.blocked_at`, fila `skipped` (`blocked`), sin reintento; límite de
  ritmo con el *middleware* `RateLimited` de la cola: ≤ 1 mensaje/s por chat y ≤ 25/s global.
- Al agotar reintentos: `failed`, y la obligación muestra «no pudimos avisarte por <canal> el <fecha>».
- Nada del contenido del mensaje ni el destinatario van a los logs: solo `reminder_send_id`, canal y código de error.

### 8.4 Mantenimiento programado

Diario a las 03:00 UTC: purgar `waitlist_signups` sin confirmar vencidos, `telegram_link_tokens` vencidos,
`data_exports` vencidos (y su archivo), ejecutar borrados de cuenta cuya gracia terminó, anonimizar `receipt_access_log`
y `reminder_sends` mayores a 12 meses, marcar `overdue`, generar `metric_daily` del día anterior.

## 9. Ley 21.719 (datos personales)

Rol: KraftDo SpA es **responsable** del tratamiento de los datos de los usuarios del MVP (no encargado: aquí no hay
cliente que cargue datos de terceros). El marco y los plazos de respuesta ARCOP siguen lo ya redactado en
`../diseno-06-datos/` (política, procedimiento ARCOP, brechas), adaptado a este producto; todo pendiente de abogado
(L1–L5, L18).

- **Base de licitud**: consentimiento expreso, informado y versionado al registrarse (casilla sin marcar por defecto,
  texto que nombra los canales y los subencargados); registro en `consents`. Para la lista de espera, consentimiento
  más doble opt-in. Retiro del consentimiento = baja/borrado.
- **Minimización**: correo y contraseña; nombre opcional; nada de RUT, rol, patente ni dirección; alias con advertencia;
  comuna solo si el usuario la elige; IP solo como hash con sal para límites y abuso. Los comprobantes son lo más
  sensible (domicilio, patente, RUT, deuda): cifrados, con acceso trazado, sin OCR en V1.
- **Seudonimización hacia terceros**: a Resend y Telegram viaja solo el mínimo (correo o `chat_id` + texto genérico);
  nunca RUT, patente, rol, dirección ni montos. A Cloudflare (proxy) llega el tráfico HTTPS descifrado si se activa la
  nube naranja [DECISIÓN 2B-1: default = con proxy]: declararlo (L11).
- **Derechos ARCOP**: exportación autoservicio (JSON de todas sus tablas + ZIP de comprobantes, enlace de un solo uso,
  24 h); rectificación editando; **borrado de cuenta autoservicio** con gracia de 7 días cancelable, luego borrado físico
  en cascada, archivos incluidos; bloqueo temporal = desactivar obligaciones. Plazo de respuesta a solicitudes por
  correo según `arcop-procedimiento.md` (30 días corridos, prorrogables una vez).
- **Retención**: lista de espera sin confirmar 72 h; confirmada hasta el lanzamiento + 6 meses o hasta la baja; cuenta
  mientras exista; comprobantes mientras el usuario no los borre o borre la cuenta; `reminder_sends` y
  `receipt_access_log` 12 meses y luego anonimizados (se conservan solo agregados en `metric_daily`); respaldos en R2
  según la retención general del servidor (**pendiente L7**: un respaldo puede contener datos ya borrados hasta que expira;
  la política lo dice).
- **Subencargados / destinatarios** (actualizar `../diseno-06-datos/subencargados.md` con una sección propia del MVP):
  Contabo (alojamiento, UE), Cloudflare (DNS, certificados, proxy; R2 para respaldos cifrados del lado del cliente),
  Resend (correo, EE. UU., DPA con SCC), Telegram (mensajes; jurisdicción NO VERIFICADA; [DECISIÓN 6-5: default = canal que
  el usuario elige y contrata directamente, **confirmar en L2** porque aquí el bot es nuestro). En V2 se sumaría el
  proveedor de OCR y Meta (WhatsApp).
- **Transferencias internacionales**: Resend y Cloudflare en EE. UU., Contabo en la UE: mecanismo pendiente del abogado
  (L4). **Vigencia de la ley** pendiente (L1).
- **Seguridad** (art. 14 quinquies, NO VERIFICADO el número): TLS, cifrado de comprobantes, panel solo por VPN, secretos
  fuera del repo, respaldos cifrados, registro de accesos, procedimiento de brechas (`procedimiento-brechas.md`).
- **Sin decisiones automatizadas** con efecto jurídico: los recordatorios son reglas deterministas elegidas por el usuario.
- **Menores**: el registro declara mayoría de edad; no se piden datos de menores.

## 10. Amenazas de seguridad y controles

| Amenaza | Vector | Controles en V1 |
|---|---|---|
| Robo de cuenta | Fuerza bruta, *credential stuffing*, reset de contraseña, sesión robada | argon2id; límite 5 intentos/min por IP+correo y bloqueo progresivo; verificación de correo obligatoria; token de reset de un solo uso y 60 min; regenerar sesión al entrar; cerrar otras sesiones y avisar por correo al cambiar contraseña o correo; cookies `Secure`, `HttpOnly`, `SameSite=Lax`; CSRF; TOTP opcional si el scaffold ya lo trae (si no, V1.1). |
| Comprobantes maliciosos | Subida de archivo con *polyglot*, SVG con script, EXIF con GPS, PDF con JS, *path traversal* en el nombre | Allowlist por **magic bytes** (PDF, JPEG, PNG, WebP), nunca por extensión; sin SVG ni HEIC en V1; máx. 10 MB; nombre regenerado (UUID); re-codificación de imágenes (quita EXIF y *payloads*); servido solo como `attachment` + `nosniff` desde ruta firmada; almacenamiento fuera del webroot y cifrado; ClamAV queda para V2 (costo de RAM). |
| Acceso a comprobantes/obligaciones ajenas (IDOR) | Cambiar un ID en la URL o en el payload de Inertia | *Policies* + *global scope* + `scopeBindings` (§7.3); IDs ULID no secuenciales; test de IDOR por endpoint; descargas por URL firmada ligada al usuario y con vencimiento. |
| Enumeración de cuentas | Mensajes distintos en registro, login, reset y lista de espera; diferencias de tiempo | Misma respuesta genérica («si el correo existe, te enviamos…»); el registro con correo ya usado envía un correo al dueño en vez de revelar; comparación en tiempo constante; límites por IP y por `email_hash`. |
| Abuso del bot de Telegram | Mensajes al webhook falsos, *spam* de `/start`, intentos de adivinar tokens, el bot como oráculo de existencia de cuentas | Webhook con `secret_token` comprobado en cada petición y ruta con segmento aleatorio; el bot solo entiende `/start <token>`, `/stop` y `/ayuda`, ignora todo lo demás y nunca repite texto del usuario; tokens de 32 bytes con hash, 15 min, un uso; límite por `chat_id`; la vinculación se confirma en la web dentro de la sesión, no en el chat; `getUpdates` deshabilitado (es excluyente con webhook). |
| Abuso de la lista de espera / correo | Bots inscribiendo correos ajenos, *mail bombing*, uso del formulario para enviar spam | Turnstile + límites por IP y correo; doble opt-in (sin confirmar no existe); un solo correo de confirmación por correo cada 24 h; plantillas sin contenido controlado por el usuario. |
| Suplantación del servicio (phishing contra nuestros usuarios) | Correos falsos «de recordatorios» con enlaces de pago | Nuestros mensajes nunca piden datos ni incluyen enlaces de pago de terceros; solo enlaces a `recordatorios.<zona>` y al sitio oficial; SPF/DKIM/DMARC con `p=reject` en `avisos.<zona>`; aviso fijo en la app sobre cómo reconocer un mensaje legítimo. |
| Fuga de secretos | Claves en el repo, en logs o en imágenes Docker | SOPS+age, variables de Coolify, `.dockerignore`, escáner de secretos en CI, nada de PII en logs, `RECEIPTS_KEY` separada de `APP_KEY`. |
| Fuga de datos vía panel | Panel Filament expuesto a Internet | Panel servido solo a la IP de WireGuard (allowlist en el proxy) y guard `admin` separado; el panel no ve destinatarios ni archivos. |
| SSRF / dependencia de hosts del cliente | URL «oficial» editable por el usuario | Las URL de pago son de catálogo (`comunas`, `obligation_types`), nunca entradas del usuario; el usuario solo elige comuna y tipo. |
| Degradar NFC | Consumo de RAM/CPU del MVP en el mismo VPS | Límites de recursos en el compose (igual que el bundle, [DECISIÓN 3-6]), colas con `--max-jobs`, medición en reposo antes de abrir inscripciones. |

## 11. V2 y V3 (esbozo, sin diseño)

**V2**
- OCR de comprobantes **con confirmación humana del dato**: comparar Tesseract autohospedado (sin tercero, costo de
  CPU/RAM) con OCR en la nube (nuevo subencargado, datos sensibles salen del VPS: requiere análisis de impacto, L10). La
  salida solo sugiere fecha y tipo; nunca se guarda sin que el usuario confirme.
- WhatsApp con plantillas de utilidad aprobadas y opt-in explícito [DECISIÓN 9-4: default = se decide después de bajar la
  tarjeta de tarifas de Meta para Chile]; mostrar costo estimado por aviso antes de activarlo; Meta entra a subencargados.
- Rebotes y quejas de Resend por webhook; verificación anual de plantillas asistida (lista de URL de fuente para revisar).
- TOTP si no entró en V1; ClamAV en la subida.

**V3**
- Varias propiedades/vehículos agrupados; acceso compartido a una cuenta familiar (invitación por correo, roles
  `owner`/`viewer`) y administradores de condominios **privados** (nunca municipios, §0). Exige rediseñar *policies*
  (de «dueño» a «miembro con rol») y el registro de accesos (quién vio qué comprobante).
- Exportación a calendario (`.ics`) y recordatorios en la app (PWA con *push*).

## 12. Métricas de la V1 (definición)

Diarias en `metric_daily`, mostradas en el panel y exportables en CSV: `waitlist_confirmed`, `users_registered`,
`users_verified`, `obligations_created{type}`, `dues_pending`, `reminders_sent{channel}`, `reminders_failed{channel}`,
`reminders_skipped{reason}`, `delivery_rate{channel}`, `dues_marked_paid`, `receipts_uploaded`, `telegram_linked`,
`accounts_deleted`. Indicadores de la validación en V1: % de usuarios con ≥ 1 obligación, % de vencimientos marcados
pagados antes de la fecha, % con comprobante.

## 13. Preguntas abiertas (para César)

- **Q1. Umbral de la validación** (§4): ¿100 confirmados en 30 días + 10 entrevistas + 6/10 con dolor real? ¿Otro número?
- **Q2. Zona DNS** [B6 sigue sin nombre]: cuál es la zona dedicada a Hefesto para `recordatorios.<zona>` y `avisos.<zona>`.
  Sin esto no hay Resend ni certificado.
- **Q3. ¿La revisión legal L18 (probidad) se hace ahora, aunque el público sea solo personas?** El diseño parte de
  «solo personas», pero el asesor puede opinar que el solo hecho de operar un producto sobre pagos municipales siendo
  funcionario merece revisión aunque no se ofrezca a municipios. Recomendación: pedirla antes de abrir la lista de espera
  al público.
- **Q4. Telegram como subencargado o como canal del usuario** (L2): el bot es nuestro, así que la clasificación de
  6-5 puede no aplicar aquí.
- **Q5. Gracia del borrado de cuenta**: 7 días cancelables (propuesta) o inmediato.
- **Q6. Tope de almacenamiento por usuario**: propuesta 200 MB y 50 comprobantes en V1 (protege el disco del VPS).
- **Q7. TOTP en V1**: solo si el scaffold ya lo trae; si no, ¿V1.1 o bloqueante?
- **Q8. Idioma del repo y nombre del producto**: código en inglés (regla general); el nombre comercial y el dominio los
  decide César; «recordatorios» es un marcador.
- **Q9. Nombre del bot de Telegram y cuenta que lo administra** (debe ser una cuenta de KraftDo, no personal ni municipal).
- **Q10. ¿Se admite HEIC** (fotos de iPhone) en V1 convirtiéndolo en el servidor, o se pide JPG/PNG?

## 14. Dependencias con el resto de la serie

- Antes: prompt 2A (hardening, verde), 2B (Coolify, Resend, R2, Cloudflare) y las decisiones B1, B2, B3, B6.
- No depende del bundle (prompt 3), de n8n (prompt 4) ni de Supabase (prompt 5, descartado para esto).
- Al construir, actualizar `../diseno-14-integracion/INTEGRATION.md` (regla de `../INDICE.md`): nuevas conexiones
  MVP → Resend, MVP ← Telegram (webhook), MVP → R2 (respaldos), Cloudflare → MVP (Turnstile).
- Actualizar `../diseno-06-datos/subencargados.md` y `politica-privacidad.md` con la sección del MVP (§9).

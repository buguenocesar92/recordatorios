# Recordatorios de obligaciones (MVP, KraftDo SpA)

MVP de **KraftDo SpA** para avisar a personas naturales, con anticipación, cuándo vence
una obligación periódica (contribuciones, permiso de circulación, SOAP, patente municipal,
derechos de aseo). **No es un servicio del Estado ni de ningún municipio**, no pide
ClaveÚnica ni ninguna otra clave, y no cobra ni intermedia pagos.

Spec y plan: `docs/superpowers/specs/2026-10-01-recordatorios-design.md` y
`docs/superpowers/plans/2026-10-01-recordatorios.md`. Hoy está construida la **fase 0**
(landing + lista de espera); la V1 no se empieza sin el visto bueno de César
([PUNTO DE CONTROL] de la tarea 0.9).

> Repositorio **solo local**: no tiene remoto y no se publica.

## Decisión de origen: sin paquetes compartidos

Se generó con `./scaffold new recordatorios /home/cesar/Dev --shared=kraftdo
--frontend=livewire` (commit `d5d3bda`) y, por decisión del delegado del dueño (camino b),
nace **sin** `kraftdo/laravel-kraftdo-shared` y **sin ningún paquete municipal**
(`Muni\Shared`, `Muni\Ui`, `muni-graneros/*`, `demo-engine`). Lo que esos paquetes daban
y este producto necesita se reescribió dentro del repo; lo que era municipal (ARCOP de
mesón, personas/RUT, recorridos guiados, catálogo de componentes, videos) se quitó.

Dos desviaciones del plan, a propósito y anotadas:

- El plan pedía `--frontend=inertia-react`; se generó con `livewire`. La fase 0 no usa la
  app Inertia (es Blade puro); la app pública de la V1 tendrá que agregarse antes de la
  tarea 1.3.
- El repo vive en `~/Dev/recordatorios`, no en `~/Dev/kraftdo/recordatorios`.

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
│   ├── Support/
│   │   ├── CredencialesDePlantilla.php # Guarda de arranque en producción
│   │   ├── ReporteDeErrores.php        # Errores solo a un colector propio
│   │   └── Rutas/
│   │       └── MiddlewareDeApi.php     # Pila obligatoria de toda ruta de api/
│   ├── Http/
│   │   └── Middleware/
│   │       └── TwoFactorAuthentication.php # Middleware MFA
│   └── Livewire/
│       └── VerificarMfa.php           # Pantalla del segundo factor
├── docker/
│   ├── docker-entrypoint.prod.sh      # Arranque de producción (baja a www-data)
│   └── supervisord.conf               # Worker de colas y planificador
├── tests/
│   └── Feature/
│       └── CandadosDelEcosistemaTest.php # Candados de configuración, sin paquete
├── Dockerfile                         # Build multi-etapa (assets + FrankenPHP)
├── docker-compose.yml                 # Desarrollo
└── docker-compose.prod.yml            # Endurecimiento de producción
```

## Pendiente para César

Lo que solo César puede dar. Mientras falte, el código usa marcadores en `.env.example`
y no se despliega nada.

- **Zona DNS** (spec Q2) para `recordatorios.<zona>` y `avisos.<zona>`.

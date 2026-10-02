# `--shared`: cablear el proyecto al paquete compartido de su ecosistema

Qué hace la bandera `--shared` del generador, qué reescribe el script, y por qué es obligatoria.
Todo lo de acá sale de `tools/usar-paquete-compartido.php`; si el script cambia, este documento
cambia con él.

```bash
./scaffold new "Nuevo Portal"  --shared=muni
./scaffold new "Nuevo Sistema" --shared=kraftdo
```

Internamente: `php tools/usar-paquete-compartido.php <destino> <muni|kraftdo>`.

## Por qué no hay valor por defecto

`--shared` dice **a qué entidad pertenece el proyecto**. No hay default a propósito: este
scaffold vive en la cuenta de la Municipalidad de Graneros y su `composer.json` exige paquetes
de `muni-graneros/`. Elegir por omisión era meter una entidad dentro de otra —el error que el
ecosistema no comete nunca— y, en la práctica, un proyecto de KraftDo generado «por defecto»
ni siquiera instalaba sin el token de Graneros.

Sin `--shared`, el generador muere antes de copiar nada, enumerando los valores válidos.
Un ecosistema desconocido también: STDERR + `exit(1)`.

(Contraste deliberado: `--frontend` **sí** tiene default —`livewire`—, porque el stack de la
interfaz no le cambia el dueño a nada. Ver [`docs/FRONTEND-INERTIA-REACT.md`](FRONTEND-INERTIA-REACT.md).)

## Los dos ecosistemas

| | `--shared=muni` | `--shared=kraftdo` |
|---|---|---|
| Entidad | Municipalidad de Graneros | KraftDo SpA |
| Paquete | `muni-graneros/laravel-muni-shared` | `kraftdo/laravel-kraftdo-shared` |
| Constraint declarado | `^1.18` | `^1.0` |
| Origen | repositorio VCS de GitHub (`muni-graneros/laravel-muni-shared`) | repositorio VCS de GitHub (`kraftdo/laravel-kraftdo-shared`), **privado**: exige token |
| Clases migradas | 5 | 10 (las 5 + casts y onboarding) |

Ningún paquete se comparte entre entidades. El de KraftDo no entra en un sistema municipal ni
al revés.

## Qué clases deja de tener el proyecto (y de dónde las toma)

| Clase local | `muni` | `kraftdo` |
|---|---|---|
| `App\Helpers\RutHelper` | `Muni\Shared\RutHelper` | `Kraftdo\Shared\Rut\RutHelper` |
| `App\Rules\RutValido` | `Muni\Shared\RutValido` | `Kraftdo\Shared\Rut\RutValido` |
| `App\Support\Coordenadas` | `Muni\Shared\Coordenadas` | `Kraftdo\Shared\Support\Coordenadas` |
| `App\Contracts\PersonaResolverInterface` | `Muni\Shared\Persona\PersonaResolverInterface` | `Kraftdo\Shared\Persona\PersonaResolverInterface` |
| `App\DTOs\PersonaDTO` | `Muni\Shared\Persona\PersonaDTO` | `Kraftdo\Shared\Persona\PersonaDTO` |
| `App\Casts\EncryptedSeguro` | — | `Kraftdo\Shared\Casts\EncryptedSeguro` |
| `App\Models\OnboardingTour` | — | `Kraftdo\Shared\Onboarding\OnboardingTour` |
| `App\Models\OnboardingStep` | — | `Kraftdo\Shared\Onboarding\OnboardingStep` |
| `App\Models\OnboardingProgress` | — | `Kraftdo\Shared\Onboarding\OnboardingProgress` |
| `App\Http\Controllers\OnboardingController` | — | `Kraftdo\Shared\Onboarding\OnboardingController` |

Los namespaces **no coinciden** entre ecosistemas: cada paquete tiene su propio layout. Por eso
el mapeo está en una tabla del script y no en una regla mecánica.

## Qué hace el script, paso a paso

1. **Valida el destino** (tiene que ser un directorio existente) y el ecosistema. Si algo falla,
   STDERR + `exit(1)` **antes** de tocar un solo archivo.
2. **Reescribe las referencias** en todos los `.php` del proyecto, saltando `vendor`,
   `node_modules`, `.git` y `storage`, y saltando los archivos que va a borrar.
3. **Borra sólo los archivos migrados**, nunca la carpeta completa: `app/Support/Geocoder.php`
   sobrevive aunque `app/Support/Coordenadas.php` se vaya.
4. **Elimina las carpetas que quedaron vacías** (`app/Helpers`, `app/Rules`, `app/Support`,
   `app/Contracts`, `app/DTOs`, `app/Casts`).
5. **Poda del README el árbol «Estructura del Scaffold»**: quita las hojas que acaba de borrar
   y las carpetas que quedan sin hijos por esa poda, y redibuja los conectores. Hace falta
   porque `tests/Feature/EstructuraDelReadmeTest` **viaja** al proyecto generado y comprueba
   que cada hoja del árbol exista en disco: sin este paso, todo proyecto `--shared=muni` nacía
   con esa suite en rojo por `app/Helpers/RutHelper.php`. Si el README no tiene el bloque, el
   script muere (STDERR + `exit(1)`) en vez de entregar un proyecto que falla en su primer
   `make test`.
6. **Declara la dependencia** en `composer.json`: el paquete con su constraint **y su entrada en
   `repositories` de tipo `vcs`**. Sin la segunda, Composer responde «could not be found in any
   version» —que suena a nombre mal escrito y manda a buscar por el lado equivocado— y el
   proyecto recién generado no arranca. El nombre de un paquete no dice dónde vive.
7. **Si la entidad no es `muni`, retira los paquetes de la Municipalidad**: todo `muni-graneros/*`
   de `require` y toda entrada de `repositories` que apunte a `muni-graneros/`. Sin este paso,
   `--shared=kraftdo` sumaba el paquete de KraftDo y **dejaba** los de Graneros: el proyecto de
   KraftDo seguía sin instalar fuera de la cuenta de Graneros y, si instalaba, era porque tenía
   su token. Mezclar entidades es justamente lo que no se hace.

Al terminar informa a STDOUT cuántos archivos reescribió, cuántas copias locales borró, cuántas
líneas podó del árbol del README y qué paquete quedó requerido.

## Lo que `--shared` NO resuelve

Dos limitaciones conocidas; ninguna es un bug del script.

- **El proyecto no nace autónomo de Graneros.** `composer.json` exige
  `muni-graneros/laravel-muni-shared` y `muni-graneros/laravel-muni-ui` sin importar `--shared`,
  y la identidad visual (paleta, escudo, `MuniPanel`, `PanelArcopPlugin`) está escrita directo en
  `AdminPanelProvider` y en varias clases de `app/`. Extraer eso a algo neutro es un cambio de
  arquitectura pendiente.
- **Con `--shared=kraftdo` el proyecto no compila tal cual.** El script avisa por STDOUT: quedan
  referencias a `Muni\Shared` y `Muni\Ui` en `app/` porque el módulo de Privacidad (ARCOP) y el
  plugin del panel viven en esos paquetes y todavía no están extraídos a uno sin entidad. Hay que
  reemplazarlas a mano.

## Deriva de versiones: el candado

El constraint del script y el de `composer.json` en la raíz **tienen que subir en el mismo
commit**. Declarar acá una versión más vieja no rompe nada por sí solo —Composer se queda con la
más restrictiva—, pero **miente sobre qué versión pide este cableado**, y así se fue a la rama:
el script quedó en `^1.16` cuando la raíz ya pedía `^1.18`.

`VersionDelPaqueteCompartidoTest` existe exactamente para detectar eso. Si falla, la respuesta
no es ajustar el test: es alinear el número.

## Por qué es PHP y no `sed`

Los namespaces llevan backslashes. El doble escape entre bash y las expresiones regulares es una
fuente segura de errores **silenciosos**: la reescritura «funciona» y deja referencias rotas que
sólo aparecen al ejecutar. En PHP es `str_replace` sobre cadenas literales y no hay escape que
se pueda equivocar.

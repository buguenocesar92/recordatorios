---
paths:
  - '.ai/rules/**'
  - 'boost.json'
  - 'CLAUDE.md'
  - 'AGENTS.md'
---

# Mantener estas reglas y regenerar Boost

## Qué es de Boost y qué es nuestro

| Ruta | Quién manda |
|---|---|
| `.ai/rules/*.md` | **Nuestro.** `boost:install` no lo toca |
| `.ai/rules/boost/` | De Boost: `RuleRepository::syncManaged()` **borra el directorio entero** y lo reescribe |
| `.ai/rules/index.md` | Generado por `RuleRepository::writeIndex()` a partir del `paths:` de cada archivo |

Nunca escribas una regla propia dentro de `.ai/rules/boost/`: se pierde en la próxima
regeneración.

## Formato: sin `paths:`, la regla no existe

`RuleFrontmatter::parse()` solo lee la clave `paths` (lista de globs) del frontmatter
YAML. Un archivo sin frontmatter, o con frontmatter que no parsea, se lee con
`paths => []` y **`writeIndex()` lo descarta del índice**: nadie lo encuentra nunca.
Tras crear o renombrar una regla hay que regenerar `index.md`.

## `--guidelines` y `--skills` van SIEMPRE juntos

`boost:install --guidelines` sin `--skills` entra en modo de banderas explícitas y
`buildGuidelineConfig()` fija `hasSkills = false`. El bloque `Skills Activation` de
`foundation.blade.php` está bajo `@if($assist->hasSkillsEnabled())`, así que **se
reescribe `CLAUDE.md` sin la sección que obliga a activar las skills del proyecto** —y
las skills siguen en disco, simplemente dejan de anunciarse—. Al revés, `--skills` solo
no refresca las guidelines.

Regenerar siempre con `php artisan boost:install --guidelines --skills`, o con
`php artisan boost:update`, que pasa ambas por su cuenta leyendo `boost.json`.

El bloque de uso de herramientas propio va **ANTES** de `<laravel-boost-guidelines>` en
`CLAUDE.md`, para que la regeneración no lo pise.

## Al aprender algo nuevo, se registra acá

Un gotcha que solo vive en la memoria personal de un agente no lo ve nadie más: ni otra
sesión, ni otro editor de IA trabajando el mismo repo. Usa la tool `record-rule` del MCP
de Boost (glob + título + nota corta) o escribe el archivo a mano con su `paths:`. Nada
de secretos, estado transitorio ni cosas obvias leyendo el código.

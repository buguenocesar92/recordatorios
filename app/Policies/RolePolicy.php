<?php

declare(strict_types=1);

namespace App\Policies;

use Muni\Shared\Auditoria\RolePolicy as RolePolicyCompartida;

/**
 * Fachada vacía: NO borrar este archivo.
 *
 * `bezhansalleh/filament-shield` registra la policy del Resource de Roles con
 * la ruta `App\Policies\RolePolicy` HARDCODEADA (ver
 * `Utils::getRolePolicyPath()`/`isRolePolicyRegistered()`), y solo si el
 * archivo existe en disco en `app/Policies/RolePolicy.php` — la resuelve por
 * el mapeo PSR-4 de esa ruta, no por el namespace que declare el archivo. Si
 * este archivo se borra, Shield deja de registrar la policy del todo y el
 * Resource de Roles queda sin ninguna: Filament autoriza TODO cuando un
 * recurso no tiene policy, así que borrar esto sería peor que la duplicación
 * que venía a resolver.
 *
 * El comportamiento real vive en `Muni\Shared\Auditoria\RolePolicy` (delega
 * en los permisos que Shield genera, sin hardcodear ningún rol).
 */
class RolePolicy extends RolePolicyCompartida {}

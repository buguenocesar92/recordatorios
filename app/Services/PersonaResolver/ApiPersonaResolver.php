<?php

namespace App\Services\PersonaResolver;

use Kraftdo\Shared\Persona\PersonaResolverInterface;
use Kraftdo\Shared\Persona\PersonaDTO;

/**
 * Implementación para consultar una API centralizada externa.
 */
class ApiPersonaResolver implements PersonaResolverInterface
{
    public function findByRut(string $rut): ?PersonaDTO
    {
        // Ejemplo de consumo API externa
        /*
        $resp = \Illuminate\Support\Facades\Http::withToken(config('services.personas_api.token'))
            ->acceptJson()
            ->timeout(5)
            ->get(config('services.personas_api.url')."/personas/".urlencode($rut));

        if ($resp->status() === 404) {
            return null;
        }

        return PersonaDTO::fromArray($resp->throw()->json());
        */

        return null;
    }

    public function existsByRut(string $rut): bool
    {
        return false;
    }

    public function getSource(): string
    {
        return 'api';
    }
}

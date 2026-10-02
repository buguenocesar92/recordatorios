<?php

namespace App\Services\PersonaResolver;

use Kraftdo\Shared\Persona\PersonaResolverInterface;
use Kraftdo\Shared\Persona\PersonaDTO;

/**
 * Implementación local buscando en la base de datos local.
 * Reemplazar con el modelo real correspondiente a tu sistema.
 */
class LocalPersonaResolver implements PersonaResolverInterface
{
    public function findByRut(string $rut): ?PersonaDTO
    {
        // Ejemplo genérico para la tabla de personas
        /*
        $persona = $this->query($rut)->first();

        if ($persona === null) {
            return null;
        }

        return new PersonaDTO(
            nroDocumento: $persona->nro_documento,
            nombres: $persona->nombres,
            apellidos: $persona->apellidos,
            fechaNacimiento: $persona->fecha_nacimiento?->format('Y-m-d'),
            sexo: $persona->sexo,
            telefono: $persona->telefono,
            email: $persona->email,
            direccion: $persona->direccion,
            sector: $persona->sector,
            source: 'local',
            system: 'local'
        );
        */

        return null;
    }

    public function existsByRut(string $rut): bool
    {
        return false;
    }

    public function getSource(): string
    {
        return 'local';
    }
}

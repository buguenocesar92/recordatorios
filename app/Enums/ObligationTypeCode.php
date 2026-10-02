<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los cinco tipos de obligación del MVP (spec §7.1, `obligation_types.code`).
 *
 * En la fase 0 todavía no existe la tabla `obligation_types` (tarea 1.2): este
 * enum es la única fuente de los códigos válidos, y el seeder de la V1 sembrará
 * exactamente estos.
 */
enum ObligationTypeCode: string
{
    case Contribuciones = 'contribuciones';
    case PermisoCirculacion = 'permiso_circulacion';
    case Soap = 'soap';
    case PatenteMunicipal = 'patente_municipal';
    case DerechosAseo = 'derechos_aseo';

    public function label(): string
    {
        return match ($this) {
            self::Contribuciones => 'Contribuciones',
            self::PermisoCirculacion => 'Permiso de circulación',
            self::Soap => 'SOAP (seguro obligatorio)',
            self::PatenteMunicipal => 'Patente municipal',
            self::DerechosAseo => 'Derechos de aseo',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }
}

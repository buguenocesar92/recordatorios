<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo público de comunas (CUT). Sin datos personales: se siembra desde
 * database/data/comunas.csv, que cita su fuente oficial.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $region_code
 * @property string $region_name
 */
class Comuna extends Model
{
    /** Catálogo público: no guarda datos de personas, no hay nada que suprimir. */
    public const SUPRESION_POR_BORRADO = true;

    protected $fillable = ['code', 'name', 'region_code', 'region_name'];
}

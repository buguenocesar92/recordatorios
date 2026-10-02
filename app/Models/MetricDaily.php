<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregados diarios para el panel (spec §7.1, §12). Sin datos personales:
 * sobrevive al borrado de cualquier persona, que es justamente para lo que sirve
 * (contar las bajas sin conservar a quien se dio de baja).
 *
 * @property Carbon $date
 * @property string $metric
 * @property int $value
 */
class MetricDaily extends Model
{
    /** Solo agregados: no hay datos personales que suprimir. */
    public const SUPRESION_POR_BORRADO = true;

    protected $table = 'metric_daily';

    protected $fillable = ['date', 'metric', 'value'];

    protected function casts(): array
    {
        return ['date' => 'date', 'value' => 'integer'];
    }

    /** Suma a la métrica del día sin carreras: upsert atómico. */
    public static function incrementar(string $metrica, int $cuanto = 1): void
    {
        static::query()->upsert(
            [['date' => now()->toDateString(), 'metric' => $metrica, 'value' => $cuanto, 'created_at' => now(), 'updated_at' => now()]],
            ['date', 'metric'],
            // MariaDB: VALUES(value) es lo que se intentó insertar (= $cuanto).
            ['value' => DB::raw('value + VALUES(value)'), 'updated_at' => now()],
        );
    }
}

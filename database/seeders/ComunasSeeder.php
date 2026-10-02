<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comuna;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Siembra el catálogo público de comunas desde database/data/comunas.csv
 * (fuente oficial citada en su encabezado). Idempotente: upsert por código.
 * Es dato público, no de demostración: también corre en producción.
 */
class ComunasSeeder extends Seeder
{
    public function run(): void
    {
        $archivo = fopen(database_path('data/comunas.csv'), 'r');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo abrir database/data/comunas.csv');
        }

        $filas = [];
        $ahora = now();

        while (($linea = fgetcsv($archivo, escape: '\\')) !== false) {
            $primera = (string) ($linea[0] ?? '');

            if ($primera === '' || str_starts_with($primera, '#') || $primera === 'code') {
                continue;
            }

            $filas[] = [
                'code' => $primera,
                'name' => (string) $linea[1],
                'region_code' => (string) $linea[2],
                'region_name' => (string) $linea[3],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        fclose($archivo);

        Comuna::query()->upsert($filas, ['code'], ['name', 'region_code', 'region_name', 'updated_at']);
    }
}

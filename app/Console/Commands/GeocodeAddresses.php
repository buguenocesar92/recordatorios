<?php

namespace App\Console\Commands;

use App\Support\Geocoder;
use Illuminate\Console\Command;

/**
 * Geocodifica direcciones a coordenadas de cualquier modelo Eloquent.
 * Respeta la política de Nominatim (1 petición/segundo).
 *
 * Ojo con lo que hace en masa: cada registro manda su dirección a un servicio de
 * terceros. La base de licitud, qué se envía y qué no, y por qué sin un contacto
 * declarado no se consulta nada están en el docblock de App\Support\Geocoder.
 * Apuntar este comando a un modelo con domicilios de vecinos es una decisión de
 * tratamiento de datos personales (Ley 21.719), no una tarea de mantenimiento.
 */
class GeocodeAddresses extends Command
{
    protected $signature = 'geo:geocode
        {model : Nombre completo de la clase del modelo Eloquent (ej: App\\Models\\User)}
        {--address=direccion : Columna que contiene la dirección de texto plano}
        {--lat=latitud : Columna para guardar la latitud}
        {--lng=longitud : Columna para guardar la longitud}
        {--limit=50 : Límite de registros a procesar (0 para sin límite)}';

    protected $description = 'Geocodifica direcciones de texto plano de cualquier modelo a coordenadas usando OpenStreetMap (Nominatim).';

    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("La clase del modelo '{$modelClass}' no existe.");

            return self::FAILURE;
        }

        $addressCol = (string) $this->option('address');
        $latCol = (string) $this->option('lat');
        $lngCol = (string) $this->option('lng');
        $limit = (int) $this->option('limit');

        $query = $modelClass::query()
            ->whereNotNull($addressCol)
            ->where($addressCol, '!=', '')
            ->whereNull($latCol);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->info("No hay registros pendientes de geocodificar para {$modelClass}.");

            return self::SUCCESS;
        }

        $this->info("Geocodificando {$records->count()} registro(s) — 1 por second (política Nominatim)…");

        $ok = 0;
        $failed = 0;

        foreach ($records as $i => $record) {
            if ($i > 0) {
                sleep(1);
            }

            $address = $record->{$addressCol};
            $r = Geocoder::buscar($address);

            if ($r) {
                $record->forceFill([
                    $latCol => $r['lat'],
                    $lngCol => $r['lng'],
                ])->save();
                $ok++;
                $this->line("  ✓ Registro ID {$record->getKey()} → {$r['lat']}, {$r['lng']}");
            } else {
                $failed++;
                $this->line("  ✗ Registro ID {$record->getKey()} — sin resultados para «{$address}»");
            }
        }

        $this->newLine();
        $this->info("Proceso terminado. Geocodificados: {$ok}. Fallidos: {$failed}.");

        return self::SUCCESS;
    }
}

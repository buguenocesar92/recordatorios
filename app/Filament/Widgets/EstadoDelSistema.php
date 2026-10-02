<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Si las piezas de las que depende el sistema responden.
 *
 * Cada revisión trae cuánto tardó. Un servicio que contesta en cuatro segundos
 * está técnicamente arriba y en la práctica caído, y sin el número eso no se ve.
 *
 * @property-read Collection<int, array<string, mixed>> $chequeos
 */
class EstadoDelSistema extends WidgetDePanel
{
    protected string $view = 'filament.widgets.estado-del-sistema';

    protected static ?int $sort = 1;

    /**
     * Dos de seis: son dos o tres cifras cortas. A ancho completo desperdicia
     * media pantalla para decir «todo bien».
     */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    /**
     * Por encima de esto, responder deja de ser «bien» aunque responda.
     */
    private const LENTO_MS = 500;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function chequeos(): Collection
    {
        return collect([
            // Una consulta de verdad, no solo abrir la conexión: «getPdo()» devuelve
            // el objeto aunque el servidor esté caído del otro lado.
            $this->medir('Base de datos', function (): bool {
                DB::connection()->select('select 1');

                return true;
            }),
            $this->medir('Caché', function (): bool {
                Cache::put('panel:latido', '1', 5);

                return Cache::get('panel:latido') === '1';
            }),
        ]);
    }

    /**
     * @param  callable(): bool  $revision
     * @return array<string, mixed>
     */
    private function medir(string $nombre, callable $revision): array
    {
        $desde = microtime(true);

        try {
            $bien = $revision();
            $detalle = null;
        } catch (\Throwable $e) {
            $bien = false;
            // El mensaje de la excepción puede traer la cadena de conexión
            // entera, con usuario y contraseña. Se dice el tipo de problema, no
            // el mensaje: una pantalla de diagnóstico es justo donde se filtran
            // las credenciales.
            $detalle = class_basename($e);
        }

        $ms = (int) round((microtime(true) - $desde) * 1000);

        return [
            'nombre' => $nombre,
            'estado' => match (true) {
                ! $bien => 'caído',
                $ms > self::LENTO_MS => 'lento',
                default => 'bien',
            },
            'detalle' => $detalle,
            'ms' => $ms,
        ];
    }

    protected function olvidarLoCalculado(): void
    {
        unset($this->chequeos);
    }
}

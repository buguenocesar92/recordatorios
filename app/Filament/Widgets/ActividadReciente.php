<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Cuánto se usó el sistema en las últimas dos semanas.
 *
 * No está para adornar el escritorio: un sistema municipal que deja de usarse
 * de un día para otro casi siempre está roto para alguien —un rol que perdió
 * permisos, un trámite que dejó de llegar— y sin esto nadie lo nota hasta que
 * llega el reclamo. La caída se ve antes que el reclamo.
 *
 * El gráfico se dibuja en SVG, sin librería. No es purismo: una de gráficos son
 * decenas de kilobytes para catorce barras, y además dibuja sobre un lienzo, que
 * es opaco para un lector de pantalla. Acá cada barra lleva su fecha y su
 * número, y debajo va la misma información como texto.
 *
 * @property-read Collection<int, array<string, mixed>> $dias
 */
class ActividadReciente extends WidgetDePanel
{
    protected string $view = 'filament.widgets.actividad-reciente';

    protected static ?int $sort = 4;

    /** Cuatro de seis: necesita ancho para que catorce barras se distingan. */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 6, 'xl' => 4];

    private const DIAS = 14;

    /**
     * Una consulta agregada, no los registros.
     *
     * Agrupar en la base y traer catorce filas es lo que permite que esto siga
     * costando lo mismo cuando la tabla tenga millones: traer los registros para
     * contarlos en PHP funciona el primer mes y se cae el segundo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function dias(): Collection
    {
        try {
            $desde = Carbon::today()->subDays(self::DIAS - 1);

            /** @var array<string, int> $conteos */
            $conteos = Cache::remember(
                'escritorio:actividad:'.$desde->toDateString(),
                now()->addMinutes(10),
                fn (): array => DB::table('activity_log')
                    ->selectRaw('DATE(created_at) as dia, COUNT(*) as total')
                    ->where('created_at', '>=', $desde)
                    ->groupBy('dia')
                    ->pluck('total', 'dia')
                    ->all()
            );
        } catch (Throwable) {
            // La tarjeta lo dice y ofrece reintentar; el resto del escritorio
            // sigue funcionando.
            $this->huboError = true;

            return collect();
        }

        // Los días sin actividad tienen que aparecer con cero. Si se omitieran,
        // el gráfico se vería continuo y un fin de semana muerto pasaría por
        // normal.
        /** @var Collection<int, array<string, mixed>> $dias */
        $dias = collect(range(0, self::DIAS - 1))->map(function (int $i) use ($desde, $conteos): array {
            $fecha = $desde->copy()->addDays($i);

            return [
                'fecha' => $fecha,
                'etiqueta' => $fecha->translatedFormat('D j'),
                'total' => (int) ($conteos[$fecha->toDateString()] ?? 0),
            ];
        })->values();

        return $dias;
    }

    /** El valor más alto, que fija la escala. Nunca cero, para no dividir por él. */
    #[Computed]
    public function maximo(): int
    {
        return max(1, (int) $this->dias->max('total'));
    }

    #[Computed]
    public function total(): int
    {
        return (int) $this->dias->sum('total');
    }

    protected function olvidarLoCalculado(): void
    {
        unset($this->dias, $this->maximo, $this->total);

        Cache::forget('escritorio:actividad:'.Carbon::today()->subDays(self::DIAS - 1)->toDateString());
    }
}

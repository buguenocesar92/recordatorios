<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\MetricDaily;
use App\Models\WaitlistSignup;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Métricas de la validación (spec §4, plan 0.7): solo agregados.
 *
 * Todas las consultas son COUNT/SUM agrupados: la página nunca carga filas de
 * la lista de espera, así que no puede mostrar un correo aunque se quiera. Lo
 * vigila WaitlistMetricsTest.
 */
class Metricas extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.metricas';

    protected static ?string $title = 'Métricas de la lista de espera';

    protected static ?string $navigationLabel = 'Métricas';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 1;

    /**
     * @return array{inscritos: int, confirmados: int, bajas: int, respondieron: int, porcentaje_multa: int|null}
     */
    public function indicadores(): array
    {
        $fila = WaitlistSignup::query()
            ->toBase()
            ->selectRaw('COUNT(*) AS inscritos')
            ->selectRaw('SUM(CASE WHEN confirmed_at IS NOT NULL THEN 1 ELSE 0 END) AS confirmados')
            ->selectRaw('SUM(CASE WHEN confirmed_at IS NOT NULL AND paid_penalty_last_year IS NOT NULL THEN 1 ELSE 0 END) AS respondieron')
            ->selectRaw('SUM(CASE WHEN confirmed_at IS NOT NULL AND paid_penalty_last_year = 1 THEN 1 ELSE 0 END) AS con_multa')
            ->first();

        $respondieron = (int) ($fila->respondieron ?? 0);

        return [
            'inscritos' => (int) ($fila->inscritos ?? 0),
            'confirmados' => (int) ($fila->confirmados ?? 0),
            'bajas' => (int) MetricDaily::query()->where('metric', 'waitlist_unsubscribed')->sum('value'),
            'respondieron' => $respondieron,
            'porcentaje_multa' => $respondieron > 0 ? (int) round(100 * (int) ($fila->con_multa ?? 0) / $respondieron) : null,
        ];
    }

    /**
     * Confirmados por día en los últimos 30 días (fecha local), con los días
     * sin confirmaciones en cero para que la serie no salte.
     *
     * @return list<array{dia: string, confirmados: int}>
     */
    public function serieDiaria(): array
    {
        $desde = now()->subDays(29)->startOfDay();

        $porDia = WaitlistSignup::query()
            ->toBase()
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '>=', $desde)
            ->selectRaw('DATE(confirmed_at) AS dia, COUNT(*) AS total')
            ->groupBy(DB::raw('DATE(confirmed_at)'))
            ->pluck('total', 'dia');

        $serie = [];

        for ($dia = $desde->copy(); $dia->lte(now()); $dia->addDay()) {
            $clave = $dia->toDateString();
            $serie[] = ['dia' => $clave, 'confirmados' => (int) ($porDia[$clave] ?? 0)];
        }

        return $serie;
    }

    public static function formatearDia(string $dia): string
    {
        $fecha = Carbon::parse($dia);
        $fecha->setLocale('es');

        return $fecha->isoFormat('ddd D MMM');
    }
}

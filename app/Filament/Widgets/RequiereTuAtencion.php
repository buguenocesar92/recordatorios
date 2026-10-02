<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Avisos\Aviso;
use App\Avisos\FuenteDeAvisos;
use App\Avisos\RegistroDeFuentes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Lo que requiere atención hoy.
 *
 * Reúne en una sola lista lo que las fuentes del sistema declaran accionable, y
 * pone la acción al lado de cada fila. El paquete no sabe qué mira cada fuente:
 * solo las ordena, las acota y las dibuja.
 *
 * @property-read Collection<int, Aviso> $avisos
 */
class RequiereTuAtencion extends WidgetDePanel
{
    protected string $view = 'filament.widgets.requiere-tu-atencion';

    protected static ?int $sort = 0;

    /**
     * Cuatro de seis: es lo primero que se mira y lo que más texto lleva, pero
     * deja sitio al costado para el estado del sistema en vez de empujarlo a
     * una fila propia.
     */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 4];

    /**
     * Cuántos avisos se muestran.
     *
     * Con 504 pendientes la tarjeta medía 28.858 px —32 pantallas— y empujaba
     * el resto del escritorio fuera de la vista; se midió en el navegador. Ocho
     * es lo que cabe sin bajar y el techo de lo que alguien atiende de una
     * sentada. Los omitidos se cuentan al pie.
     */
    public const MAXIMO_VISIBLE = 8;

    /**
     * A qué fuente pertenece cada id.
     *
     * Se arma junto con «avisos», en el mismo recorrido de las mismas
     * fuentes, para que nunca quede desincronizado con la lista y para no
     * releer «avisos()» de cada fuente una segunda vez al resolver.
     *
     * Si dos fuentes visibles devolvieran un id igual, gana la primera que
     * se recorrió: el contrato de «FuenteDeAvisos::resolver()» promete a
     * cada fuente que el id que recibe es suyo, y ante una colisión solo una
     * puede quedarse con la resolución.
     *
     * @var array<string, FuenteDeAvisos>
     */
    private array $fuentePorId = [];

    /**
     * Los avisos vigentes de quien mira, ya ordenados.
     *
     * Computada para que la lista se arme UNA vez por render: la vista la
     * consulta tres veces —el contador, la lista y el estado vacío—.
     *
     * @return Collection<int, Aviso>
     */
    #[Computed]
    public function avisos(): Collection
    {
        try {
            $fuentePorId = [];

            $avisos = $this->fuentesVisibles()
                ->flatMap(function (FuenteDeAvisos $fuente) use (&$fuentePorId): array {
                    $avisosDeFuente = collect($fuente->avisos())->all();

                    foreach ($avisosDeFuente as $aviso) {
                        $fuentePorId[$aviso->id] ??= $fuente;
                    }

                    return $avisosDeFuente;
                })
                ->sortBy(fn (Aviso $aviso): int => $aviso->orden)
                ->values();

            $this->fuentePorId = $fuentePorId;

            return $avisos;
        } catch (Throwable $e) {
            Log::error('El escritorio no pudo armar los avisos', ['excepcion' => $e]);

            $this->huboError = true;
            $this->fuentePorId = [];

            return collect();
        }
    }

    /** @return Collection<int, Aviso> */
    public function visibles(): Collection
    {
        return $this->avisos->take(self::MAXIMO_VISIBLE);
    }

    public function omitidos(): int
    {
        return max(0, $this->avisos->count() - self::MAXIMO_VISIBLE);
    }

    /**
     * Resuelve un aviso desde su propia fila.
     *
     * El id viaja desde el navegador, así que se comprueba contra los avisos
     * vigentes de ESTA persona en ESTE request. Una lista blanca fija —como la
     * constante de cuatro claves que tenía el widget original— alcanza mientras
     * la lista sea corta y del propio sistema; derivarla de los avisos cubre
     * además el caso de un aviso que dejó de aplicar entre el render y el clic.
     */
    public function resolver(string $id): void
    {
        // Acceder a «avisos» primero garantiza que «fuentePorId» quede armado:
        // ambos se calculan en el mismo recorrido, dentro del método de arriba.
        abort_unless($this->avisos->contains(fn (Aviso $aviso): bool => $aviso->id === $id), 403);

        if (isset($this->fuentePorId[$id])) {
            $this->fuentePorId[$id]->resolver($id);
        }

        $this->olvidarLoCalculado();
    }

    /** @return Collection<int, FuenteDeAvisos> */
    private function fuentesVisibles(): Collection
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            return collect();
        }

        return collect(app(RegistroDeFuentes::class)->todas())
            ->map(fn (string $clase): FuenteDeAvisos => app($clase))
            ->filter(fn (FuenteDeAvisos $fuente): bool => $fuente->visiblePara($usuario))
            ->values();
    }

    protected function olvidarLoCalculado(): void
    {
        unset($this->avisos);
    }
}

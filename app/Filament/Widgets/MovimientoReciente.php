<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Spatie\Activitylog\Models\Activity;

/**
 * Qué se tocó en el panel, y quién.
 *
 * La actividad se filtra por lo que cada persona administra. El historial guarda
 * todo lo que pasa en el panel, y sin filtro alguien de una oficina veía los
 * títulos de lo que tocaron las demás —que a veces son nombres de personas—.
 *
 * @property-read Collection<int, array<string, mixed>> $cambios
 */
class MovimientoReciente extends WidgetDePanel
{
    protected string $view = 'filament.widgets.movimiento-reciente';

    protected static ?int $sort = 2;

    /** Ancho completo: son diez filas de texto y se leen mejor sin cortar. */
    protected int|string|array $columnSpan = 'full';

    /** Cuántos cambios se asoman. Los suficientes para responder «¿qué pasó?». */
    public const CAMBIOS = 10;

    /**
     * Los últimos cambios, ya filtrados por lo que esta persona administra.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function cambios(): Collection
    {
        try {
            $mios = $this->modelosQuePuedeVer();

            if ($mios->isEmpty()) {
                return collect();
            }

            /** @var Collection<int, Activity> $registros */
            $registros = Activity::query()
                // Eager: sin esto son dos consultas por fila para pintar diez.
                ->with(['causer', 'subject'])
                // Lo que no tiene sujeto —un inicio de sesión, una tarea del
                // sistema— queda fuera: sin sujeto no hay forma de saber si esta
                // persona tenía por qué verlo.
                ->whereIn('subject_type', $mios->all())
                ->latest('id')
                ->limit(self::CAMBIOS)
                ->get();

            /** @var Collection<int, array<string, mixed>> $filas */
            $filas = $registros
                ->map(fn (Activity $registro): array => [
                    'cuando' => $registro->created_at?->diffForHumans(),
                    'fecha' => $registro->created_at?->format('d/m/Y H:i'),
                    'quien' => $this->nombreDelAutor($registro->causer instanceof Model ? $registro->causer : null),
                    'que' => match ($registro->description) {
                        'created' => 'Creó',
                        'updated' => 'Editó',
                        'deleted' => 'Borró',
                        'restored' => 'Restauró',
                        default => (string) $registro->description,
                    },
                    'color' => match ($registro->description) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    },
                    'sobre' => $this->nombreDelSujeto($registro),
                ]);

            return $filas;
        } catch (\Throwable $e) {
            Log::error('El escritorio no pudo leer la actividad', ['excepcion' => $e]);

            $this->huboError = true;

            return collect();
        }
    }

    /**
     * Los modelos cuyos listados esta persona tiene permiso de ver.
     *
     * Se pregunta a los recursos del panel en vez de mantener una lista a mano:
     * un recurso nuevo queda cubierto solo, y uno al que le cambien el permiso
     * no se queda con una excepción olvidada acá.
     *
     * @return Collection<int, class-string<Model>>
     */
    private function modelosQuePuedeVer(): Collection
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel === null) {
            return collect();
        }

        return collect($panel->getResources())
            ->filter(fn (string $recurso): bool => is_subclass_of($recurso, Resource::class) && $recurso::canViewAny())
            ->map(fn (string $recurso): string => $recurso::getModel())
            ->unique()
            ->values();
    }

    /**
     * Quién hizo el cambio.
     *
     * Un cambio hecho por una tarea automática no tiene autor, y dejar la celda
     * vacía se lee como un dato que falta.
     */
    private function nombreDelAutor(?Model $autor): string
    {
        $nombre = $autor?->getAttribute('name');

        return is_string($nombre) && $nombre !== '' ? $nombre : 'El sistema';
    }

    /**
     * Cómo se llama la cosa que se tocó.
     *
     * El nombre está en «titulo» o en «nombre» según la tabla, y se lee con
     * «getAttribute» porque el sujeto puede ser cualquier modelo del sistema. Si
     * el registro se borró del todo, se dice qué era: más útil que un hueco.
     */
    private function nombreDelSujeto(Activity $registro): string
    {
        $sujeto = $registro->subject instanceof Model ? $registro->subject : null;

        $nombre = $sujeto?->getAttribute('titulo') ?? $sujeto?->getAttribute('nombre');

        if (is_string($nombre) && $nombre !== '') {
            return $nombre;
        }

        return class_basename((string) $registro->subject_type).' N.º '.$registro->subject_id;
    }

    protected function olvidarLoCalculado(): void
    {
        unset($this->cambios);
    }
}

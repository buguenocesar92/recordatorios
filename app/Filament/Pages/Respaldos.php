<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Los respaldos, para poder bajar uno sin entrar al servidor.
 *
 * El respaldo ya se hacía solo cada noche, pero vivía dentro del servidor: para
 * llevarse una copia había que pedirle a alguien con acceso que la sacara. En un
 * municipio eso significa esperar a que esa persona esté disponible, y suele
 * pedirse justo cuando no lo está.
 *
 * Un respaldo es la base de datos entera, con todos los datos personales que
 * contenga: la pantalla lleva permiso propio y no es para cualquiera con acceso
 * al panel.
 */
class Respaldos extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.respaldos';

    protected static ?string $title = 'Respaldos';

    protected static ?string $navigationLabel = 'Respaldos';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 98;

    /**
     * Los respaldos que hay, del más nuevo al más viejo.
     *
     * @return array<int, array{nombre: string, peso: string, cuando: string}>
     */
    public function respaldos(): array
    {
        $disco = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $carpeta = config('backup.backup.name', config('app.name'));

        if (! $disco->exists($carpeta)) {
            return [];
        }

        return collect($disco->files($carpeta))
            ->filter(fn (string $ruta): bool => str_ends_with($ruta, '.zip'))
            ->sortByDesc(fn (string $ruta): int => $disco->lastModified($ruta))
            ->map(fn (string $ruta): array => [
                'nombre' => basename($ruta),
                'peso' => $this->enPalabras($disco->size($ruta)),
                'cuando' => date('d/m/Y H:i', $disco->lastModified($ruta)),
            ])
            ->values()
            ->all();
    }

    /**
     * Entrega uno de los respaldos.
     *
     * El nombre llega desde el navegador, así que se comprueba dos veces: que
     * sea solo un nombre —sin barras ni «..»— y que además exista en la lista de
     * los que hay. Sin lo primero, «../../.env» entrega las claves del sistema;
     * lo segundo lo hace redundante a propósito, porque esta es de las
     * comprobaciones que no conviene que dependan de una sola línea.
     */
    public function descargar(string $nombre): StreamedResponse
    {
        if (basename($nombre) !== $nombre || ! str_ends_with($nombre, '.zip')) {
            throw new NotFoundHttpException('Respaldo no encontrado.');
        }

        $existentes = array_column($this->respaldos(), 'nombre');

        if (! in_array($nombre, $existentes, true)) {
            throw new NotFoundHttpException('Respaldo no encontrado.');
        }

        $disco = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $carpeta = config('backup.backup.name', config('app.name'));

        // Después de las dos comprobaciones y antes de entregar nada: anotar
        // una descarga que se va a rechazar llenaría el registro de cosas que
        // no pasaron. El permiso dice quién PUEDE llevarse la base entera; esto
        // dice quién se la llevó y cuándo, que es lo primero que se pregunta
        // tras una filtración (Ley 21.719, trazabilidad de accesos).
        activity()
            ->causedBy(auth()->user())
            ->withProperties(['archivo' => $nombre])
            ->log('descarga de respaldo');

        // Se manda por trozos y no cargándolo en memoria: un respaldo de varios
        // cientos de megas agotaría la memoria del proceso al leerlo entero.
        return $disco->download($carpeta.'/'.$nombre);
    }

    private function enPalabras(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return round($bytes / 1024 ** 3, 1).' GB';
        }

        if ($bytes >= 1024 ** 2) {
            return round($bytes / 1024 ** 2, 1).' MB';
        }

        return round($bytes / 1024).' KB';
    }
}

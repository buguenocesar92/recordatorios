<?php

declare(strict_types=1);

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatar dibujado en el propio servidor, sin salir a ningún servicio externo.
 *
 * El proveedor de fábrica de Filament arma la imagen contra ui-avatars.com y le pasa el
 * nombre del funcionario en la URL. Eso es una petición saliente en cada carga del
 * panel, desde el navegador del funcionario, hacia un tercero que la Municipalidad no
 * contrató ni audita: la nómina de quién entra y a qué hora queda en manos ajenas.
 *
 * Acá se generan las iniciales sobre un color derivado del propio nombre y se entregan
 * como data: URI, así que el navegador no pide nada a nadie. El color sale de un hash
 * para que cada persona tenga siempre el mismo y se distingan entre sí.
 */
class AvatarLocalProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        /** @var string $nombre */
        $nombre = $record->getAttribute('name') ?? '';

        return 'data:image/svg+xml;base64,'.base64_encode(
            $this->svg($this->iniciales($nombre), $this->color($nombre))
        );
    }

    private function iniciales(string $nombre): string
    {
        $palabras = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $iniciales = collect($palabras)
            ->take(2)
            ->map(fn (string $palabra): string => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->implode('');

        return $iniciales !== '' ? $iniciales : '?';
    }

    /**
     * Tono estable derivado del nombre: la misma persona siempre con el mismo color.
     */
    private function color(string $nombre): string
    {
        $tono = hexdec(substr(md5(Str::lower($nombre)), 0, 2)) / 255 * 360;

        return sprintf('hsl(%d, 45%%, 35%%)', (int) $tono);
    }

    private function svg(string $iniciales, string $color): string
    {
        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="{$iniciales}">
                <rect width="100" height="100" fill="{$color}"/>
                <text x="50" y="50" fill="#ffffff" font-family="system-ui, sans-serif" font-size="42" font-weight="600" text-anchor="middle" dominant-baseline="central">{$iniciales}</text>
            </svg>
            SVG;
    }
}

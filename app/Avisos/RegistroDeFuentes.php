<?php

declare(strict_types=1);

namespace App\Avisos;

use InvalidArgumentException;

/**
 * Las fuentes de avisos que un sistema declaró.
 *
 * Vive como singleton en el contenedor: se llena una vez, desde el
 * ServiceProvider del sistema, y se lee en cada render del escritorio.
 *
 * Comprueba el contrato al registrar y no al usar, para que el error salte
 * arrancando la aplicación —donde se lee y se entiende— en vez de a mitad de un
 * render, con el escritorio ya a medio dibujar.
 */
class RegistroDeFuentes
{
    /** @var array<int, class-string<FuenteDeAvisos>> */
    private array $fuentes = [];

    /** @param class-string $clase */
    public function registrar(string $clase): void
    {
        if (! is_subclass_of($clase, FuenteDeAvisos::class)) {
            throw new InvalidArgumentException(
                "«{$clase}» no implementa FuenteDeAvisos: el escritorio no sabría qué pedirle.",
            );
        }

        if (! in_array($clase, $this->fuentes, true)) {
            $this->fuentes[] = $clase;
        }
    }

    /** @return array<int, class-string<FuenteDeAvisos>> */
    public function todas(): array
    {
        return $this->fuentes;
    }
}

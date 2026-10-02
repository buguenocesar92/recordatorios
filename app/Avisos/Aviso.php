<?php

declare(strict_types=1);

namespace App\Avisos;

/**
 * Una cosa sobre la que alguien todavía puede hacer algo.
 *
 * El criterio de qué llega a ser un aviso es siempre el mismo: si al leerlo la
 * persona no puede hacer nada, no va en el escritorio. Por eso «accion» no es
 * opcional y «url» solo admite nulo cuando el aviso se resuelve sin salir de la
 * fila: cada línea se gana su espacio con lo que permite hacer.
 *
 * «id» es estable y viaja al navegador: es lo que vuelve al servidor cuando
 * alguien resuelve el aviso desde la propia fila, y lo que se comprueba contra
 * los avisos vigentes de esa persona antes de escribir nada.
 */
final readonly class Aviso
{
    public function __construct(
        public string $id,
        public string $titulo,
        public string $seccion,
        public string $detalle,
        public bool $urgente,
        public ?string $url,
        public string $accion,
        public int $orden,
    ) {}
}

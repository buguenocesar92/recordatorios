<?php

declare(strict_types=1);

namespace App\Avisos;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * De dónde salen los avisos de un sistema.
 *
 * El escritorio no sabe qué es una noticia, una licencia ni un permiso de
 * circulación: cada sistema escribe sus fuentes para lo suyo y las registra en
 * «AppServiceProvider» con «RegistroDeFuentes::registrar()». Así el escritorio
 * sirve igual el primer día, cuando todavía no hay ninguna, que cuando el
 * sistema tiene seis.
 */
interface FuenteDeAvisos
{
    /** @return iterable<Aviso> */
    public function avisos(): iterable;

    /**
     * Resuelve un aviso desde su propia fila.
     *
     * El widget ya comprobó que el id está entre los avisos vigentes de quien
     * hace clic: acá no hace falta volver a validar la pertenencia, sí la
     * lógica propia del sistema.
     */
    public function resolver(string $id): void;

    /** Si esta persona administra lo que esta fuente mira. */
    public function visiblePara(Authenticatable $usuario): bool;
}

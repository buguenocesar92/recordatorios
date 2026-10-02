<?php

declare(strict_types=1);

namespace App\Privacidad;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Model;
use Muni\Shared\Privacidad\Contratos\BuscaTitulares;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;

/**
 * Cómo encuentra este sistema a la persona que viene al mesón a ejercer sus
 * derechos sobre sus datos.
 *
 * Es el único punto del ciclo ARCOP que ningún paquete puede resolver por su
 * cuenta: cada sistema conoce a las personas por lo suyo —el RUT, un padrón, un
 * número de licencia— y adivinar el esquema del adoptante es justamente lo que
 * hacía que este ciclo viviera en un solo repositorio.
 *
 * Un sistema nacido de este scaffold empieza buscando por documento contra la
 * tabla «personas», que es lo que tiene de fábrica. El que guarde a su gente de
 * otra forma reemplaza esta clase por la suya y no toca nada más.
 */
class BuscadorDePersonas implements BuscaTitulares
{
    /**
     * Cuántos resultados se ofrecen en el selector.
     *
     * Pocos a propósito: quien atiende tiene a la persona delante y su
     * documento en la mano. Una lista larga invita a elegir por el nombre, y
     * dos vecinos con el mismo nombre es lo corriente, no la excepción.
     */
    private const MAXIMO = 15;

    /**
     * @return array<int|string, string>
     */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);

        if ($termino === '') {
            return [];
        }

        return Persona::query()
            // Por documento primero: es lo que la persona trae en la mano y lo
            // único que identifica sin ambigüedad. El scope normaliza puntos y
            // guiones, así que da igual cómo lo escriba quien atiende.
            ->where(fn ($q) => $q
                ->porDocumento($termino)
                ->orWhere('nombres', 'like', '%'.$termino.'%')
                ->orWhere('apellidos', 'like', '%'.$termino.'%'))
            ->orderBy('apellidos')
            ->limit(self::MAXIMO)
            ->get()
            ->mapWithKeys(fn (Persona $persona): array => [
                $persona->getKey() => trim($persona->nombres.' '.$persona->apellidos)
                    .' · '.($persona->nro_documento ?? 'sin documento'),
            ])
            ->all();
    }

    public function encontrar(int|string $clave): (Model&TitularDeDatos)|null
    {
        /** @var (Model&TitularDeDatos)|null $persona */
        $persona = Persona::query()->find($clave);

        return $persona;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Sitio;

/**
 * Registro de las URLs públicas del sistema. Cada módulo agrega las suyas con
 * un cierre (perezoso: se evalúa solo al generar el sitemap) y el controlador
 * las vuelca a XML. Estático a propósito: es configuración de arranque, no
 * estado de petición, y en Octane vive lo que vive el proceso.
 */
final class Sitemap
{
    /** @var list<callable(): iterable<Url>> */
    private static array $fuentes = [];

    /** @param callable(): iterable<Url> $fuente */
    public static function agregar(callable $fuente): void
    {
        self::$fuentes[] = $fuente;
    }

    /** @return list<Url> */
    public static function urls(): array
    {
        $urls = [];
        foreach (self::$fuentes as $fuente) {
            foreach ($fuente() as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Vuelve al registro de arranque: solo la portada. Lo llama
     * `AppServiceProvider` en `boot()` (una vez, al levantar el worker de
     * Octane) y los tests en `beforeEach` (para no arrastrar URLs que
     * registró un módulo en la prueba anterior). No es «olvidar» sin más: es
     * el registro de producción antes de que cualquier módulo agregue nada.
     */
    public static function reiniciar(): void
    {
        self::$fuentes = [];
        self::agregar(fn (): iterable => [new Url('/', changefreq: 'weekly', priority: 1.0)]);
    }
}

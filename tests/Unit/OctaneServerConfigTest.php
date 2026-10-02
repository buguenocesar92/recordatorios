<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

/**
 * El entrypoint de producción arranca Octane con `--server=frankenphp`, pero
 * ese flag vale solo para el proceso que sirve. `octane:reload` -que el
 * despliegue corre después de migrar- no lee el flag: lee config/octane.php,
 * cuyo valor por defecto es `roadrunner`. Sin OCTANE_SERVER en el entorno del
 * contenedor, el reload busca el `rpcPort` de RoadRunner, no lo encuentra y
 * el despliegue muere DESPUÉS de haber migrado la base: no llega al
 * healthcheck, no puede revertir y no deja marca a la que volver.
 *
 * Declararlo en el Compose y no solo en el `.env` es lo que hace que el
 * arreglo viaje con el repositorio, en vez de depender de que alguien se
 * acuerde de editar el `.env` del servidor.
 *
 * Se prueba sobre el archivo de Compose porque el fallo solo aparece en el
 * servidor: ninguna prueba que arranque la aplicación lo habría visto.
 */
class OctaneServerConfigTest extends TestCase
{
    private const RUTA_COMPOSE = __DIR__.'/../../docker-compose.prod.yml';

    /**
     * @return array<int, string>
     */
    private function entornoDe(string $servicio): array
    {
        // El archivo usa las etiquetas propias de Compose (!reset, !override),
        // que Symfony solo acepta con PARSE_CUSTOM_TAGS.
        $compose = Yaml::parseFile(self::RUTA_COMPOSE, Yaml::PARSE_CUSTOM_TAGS);

        $entorno = $compose['services'][$servicio]['environment'] ?? [];

        return $entorno instanceof TaggedValue ? $entorno->getValue() : $entorno;
    }

    public function test_el_servicio_app_declara_frankenphp_como_servidor_de_octane(): void
    {
        $this->assertContains(
            'OCTANE_SERVER=frankenphp',
            $this->entornoDe('app'),
            'Sin OCTANE_SERVER, `octane:reload` falla con «Undefined array key "rpcPort"».'
        );
    }

    public function test_el_servicio_worker_declara_frankenphp_como_servidor_de_octane(): void
    {
        $this->assertContains(
            'OCTANE_SERVER=frankenphp',
            $this->entornoDe('worker'),
            'El worker comparte la configuración cacheada: si difiere del app, la caché depende de quién la generó.'
        );
    }

    public function test_el_valor_por_defecto_de_la_configuracion_sigue_siendo_el_de_laravel(): void
    {
        // Si algún día Laravel cambia este defecto, la variable de entorno deja
        // de ser necesaria y esta prueba avisa para poder simplificar.
        $this->assertStringContainsString(
            "env('OCTANE_SERVER', 'roadrunner')",
            (string) file_get_contents(__DIR__.'/../../config/octane.php')
        );
    }
}

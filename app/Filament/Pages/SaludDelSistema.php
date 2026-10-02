<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Diagnóstico del sistema, en palabras.
 *
 * Cuando algo va mal en un municipio, quien está delante del panel no es quien
 * puede entrar al servidor: es alguien de la oficina que ve que las fotos no se
 * optimizan o que el sitio va lento, y lo único que puede hacer es llamar. Esta
 * pantalla le da algo concreto que decir en esa llamada, en vez de «no funciona».
 *
 * Cada revisión dice si está bien o mal, no solo un número: un número suelto no
 * le sirve a quien no sabe qué número es normal.
 *
 * Lo que NO se muestra acá es tan importante como lo que sí. Una pantalla de
 * diagnóstico es justo donde se filtran las claves, porque alguien pone «para
 * depurar» la configuración entera y ahí va la contraseña de la base. No hay
 * nada de eso, y hay un test que lo comprueba.
 */
class SaludDelSistema extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.salud-del-sistema';

    protected static ?string $title = 'Salud del sistema';

    protected static ?string $navigationLabel = 'Salud del sistema';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 99;

    /**
     * @return array<int, array{nombre: string, estado: string, detalle: string}>
     */
    public function revisiones(): array
    {
        return [
            $this->baseDeDatos(),
            $this->cola(),
            $this->cache(),
            $this->disco(),
            $this->almacenamiento(),
            $this->imagenes(),
            $this->correoDelSegundoFactor(),
        ];
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function baseDeDatos(): array
    {
        try {
            $inicio = microtime(true);
            DB::connection()->getPdo();
            $ms = (int) ((microtime(true) - $inicio) * 1000);

            return [
                'nombre' => 'Base de datos',
                'estado' => $ms < 200 ? 'bien' : 'atención',
                'detalle' => $ms < 200
                    ? "Responde en {$ms} milisegundos."
                    : "Responde lenta: {$ms} milisegundos.",
            ];
        } catch (\Throwable) {
            return [
                'nombre' => 'Base de datos',
                'estado' => 'mal',
                // Sin el mensaje de la excepción: suele traer el usuario y el
                // servidor, y a veces la contraseña.
                'detalle' => 'No responde. El sitio no puede mostrar contenido.',
            ];
        }
    }

    /**
     * La cola de trabajos.
     *
     * Qué se puede contar y qué no depende de POR DÓNDE viajan los trabajos, y
     * eso lo dice la configuración, no la base de datos. La tabla «jobs» existe
     * siempre —la crea la migración que trae Laravel— aunque la cola corra en
     * Redis y ningún trabajo pase nunca por ahí. Preguntarle a la base si la
     * tabla está («try/catch») no distingue los dos casos: con Redis la consulta
     * no falla, devuelve 0, y esta pantalla informaba «0 trabajos en espera ·
     * bien». Es la misma respuesta que daría una cola sana, así que un trabajador
     * caído hace semanas se veía exactamente igual que uno al día, que es
     * justamente lo que esta pantalla existe para evitar.
     *
     * Los trabajos FALLADOS sí se cuentan siempre: se guardan en la base
     * («queue.failed.driver») pase lo que pase con la conexión de la cola.
     *
     * @return array{nombre: string, estado: string, detalle: string}
     */
    private function cola(): array
    {
        $conexion = (string) config('queue.default');
        $motor = (string) config("queue.connections.{$conexion}.driver", $conexion);

        $fallados = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();

        if ($fallados > 0) {
            return [
                'nombre' => 'Cola de trabajos',
                'estado' => 'mal',
                'detalle' => "{$fallados} trabajos fallaron. Las imágenes nuevas pueden no estar optimizándose.",
            ];
        }

        if ($motor === 'sync') {
            return [
                'nombre' => 'Cola de trabajos',
                'estado' => 'bien',
                'detalle' => 'No hay cola: los trabajos se ejecutan al instante.',
            ];
        }

        if ($motor !== 'database') {
            return [
                'nombre' => 'Cola de trabajos',
                'estado' => 'bien',
                'detalle' => "La cola corre en {$motor}; cuántos esperan se mira desde el servidor.",
            ];
        }

        $pendientes = DB::table((string) config("queue.connections.{$conexion}.table", 'jobs'))->count();

        return [
            'nombre' => 'Cola de trabajos',
            'estado' => $pendientes > 500 ? 'atención' : 'bien',
            'detalle' => $pendientes > 500
                ? "{$pendientes} trabajos esperando: la cola va atrasada."
                : "{$pendientes} trabajos en espera.",
        ];
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function cache(): array
    {
        try {
            Cache::put('salud:prueba', '1', 10);
            $anda = Cache::get('salud:prueba') === '1';
            Cache::forget('salud:prueba');

            return [
                'nombre' => 'Memoria de páginas',
                'estado' => $anda ? 'bien' : 'mal',
                'detalle' => $anda
                    ? 'Guarda y devuelve lo que se le pide.'
                    : 'No guarda: el sitio rehace cada página en cada visita y va lento.',
            ];
        } catch (\Throwable) {
            return [
                'nombre' => 'Memoria de páginas',
                'estado' => 'mal',
                'detalle' => 'No responde. El sitio funciona, pero más lento.',
            ];
        }
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function disco(): array
    {
        $libre = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if ($libre === false || $total === false || $total <= 0) {
            return [
                'nombre' => 'Espacio en disco',
                'estado' => 'atención',
                'detalle' => 'No se pudo medir desde acá.',
            ];
        }

        $porcentaje = (int) round(($libre / $total) * 100);
        $gigas = round($libre / 1024 ** 3, 1);

        // Un disco lleno rompe todo a la vez y sin aviso: no se pueden subir
        // archivos, no se guardan los registros y no se escriben los respaldos.
        return [
            'nombre' => 'Espacio en disco',
            'estado' => match (true) {
                $porcentaje < 5 => 'mal',
                $porcentaje < 15 => 'atención',
                default => 'bien',
            },
            'detalle' => "Quedan {$gigas} GB libres ({$porcentaje} %).",
        ];
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function almacenamiento(): array
    {
        try {
            $nombre = 'salud/prueba-'.now()->timestamp.'.txt';
            Storage::disk('public')->put($nombre, 'ok');
            $anda = Storage::disk('public')->get($nombre) === 'ok';
            Storage::disk('public')->delete($nombre);

            return [
                'nombre' => 'Carpeta de archivos',
                'estado' => $anda ? 'bien' : 'mal',
                'detalle' => $anda
                    ? 'Se pueden subir y leer archivos.'
                    : 'No se puede escribir: las cargas van a fallar.',
            ];
        } catch (\Throwable) {
            return [
                'nombre' => 'Carpeta de archivos',
                'estado' => 'mal',
                'detalle' => 'No se puede escribir: las cargas van a fallar.',
            ];
        }
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function imagenes(): array
    {
        $puede = function_exists('imageavif') && function_exists('imagewebp');

        return [
            'nombre' => 'Optimización de imágenes',
            'estado' => $puede ? 'bien' : 'atención',
            'detalle' => $puede
                ? 'El servidor puede generar las versiones livianas.'
                : 'Falta soporte AVIF o WebP: las fotos se sirven en su tamaño original.',
        ];
    }

    /**
     * Si el código del segundo factor puede estar quedando escrito en disco.
     *
     * El código se manda por correo. Cuando el correo está configurado para
     * «escribir en el registro» —que es lo normal en desarrollo— el mensaje
     * entero, con el código dentro, se guarda en un archivo. Quien pueda leer
     * ese archivo entra al panel sin necesitar el teléfono de nadie.
     *
     * En desarrollo da igual. En producción es una puerta abierta, y es de las
     * cosas que se quedan puestas al copiar una configuración de un sitio a
     * otro.
     */
    /** @return array{nombre: string, estado: string, detalle: string} */
    private function correoDelSegundoFactor(): array
    {
        if (! config('mfa.enabled')) {
            return [
                'nombre' => 'Código del segundo factor',
                'estado' => 'atención',
                'detalle' => 'El segundo factor está apagado: al panel se entra solo con la contraseña.',
            ];
        }

        $canal = (string) config('mail.default');

        if (in_array($canal, ['log', 'array'], true)) {
            return [
                'nombre' => 'Código del segundo factor',
                'estado' => app()->isProduction() ? 'mal' : 'atención',
                'detalle' => app()->isProduction()
                    ? 'El correo se está escribiendo en un archivo: cada código de acceso queda guardado ahí. Hay que configurar un servidor de correo.'
                    : 'El correo se escribe en un archivo. Está bien para desarrollar; en producción hay que cambiarlo.',
            ];
        }

        return [
            'nombre' => 'Código del segundo factor',
            'estado' => 'bien',
            'detalle' => 'El correo sale por «'.$canal.'». Conviene comprobar que llega de verdad con un envío de prueba.',
        ];
    }
}

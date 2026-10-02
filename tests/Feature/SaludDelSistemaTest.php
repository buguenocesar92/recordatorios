<?php

use App\Filament\Pages\SaludDelSistema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

/**
 * La pantalla de diagnóstico.
 *
 * Cuando algo va mal en un municipio, quien está delante del panel no es quien
 * puede entrar al servidor: es alguien de la oficina que ve que las fotos no se
 * optimizan o que el sitio va lento, y lo único que puede hacer es llamar. Esta
 * pantalla le da algo concreto que decir en esa llamada.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', false);

    Role::findOrCreate('super_admin', 'web');
    $this->jefe = User::factory()->create();
    $this->jefe->assignRole('super_admin');
    $this->actingAs($this->jefe);
});

it('la pantalla de salud se abre', function () {
    livewire(SaludDelSistema::class)->assertSuccessful();
});

it('dice si la cola de trabajos está corriendo', function () {
    // Es lo primero que falla y lo último que se nota: si el trabajador se cayó,
    // las imágenes dejan de optimizarse y nadie se entera en semanas.
    $revisiones = collect(invade(new SaludDelSistema)->revisiones());

    expect($revisiones->pluck('nombre'))->toContain('Cola de trabajos');
});

it('con la cola en Redis no inventa un recuento leyendo la tabla «jobs»', function () {
    // El error que esto vigila: contar «jobs» y creer que un cero significa que
    // la cola está al día. La tabla existe SIEMPRE —la trae la migración de
    // Laravel— aunque los trabajos viajen por Redis y nunca pasen por ahí. Con
    // «QUEUE_CONNECTION=redis», que es el valor por defecto de este scaffold, la
    // consulta no falla: devuelve 0. Y «0 trabajos en espera · bien» es la peor
    // respuesta posible, porque es la misma que daría una cola sana mientras el
    // trabajador lleva semanas caído y las imágenes sin optimizar.
    config()->set('queue.default', 'redis');

    $cola = collect(invade(new SaludDelSistema)->revisiones())
        ->firstWhere('nombre', 'Cola de trabajos');

    // Ojo con escribirlo como toContain('…', 'mensaje'): toContain admite varios
    // valores y los busca TODOS, así que el mensaje se convertiría en otra cosa
    // que buscar y la comprobación se apagaría sin decir nada.
    expect(str_contains($cola['detalle'], 'trabajos en espera'))
        ->toBeFalse(
            'la revisión contó la tabla «jobs» con la cola en Redis: ese recuento '
            .'siempre da 0 y disfraza de sana una cola con el trabajador caído'
        );
});

it('con la cola en la base de datos sí cuenta los trabajos pendientes', function () {
    // Contraprueba: sin ella, una revisión que dijera «no se puede contar» pase
    // lo que pase dejaría verde el caso de arriba y perdería el diagnóstico real
    // en los sistemas que sí encolan contra la base.
    config()->set('queue.default', 'database');

    $cola = collect(invade(new SaludDelSistema)->revisiones())
        ->firstWhere('nombre', 'Cola de trabajos');

    expect(str_contains($cola['detalle'], 'trabajos en espera'))
        ->toBeTrue('con la cola en la base de datos el recuento sí es real y hay que darlo');
});

it('dice cuánto espacio queda en disco', function () {
    // Un disco lleno rompe todo a la vez y sin aviso: no se pueden subir
    // archivos, no se guardan los registros y no se escriben los respaldos.
    $revisiones = collect(invade(new SaludDelSistema)->revisiones());

    expect($revisiones->pluck('nombre'))->toContain('Espacio en disco');
});

it('cada revisión dice si está bien o mal, no solo un número', function () {
    // Un número suelto no le sirve a quien no sabe qué número es normal.
    foreach (invade(new SaludDelSistema)->revisiones() as $revision) {
        expect($revision)->toHaveKeys(['nombre', 'estado', 'detalle'])
            ->and($revision['estado'])->toBeIn(['bien', 'atención', 'mal']);
    }
});

it('no expone contraseñas ni claves en la pantalla', function () {
    // Una pantalla de diagnóstico es justo donde se filtran: se pone «para
    // depurar» la configuración entera y ahí va la clave de la base.
    $texto = json_encode(invade(new SaludDelSistema)->revisiones());

    expect($texto)
        ->not->toContain(config('app.key'))
        ->not->toContain((string) config('database.connections.mysql.password'));
});

it('solo la ve quien tiene permiso', function () {
    // Dice qué versiones corren y cuánto disco queda: no es para cualquiera con
    // acceso al panel.
    $cualquiera = User::factory()->create();
    $this->actingAs($cualquiera);

    expect(SaludDelSistema::canAccess())->toBeFalse();
});

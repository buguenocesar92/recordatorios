<?php

use App\Filament\Pages\Respaldos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Livewire\livewire;

/**
 * La pantalla de respaldos.
 *
 * Un respaldo es una copia completa de la base: quien lo descarga se lleva todo,
 * incluidos los datos personales de cualquiera que esté ahí dentro. Por eso la
 * pantalla exige su propio permiso y la descarga comprueba dos veces qué se
 * está pidiendo.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', false);

    Role::findOrCreate('super_admin', 'web');
    $this->jefe = User::factory()->create();
    $this->jefe->assignRole('super_admin');
});

it('la pantalla de respaldos se abre para quien tiene permiso', function () {
    $this->actingAs($this->jefe);

    livewire(Respaldos::class)->assertSuccessful();
});

it('quien no tiene permiso no entra', function () {
    // Sin esto, cualquiera que entre al panel se puede llevar la base entera.
    $cualquiera = User::factory()->create();

    $this->actingAs($cualquiera)
        ->get(Respaldos::getUrl())
        ->assertForbidden();
});

it('no se puede pedir un archivo de fuera de la carpeta de respaldos', function () {
    // El nombre viaja desde el navegador. Sin comprobarlo, «../../.env» es un
    // archivo perfectamente descargable y ahí están todas las credenciales.
    $this->actingAs($this->jefe);

    (new Respaldos)->descargar('../../.env');
})->throws(NotFoundHttpException::class);

it('no se puede pedir un respaldo que no está en la lista', function () {
    // Aunque el nombre parezca correcto: se comprueba contra lo que existe de
    // verdad, no contra su forma.
    $this->actingAs($this->jefe);

    (new Respaldos)->descargar('2020-01-01-00-00-00.zip');
})->throws(NotFoundHttpException::class);

it('solo se pueden pedir archivos zip', function () {
    $this->actingAs($this->jefe);

    (new Respaldos)->descargar('base-de-datos.sql');
})->throws(NotFoundHttpException::class);

it('descargar un respaldo deja constancia de quién se lo llevó', function () {
    // Un respaldo es la base entera, con todos los datos personales que
    // contenga. El permiso dice quién PUEDE bajarlo; sin registro, tras una
    // filtración no hay forma de saber quién lo bajó ni cuándo, que es lo
    // primero que se pregunta (Ley 21.719, trazabilidad de accesos).
    $disco = config('backup.backup.destination.disks')[0] ?? 'local';
    $carpeta = config('backup.backup.name', config('app.name'));

    Storage::fake($disco);
    Storage::disk($disco)->put($carpeta.'/2026-08-30-03-00-00.zip', 'contenido de prueba');

    $this->actingAs($this->jefe);

    (new Respaldos)->descargar('2026-08-30-03-00-00.zip');

    $registro = Activity::query()->where('description', 'descarga de respaldo')->latest('id')->first();

    expect($registro)->not->toBeNull('la descarga no dejó rastro');
    expect($registro?->causer_id)->toBe($this->jefe->getKey());
    expect($registro?->properties->get('archivo'))->toBe('2026-08-30-03-00-00.zip');
});

it('un respaldo que se rechaza no queda anotado como descargado', function () {
    // Si se anotara antes de comprobar el nombre, el registro se llenaría de
    // descargas que nunca ocurrieron y dejaría de servir para lo que existe.
    $this->actingAs($this->jefe);

    try {
        (new Respaldos)->descargar('../../.env');
    } catch (NotFoundHttpException) {
        // Es lo esperado; lo que se comprueba es lo que quedó anotado.
    }

    expect(Activity::query()->where('description', 'descarga de respaldo')->exists())->toBeFalse();
});

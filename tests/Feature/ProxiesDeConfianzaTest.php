<?php

declare(strict_types=1);

use App\Support\CredencialesDePlantilla;
use App\Support\ProxiesDeConfianza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Proxies de confianza: por omisión, NINGUNO (falla cerrado).
 *
 * Se confiaba en todos los rangos privados (10/8, 172.16/12, 192.168/16). Con el
 * userland-proxy de Docker, lo que entra por el puerto publicado llega desde la
 * puerta de enlace del bridge (172.x.0.1), que es RFC1918: cualquiera de
 * Internet mandaba «X-Forwarded-For: <IP de la VPN>» y el panel le creía.
 */
it('por omisión no se confía en ningún proxy', function () {
    expect(config('proxies.confiables'))->toBe([])
        ->and((string) file_get_contents(config_path('proxies.php')))->toContain("env('TRUSTED_PROXIES', '')");
});

it('con la configuración por omisión, una X-Forwarded-For de la VPN desde el bridge de Docker no abre /admin', function () {
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.99'])
        ->withHeaders(['X-Forwarded-For' => '10.8.0.5'])
        ->get('/admin/login')
        ->assertNotFound();
});

it('un Host terminado en .on-forge.com no hace que Laravel confíe en todos los proxies', function () {
    Route::get('/__prueba-de-forge', fn (): string => (string) request()->ip());

    $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-For' => '10.8.0.5'])
        ->get('http://x.on-forge.com/__prueba-de-forge');

    expect($respuesta->getContent())->toBe('203.0.113.9');
});

it('lee la lista exacta de TRUSTED_PROXIES sin entradas vacías', function () {
    expect(ProxiesDeConfianza::desde(''))->toBe([])
        ->and(ProxiesDeConfianza::desde(' 172.31.251.1 , ,10.0.0.2/32 '))->toBe(['172.31.251.1', '10.0.0.2/32']);
});

it('de un proxy de confianza solo se aceptan X-Forwarded-For y X-Forwarded-Proto', function () {
    ProxiesDeConfianza::aplicar(['127.0.0.1']);
    Route::get('/__prueba-de-cabeceras', fn (Request $r): string => $r->ip().'|'.$r->getHost().'|'.$r->getScheme());

    $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders([
            'X-Forwarded-For' => '198.51.100.7',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'evil.example',
        ])
        ->get('http://sistema.ejemplo.cl/__prueba-de-cabeceras');

    expect($respuesta->getContent())->toBe('198.51.100.7|sistema.ejemplo.cl|https');
});

/** Producción con todo lo demás en orden, para probar solo los proxies. */
function produccionEnOrdenSalvoProxies(): void
{
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', 'otra-de-verdad');
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);
    config()->set('waitlist.hash_salt', str_repeat('s', 32));
    config()->set('services.turnstile.sitekey', '0x4AAAAAAAreal');
    config()->set('services.turnstile.secret', '0x4AAAAAAAsecretoreal');
    config()->set('services.turnstile.hostname', 'recordatorios.example');
}

it('en producción no arranca sin TRUSTED_PROXIES explícito', function () {
    produccionEnOrdenSalvoProxies();
    config()->set('proxies.confiables', []);

    CredencialesDePlantilla::comprobar();
})->throws(RuntimeException::class, 'TRUSTED_PROXIES');

it('en producción no arranca confiando en todo el mundo, en rangos anchos ni con basura', function (string $valor) {
    produccionEnOrdenSalvoProxies();
    config()->set('proxies.confiables', ProxiesDeConfianza::desde($valor));

    CredencialesDePlantilla::comprobar();
})->with(['*', '**', '0.0.0.0/0', '::/0', 'REMOTE_ADDR', '10.0.0.0/8', '172.16.0.0/12', '2001:db8::/32', 'no-es-una-ip', '172.31.251.1,0.0.0.0/0'])
    ->throws(RuntimeException::class, 'TRUSTED_PROXIES');

it('en producción acepta IPs exactas o subredes acotadas', function () {
    expect(ProxiesDeConfianza::acotadas(['172.31.251.1', '172.31.251.0/24', '10.1.0.0/16', '::1', '2001:db8::/64']))->toBeTrue()
        ->and(ProxiesDeConfianza::acotadas(['10.0.0.0/15']))->toBeFalse();
});

it('la lista se aplica al arranque con ProxiesDeConfianza', function () {
    expect((string) file_get_contents(app_path('Providers/AppServiceProvider.php')))
        ->toContain("ProxiesDeConfianza::aplicar(config('proxies.confiables'))");
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

/**
 * La portada no consulta nada y no depende de quién la mira: puede cachearse
 * en el navegador y en cualquier proxy. El ETag permite que una revisita
 * cueste un 304 sin cuerpo.
 */
uses(RefreshDatabase::class);

it('la portada se puede cachear y lleva ETag', function () {
    $respuesta = $this->get('/');

    $control = (string) $respuesta->headers->get('Cache-Control');

    expect($control)->toContain('public')
        ->and($control)->toContain('max-age=300')
        ->and($control)->toContain('s-maxage=300')
        ->and($respuesta->headers->get('ETag'))->not->toBeNull();
});

it('el ETag es estable entre dos peticiones aunque el nonce de CSP cambie', function () {
    // Si esto no se cumpliera, el caso siguiente (304) pasaría por casualidad
    // en vez de por diseño: el ETag tiene que ignorar lo único que varía entre
    // dos visitas idénticas, que es el nonce de CSP de la petición.
    $r1 = $this->get('/');
    $r2 = $this->get('/');

    preg_match('/nonce="([^"]+)"/', (string) $r1->getContent(), $nonce1);
    preg_match('/nonce="([^"]+)"/', (string) $r2->getContent(), $nonce2);

    expect($nonce1[1] ?? null)->not->toBeNull()
        ->and($nonce1[1])->not->toBe($nonce2[1] ?? null)
        ->and($r1->headers->get('ETag'))->toBe($r2->headers->get('ETag'));
});

it('una revisita con el mismo ETag recibe 304 sin cuerpo', function () {
    $etag = (string) $this->get('/')->headers->get('ETag');

    $this->get('/', ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertNoContent(304);
});

it('el ETag cambia si cambia el HTML aunque la configuración no cambie', function () {
    // Un ETag que solo mirara config('sitio') no se enteraría de un cambio de
    // plantilla, de pie o de asset compilado en un deploy: un navegador que ya
    // tenía la portada seguiría recibiendo 304 con el HTML viejo para siempre.
    $etagOriginal = (string) $this->get('/')->headers->get('ETag');

    View::composer('sitio.portada', function ($vista): void {
        $vista->with('portada', ['claim' => 'Otro claim', 'bajada' => 'Otra bajada']);
    });

    $etagNuevo = (string) $this->get('/')->headers->get('ETag');

    expect($etagNuevo)->not->toBe($etagOriginal);
});

it('el panel sigue sin ser cacheable', function () {
    // Lo público se cachea; lo autenticado, nunca. El grupo «sitio» no puede
    // haber contagiado al panel.
    expect((string) $this->get('/admin/login')->headers->get('Cache-Control'))->not->toContain('public');
});

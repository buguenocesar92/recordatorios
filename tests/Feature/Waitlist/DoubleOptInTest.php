<?php

declare(strict_types=1);

use App\Mail\WaitlistConfirmationMail;
use App\Models\MetricDaily;
use App\Models\WaitlistSignup;
use App\Waitlist\ConfirmationToken;
use App\Waitlist\EnlacesDeLaLista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
    config()->set('app.url', 'https://recordatorios.example');
});

function mismoOrigen(): array
{
    return ['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'];
}

/** Inscribe y devuelve [signup, token en claro capturado del correo]. */
function inscribir(string $email = 'ana@example.com'): array
{
    test()->withHeaders(mismoOrigen())->post('/lista-de-espera', [
        'email' => $email, 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ])->assertOk();

    $token = null;
    Mail::assertSent(WaitlistConfirmationMail::class, function (WaitlistConfirmationMail $correo) use ($email, &$token): bool {
        $token = $correo->token;

        return $correo->hasTo($email);
    });

    return [WaitlistSignup::where('email_hash', WaitlistSignup::hashEmail($email))->sole(), (string) $token];
}

it('encola el correo de confirmación con el enlace y sin hash ni IP', function () {
    [$signup, $token] = inscribir();

    $correo = new WaitlistConfirmationMail($signup, $token);
    $html = $correo->render();

    expect($html)->toContain('https://recordatorios.example/lista-de-espera/confirmar/'.$token)
        ->not->toContain($signup->email_hash)
        ->not->toContain('127.0.0.1')
        ->not->toContain($signup->ip_hash)
        ->toContain('/lista-de-espera/baja/')
        ->and($signup->confirm_token_hash)->toBe(ConfirmationToken::hash($token))
        ->and(strlen($token))->toBe(43);

    $cabeceras = $correo->headers()->text;
    expect($cabeceras['List-Unsubscribe'])->toContain('/lista-de-espera/baja/')
        ->and($cabeceras['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
});

it('el enlace muestra un botón; confirmar es un POST (un antivirus que abre el enlace no confirma)', function () {
    [$signup, $token] = inscribir();

    $this->get('/lista-de-espera/confirmar/'.$token)->assertOk()->assertSee('Confirmar inscripción');
    expect($signup->fresh()->confirmed_at)->toBeNull();

    $this->withHeaders(mismoOrigen())->post('/lista-de-espera/confirmar/'.$token)
        ->assertOk()
        ->assertSee('¿has pagado multa o intereses por olvido en el último año?');

    expect($signup->fresh()->confirmed_at)->not->toBeNull();
});

it('el mismo enlace por segunda vez responde 410', function () {
    [, $token] = inscribir();
    $this->withHeaders(mismoOrigen())->post('/lista-de-espera/confirmar/'.$token)->assertOk();

    $this->get('/lista-de-espera/confirmar/'.$token)->assertStatus(410);
    $this->withHeaders(mismoOrigen())->post('/lista-de-espera/confirmar/'.$token)->assertStatus(410);
});

it('un token vencido (73 h) responde 410 y no confirma', function () {
    [$signup, $token] = inscribir();

    $this->travel(73)->hours();

    $this->withHeaders(mismoOrigen())->post('/lista-de-espera/confirmar/'.$token)->assertStatus(410);
    expect($signup->fresh()->confirmed_at)->toBeNull();
});

it('un token inventado responde 404', function () {
    inscribir();

    $this->get('/lista-de-espera/confirmar/'.ConfirmationToken::generate())->assertNotFound();
    $this->get('/lista-de-espera/confirmar/corto')->assertNotFound();
});

it('confirmar desde otro sitio se rechaza', function () {
    [$signup, $token] = inscribir();

    $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->post('/lista-de-espera/confirmar/'.$token)->assertForbidden();
    expect($signup->fresh()->confirmed_at)->toBeNull();
});

it('la encuesta guarda la respuesta una sola vez, con enlace firmado', function () {
    [$signup, $token] = inscribir();
    $this->withHeaders(mismoOrigen())->post('/lista-de-espera/confirmar/'.$token);

    $url = EnlacesDeLaLista::encuestaRelativa($signup->fresh());

    $this->withHeaders(mismoOrigen())->post($url, ['paid_penalty_last_year' => '1'])->assertOk();
    expect($signup->fresh()->paid_penalty_last_year)->toBeTrue();

    $this->withHeaders(mismoOrigen())->post($url, ['paid_penalty_last_year' => '0'])->assertStatus(410);
    expect($signup->fresh()->paid_penalty_last_year)->toBeTrue();

    // Sin firma no.
    $this->withHeaders(mismoOrigen())->post('/lista-de-espera/encuesta/'.$signup->id, ['paid_penalty_last_year' => '0'])->assertForbidden();
});

it('la baja firmada muestra un botón y el POST borra la fila y la cuenta en métricas', function () {
    [$signup] = inscribir();
    $url = EnlacesDeLaLista::bajaRelativa($signup->id);

    $this->get($url)->assertOk()->assertSee('Darme de baja');
    expect(WaitlistSignup::count())->toBe(1);

    $this->post($url)->assertOk();

    expect(WaitlistSignup::count())->toBe(0)
        ->and(MetricDaily::where('metric', 'waitlist_unsubscribed')->value('value'))->toBe(1);
});

it('la baja de un clic del cliente de correo (RFC 8058) funciona sin cabeceras de navegador', function () {
    [$signup] = inscribir();

    // El cliente de correo hace POST a la URL de List-Unsubscribe.
    $absoluta = EnlacesDeLaLista::baja($signup->id);
    $relativa = substr($absoluta, strlen('https://recordatorios.example'));

    $this->post($relativa, ['List-Unsubscribe' => 'One-Click'])->assertOk();
    expect(WaitlistSignup::count())->toBe(0);
});

it('una baja con firma mala responde 403 y no borra nada', function () {
    [$signup] = inscribir();

    $this->get('/lista-de-espera/baja/'.$signup->id.'?signature=falsa')->assertForbidden();
    $this->post('/lista-de-espera/baja/'.$signup->id.'?signature=falsa')->assertForbidden();
    $this->post('/lista-de-espera/baja/'.$signup->id)->assertForbidden();

    expect(WaitlistSignup::count())->toBe(1);
});

it('la baja responde igual exista o no la inscripción', function () {
    $this->post(EnlacesDeLaLista::bajaRelativa((string) Str::ulid()))->assertOk()->assertSee('Si estabas inscrito');
    expect(MetricDaily::count())->toBe(0);
});

it('las páginas con token no se cachean ni abren sesión', function () {
    [, $token] = inscribir();

    $respuesta = $this->get('/lista-de-espera/confirmar/'.$token);

    expect((string) $respuesta->headers->get('Cache-Control'))->toContain('no-store')
        ->and($respuesta->headers->get('set-cookie'))->toBeNull();
});

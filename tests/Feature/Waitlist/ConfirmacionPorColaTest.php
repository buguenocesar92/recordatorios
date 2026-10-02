<?php

declare(strict_types=1);

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistSignup;
use App\Waitlist\ConfirmationToken;
use App\Waitlist\EnviarConfirmacionDeLista;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
    config()->set('app.url', 'https://recordatorios.example');
});

function altaPorFormulario(string $email = 'ana@example.com'): void
{
    test()->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', [
            'email' => $email, 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ])->assertOk();
}

/**
 * Un transporte de correo que siempre falla, como un SMTP caído que además
 * nombra al destinatario en su error, y que anota el cuerpo que alcanzó a ver
 * (de ahí sale el token que el trabajo generó, para poder buscarlo después).
 *
 * @param  array{cuerpo: string}  $visto
 */
function transporteQueExplota(?array &$visto): void
{
    $visto = ['cuerpo' => ''];

    Mail::extend('explota', function () use (&$visto) {
        return new class($visto) extends AbstractTransport
        {
            /** @param array{cuerpo: string} $visto */
            public function __construct(private array &$visto)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                $this->visto['cuerpo'] = quoted_printable_decode($message->toString());

                throw new TransportException('550 buzón inexistente <ana@example.com>');
            }

            public function __toString(): string
            {
                return 'explota://';
            }
        };
    });

    config()->set('mail.mailers.explota', ['transport' => 'explota']);
    config()->set('mail.default', 'explota');
}

it('el correo de confirmación sale por un trabajo que solo lleva el id de la inscripción', function () {
    expect(is_subclass_of(EnviarConfirmacionDeLista::class, ShouldQueue::class))->toBeTrue()
        // Y el correo en sí ya NO es encolable: encolarlo serializaría el
        // destinatario descifrado y el token.
        ->and(is_subclass_of(WaitlistConfirmationMail::class, ShouldQueue::class))->toBeFalse();

    expect((new ReflectionClass(EnviarConfirmacionDeLista::class))->getDefaultProperties()['deleteWhenMissingModels'] ?? false)
        ->toBeTrue();
});

it('si el envío falla, ni el correo ni el token quedan en jobs ni en failed_jobs', function () {
    config()->set('queue.default', 'database');
    transporteQueExplota($visto);

    altaPorFormulario();

    // Mientras espera en la cola: solo el id.
    $enEspera = (string) DB::table('jobs')->value('payload');
    expect(DB::table('jobs')->count())->toBe(1)
        ->and($enEspera)->not->toContain('ana@example.com')
        ->and($enEspera)->not->toContain('ana%40example.com');

    // Tres intentos (el trabajo reintenta con espera): se viaja en el tiempo
    // para que cada reintento ya esté disponible.
    foreach ([0, 2, 6] as $minutos) {
        $this->travel($minutos)->minutes();
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();
    }

    expect(DB::table('failed_jobs')->count())->toBe(1);

    $token = [];
    preg_match('#/lista-de-espera/confirmar/([A-Za-z0-9_-]{43})#', $visto['cuerpo'], $token);
    expect($token)->not->toBeEmpty('el transporte no alcanzó a ver el enlace de confirmación');

    $fallido = DB::table('failed_jobs')->first();
    foreach ([$fallido->payload, $fallido->exception] as $texto) {
        expect($texto)->not->toContain('ana@example.com')
            ->and($texto)->not->toContain('ana%40example.com')
            ->and($texto)->not->toContain($token[1]);
    }
});

it('el trabajo genera el token al enviar: en la base queda su hash y vence a las 72 h', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());

    altaPorFormulario();

    $token = null;
    Mail::assertSent(WaitlistConfirmationMail::class, function (WaitlistConfirmationMail $correo) use (&$token): bool {
        $token = $correo->token;

        return $correo->hasTo('ana@example.com');
    });

    $signup = WaitlistSignup::query()->sole();

    expect(strlen((string) $token))->toBe(43)
        ->and($signup->confirm_token_hash)->toBe(ConfirmationToken::hash((string) $token))
        ->and($signup->confirm_expires_at?->equalTo(now()->addHours(72)))->toBeTrue();
});

it('si la persona ya confirmó o se dio de baja antes de que corra el trabajo, no se manda nada', function () {
    Mail::fake();
    $signup = WaitlistSignup::create([
        'email' => 'ana@example.com', 'interests' => [], 'consent_version' => 'v',
        'consented_at' => now(), 'ip_hash' => str_repeat('a', 64),
        'confirmed_at' => now(), 'confirm_expires_at' => now()->addHour(),
    ]);

    (new EnviarConfirmacionDeLista($signup))->handle();

    Mail::assertNothingSent();
});

it('si la inscripción se borró (baja) antes de que corra el trabajo, se descarta sin fallar', function () {
    config()->set('queue.default', 'database');
    Mail::fake();

    altaPorFormulario();
    WaitlistSignup::query()->delete();

    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
    Mail::assertNothingSent();
});

it('maintenance:daily borra de failed_jobs los fallos de este trabajo de más de 24 h, y solo esos', function () {
    $fila = fn (string $clase, string $falloHace): array => [
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => $clase]),
        'exception' => 'x',
        'failed_at' => now()->sub($falloHace),
    ];

    DB::table('failed_jobs')->insert([
        $fila(EnviarConfirmacionDeLista::class, '25 hours'),
        $fila(EnviarConfirmacionDeLista::class, '2 hours'),
        $fila('App\\Jobs\\OtroTrabajo', '25 hours'),
    ]);

    $this->artisan('maintenance:daily')->assertSuccessful();

    $quedan = DB::table('failed_jobs')->pluck('payload')->map(fn (string $p): string => (string) json_decode($p)->displayName)->sort()->values()->all();

    expect($quedan)->toBe(['App\\Jobs\\OtroTrabajo', EnviarConfirmacionDeLista::class]);
});

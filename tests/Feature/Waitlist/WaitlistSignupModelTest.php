<?php

declare(strict_types=1);

use App\Enums\ObligationTypeCode;
use App\Models\Comuna;
use App\Models\MetricDaily;
use App\Models\WaitlistSignup;
use Database\Seeders\ComunasSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('waitlist.hash_salt', 'sal-de-prueba');
});

function inscripcion(array $atributos = []): WaitlistSignup
{
    return WaitlistSignup::create(array_merge([
        'email' => 'ana@example.com',
        'interests' => ['soap'],
        'consent_version' => '2026-10-01',
        'consented_at' => now(),
        'ip_hash' => str_repeat('a', 64),
        'confirm_token_hash' => hash('sha256', 'token-'.uniqid()),
        'confirm_expires_at' => now()->addHours(72),
    ], $atributos));
}

it('calcula email_hash con HMAC-SHA256, minúsculas y sin espacios', function () {
    $signup = inscripcion(['email' => '  Ana@Example.COM ']);

    expect($signup->email_hash)->toBe(hash_hmac('sha256', 'ana@example.com', 'sal-de-prueba'))
        ->and(WaitlistSignup::hashEmail('ANA@example.com'))->toBe($signup->email_hash);
});

it('un correo se inscribe una sola vez, sin importar mayúsculas', function () {
    inscripcion(['email' => 'ana@example.com']);

    expect(fn () => inscripcion(['email' => 'ANA@example.com']))->toThrow(QueryException::class);
});

it('el correo queda cifrado en la base y se lee normal desde el modelo', function () {
    $signup = inscripcion(['email' => 'ana@example.com']);

    $crudo = DB::table('waitlist_signups')->where('id', $signup->id)->value('email');

    expect($crudo)->not->toContain('ana@example.com')
        ->and($signup->fresh()->email)->toBe('ana@example.com');
});

it('el token de confirmación nunca se guarda en claro', function () {
    $token = 'token-en-claro-de-32-bytes';
    $signup = inscripcion(['confirm_token_hash' => hash('sha256', $token)]);

    expect($signup->confirm_token_hash)->not->toBe($token)
        ->and(DB::table('waitlist_signups')->where('confirm_token_hash', $token)->exists())->toBeFalse();
});

it('expiredUnconfirmed devuelve solo las sin confirmar y vencidas', function () {
    $vencida = inscripcion(['email' => 'a@example.com', 'confirm_expires_at' => now()->subMinute()]);
    inscripcion(['email' => 'b@example.com', 'confirm_expires_at' => now()->addHour()]);
    inscripcion(['email' => 'c@example.com', 'confirm_expires_at' => now()->subDay(), 'confirmed_at' => now()->subDays(2)]);

    expect(WaitlistSignup::expiredUnconfirmed()->pluck('id')->all())->toBe([$vencida->id]);
});

it('markConfirmed fija la fecha de confirmación', function () {
    $signup = inscripcion();

    $signup->markConfirmed();

    expect($signup->fresh()->confirmed_at)->not->toBeNull()
        ->and(WaitlistSignup::expiredUnconfirmed()->count())->toBe(0);
});

it('interests solo acepta códigos de tipos de obligación', function () {
    $valido = inscripcion(['interests' => [ObligationTypeCode::Soap->value, 'contribuciones']]);
    expect($valido->interests)->toBe(['soap', 'contribuciones']);

    expect(fn () => inscripcion(['email' => 'x@example.com', 'interests' => ['soap', 'inventado']]))
        ->toThrow(InvalidArgumentException::class);
});

it('el catálogo de comunas se siembra desde el CSV público: 346 filas', function () {
    $this->seed(ComunasSeeder::class);

    expect(Comuna::count())->toBe(346)
        ->and(Comuna::where('code', '06106')->value('name'))->toBe('Graneros')
        ->and(Comuna::where('code', '12202')->value('region_code'))->toBe('12');

    // Idempotente: sembrar dos veces no duplica.
    $this->seed(ComunasSeeder::class);
    expect(Comuna::count())->toBe(346);
});

it('la configuración de la lista de espera trae los plazos del spec', function () {
    expect(config('waitlist.confirm_ttl_hours'))->toBe(72)
        ->and(config('waitlist.resend_cooldown_hours'))->toBe(24);
});

it('las métricas diarias suman sin duplicar filas', function () {
    MetricDaily::incrementar('waitlist_unsubscribed');
    MetricDaily::incrementar('waitlist_unsubscribed', 2);

    expect(MetricDaily::where('metric', 'waitlist_unsubscribed')->sole()->value)->toBe(3);
});

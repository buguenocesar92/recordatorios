<?php

declare(strict_types=1);

use App\Models\WaitlistSignup;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config()->set('waitlist.hash_salt', 'sal-de-prueba-larga'));

function filaDeLista(string $email, array $extra): WaitlistSignup
{
    return WaitlistSignup::create(array_merge([
        'email' => $email,
        'interests' => [],
        'consent_version' => 'v',
        'consented_at' => now(),
        'ip_hash' => str_repeat('b', 64),
        'confirm_token_hash' => hash('sha256', $email),
    ], $extra));
}

it('maintenance:daily borra solo las inscripciones sin confirmar ya vencidas', function () {
    filaDeLista('vencida@example.com', ['confirm_expires_at' => now()->subMinute()]);
    $vigente = filaDeLista('vigente@example.com', ['confirm_expires_at' => now()->addHour()]);
    $confirmada = filaDeLista('ok@example.com', ['confirm_expires_at' => now()->subDays(5), 'confirmed_at' => now()->subDays(6)]);

    $this->artisan('maintenance:daily')->assertSuccessful();

    expect(WaitlistSignup::pluck('id')->sort()->values()->all())
        ->toBe(collect([$vigente->id, $confirmada->id])->sort()->values()->all());
});

it('está programado todos los días a las 03:00 UTC', function () {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn (Event $evento): bool => str_contains($evento->command ?? '', 'maintenance:daily'));

    expect($eventos)->toHaveCount(1);

    $evento = $eventos->first();
    expect($evento->expression)->toBe('0 3 * * *')
        ->and((string) ($evento->timezone instanceof DateTimeZone ? $evento->timezone->getName() : $evento->timezone))->toBe('UTC');
});

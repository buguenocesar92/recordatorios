<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedSeguro;
use App\Enums\ObligationTypeCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Una inscripción en la lista de espera (spec §4, §7.1).
 *
 * - El correo va CIFRADO en reposo (EncryptedSeguro). La unicidad y los límites
 *   se hacen sobre `email_hash` (HMAC-SHA256 con sal propia), así nadie con
 *   acceso a la tabla ve correos ni puede probar uno sin la sal.
 * - El token de confirmación solo existe como sha256 y sirve una sola vez.
 * - La IP solo como `ip_hash` con sal.
 *
 * @property string $id
 * @property string $email
 * @property string $email_hash
 * @property int|null $comuna_id
 * @property list<string> $interests
 * @property string $consent_version
 * @property Carbon $consented_at
 * @property string|null $ip_hash
 * @property string|null $confirm_token_hash
 * @property Carbon|null $confirm_expires_at
 * @property Carbon|null $confirmed_at
 * @property bool|null $paid_penalty_last_year
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 */
class WaitlistSignup extends Model
{
    use HasUlids;

    /**
     * Supresión (Ley 21.719): la baja y la purga de lo no confirmado BORRAN la
     * fila entera. No hay nada que conservar (spec §7.1).
     */
    public const SUPRESION_POR_BORRADO = true;

    protected $fillable = [
        'email',
        'comuna_id',
        'interests',
        'consent_version',
        'consented_at',
        'ip_hash',
        'confirm_token_hash',
        'confirm_expires_at',
        'confirmed_at',
        'paid_penalty_last_year',
    ];

    protected $hidden = ['email', 'email_hash', 'ip_hash', 'confirm_token_hash'];

    protected function casts(): array
    {
        return [
            'email' => EncryptedSeguro::class,
            'interests' => 'array',
            'consented_at' => 'datetime',
            'confirm_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'paid_penalty_last_year' => 'boolean',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WaitlistSignup $signup): void {
            if ($signup->isDirty('email')) {
                $signup->email = self::normalizar((string) $signup->email);
                $signup->email_hash = self::hashEmail($signup->email);
            }

            $invalidos = array_diff((array) ($signup->interests ?? []), ObligationTypeCode::values());

            if ($invalidos !== []) {
                throw new InvalidArgumentException('Tipos de obligación desconocidos: '.implode(', ', $invalidos));
            }
        });
    }

    public static function normalizar(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function hashEmail(string $email): string
    {
        return hash_hmac('sha256', self::normalizar($email), (string) config('waitlist.hash_salt'));
    }

    public static function hashIp(?string $ip): string
    {
        return hash_hmac('sha256', 'ip|'.(string) $ip, (string) config('waitlist.hash_salt'));
    }

    /**
     * Sin confirmar y vencida, o pasada la vida máxima absoluta desde
     * created_at (aunque alguien haya «refrescado» el vencimiento).
     *
     * @param  Builder<WaitlistSignup>  $query
     */
    public function scopeExpiredUnconfirmed(Builder $query): void
    {
        $query->whereNull('confirmed_at')->where(function (Builder $q): void {
            $q->where('confirm_expires_at', '<', now())
                ->orWhere('created_at', '<', now()->subHours(self::vidaMaximaSinConfirmar()));
        });
    }

    public static function vidaMaximaSinConfirmar(): int
    {
        return (int) config('waitlist.unconfirmed_max_lifetime_hours', 168);
    }

    /** Si ya pasó la vida máxima absoluta desde que se creó. */
    public function superoLaVidaMaxima(): bool
    {
        return $this->created_at !== null && $this->created_at->copy()->addHours(self::vidaMaximaSinConfirmar())->lessThanOrEqualTo(now());
    }

    /** Vencimiento del enlace: 72 h desde ahora, nunca más allá de la vida máxima. */
    public function vencimientoDeConfirmacion(): Carbon
    {
        $porTtl = now()->addHours((int) config('waitlist.confirm_ttl_hours', 72));

        if ($this->created_at === null) {
            return $porTtl;
        }

        $tope = $this->created_at->copy()->addHours(self::vidaMaximaSinConfirmar());

        return $porTtl->lessThan($tope) ? $porTtl : $tope;
    }

    public function markConfirmed(): void
    {
        // El hash del token se conserva: es lo que permite responder 410
        // («este enlace ya se usó») en vez de 404 a quien vuelve a abrirlo.
        // Un token ya usado no sirve para nada: confirmed_at manda. Pasadas
        // 72 h el hash se borra (el enlace del correo ya venció de todos modos).
        // El seudónimo de la IP solo servía contra abusos del alta: se borra.
        // El hash del token se borra 72 h después (maintenance:daily).
        $this->forceFill(['confirmed_at' => now(), 'ip_hash' => null])->save();
    }

    /** @return BelongsTo<Comuna, $this> */
    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Comuna::class);
    }
}

<?php

namespace App\Models;

use App\Models\Pivots\AsignacionDeRol;
use App\Notifications\Auth\RestablecerContrasenaNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable {
        // La clase redefine «roles()» más abajo para añadirle un pivote
        // propio (ver ese método): sin este alias no habría forma de invocar
        // la implementación original del paquete, que es la que arma la
        // relación de verdad.
        HasRoles::roles as rolesDelPaquete;
    }

    /**
     * Qué se anota del historial de una cuenta.
     *
     * Solo el nombre y el correo. La contraseña queda fuera a propósito:
     * aunque se guarde cifrada, su resumen no tiene por qué quedar copiado en
     * una tabla que lee más gente que la de usuarios. El código del segundo
     * factor y la dirección tampoco: son datos personales sin valor para una
     * auditoría de cambios (Ley 21.719, minimización).
     *
     * Los roles NO viajan por acá —son una relación, no una columna— y se
     * anotan aparte, al guardarse el formulario.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Usa el correo de restablecimiento con la identidad visual del sistema en
     * lugar del genérico de Laravel.
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new RestablecerContrasenaNotification($token));
    }

    /**
     * Acceso a los paneles de Filament por rol o permiso.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->can('access_panel_'.$panel->getId());
    }

    /**
     * Redirección dinámica según roles o permisos tras el inicio de sesión.
     */
    public function homePath(): string
    {
        if ($this->hasRole('super_admin')) {
            return '/admin';
        }

        return '/admin';
    }

    /**
     * La relación de roles, con un pivote propio.
     *
     * «HasRoles::roles()» (vendor) ya arma la relación; acá se le añade
     * «->using()» para que «attach()», «detach()» y «sync()» —el camino que
     * usan por igual «assignRole()»/«syncRoles()» del paquete y
     * «saveStateToRelationship()» de Filament— pasen por
     * «save()»/«delete()» de verdad, en vez de una consulta directa que no
     * dispara nada. Es lo único que hace posible el candado contra la
     * escalada a super_admin fuera del formulario: ver
     * «App\Models\Pivots\AsignacionDeRol» y «App\Support\GuardaDeRolAdministrador».
     *
     * @return BelongsToMany<Model, Model, AsignacionDeRol, 'pivot'>
     */
    public function roles(): BelongsToMany
    {
        return $this->rolesDelPaquete()->using(AsignacionDeRol::class);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_code',
        'two_factor_expires_at',
        'direccion',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_expires_at' => 'datetime',
        ];
    }
}

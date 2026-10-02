<?php

namespace Database\Seeders;

use App\Filament\Pages\Respaldos;
use App\Models\User;
use BezhanSalleh\FilamentShield\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Páginas y widgets cuyo permiso NO recibe `administrator`, cada uno con su razón.
     * super_admin no necesita permiso (pasa por el Gate::before); el resto de los
     * permisos de páginas y widgets los recibe `administrator`. Lo que se agregue
     * acá tiene que justificarse: un permiso sin dueño es una pantalla que nadie ve
     * (`NingunPermisoHuerfanoTest`).
     *
     * - Respaldos: es la base de datos entera, con todos los datos personales. Quien
     *   la baja tiene que ser quien responde por el sistema, no cualquiera con
     *   acceso al panel (Ley 21.719: minimización y trazabilidad).
     *
     * Criterio para sumar uno nuevo: ¿lo necesita quien administra el panel todos los
     * días (escritorio, diagnóstico en palabras)? Va a `administrator`. ¿Expone datos
     * en bloque, secretos o herramientas de desarrollo? Va acá.
     *
     * @var list<class-string>
     */
    public const SOLO_SUPER_ADMIN = [
        Respaldos::class,
    ];

    public function run(): void
    {
        // Limpiar la caché de permisos
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Roles básicos
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Crear permisos personalizados
        $permissions = [
            'access_panel_admin',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Asignar permisos básicos a roles: acceso al panel más las páginas y widgets
        // que corresponden a quien administra (ver SOLO_SUPER_ADMIN).
        $admin->syncPermissions(['access_panel_admin', ...$this->permisosDeAdministrador()]);

        // Las cuentas de demostración NO se crean en producción: «admin@example.com»
        // con la contraseña «password» y el rol super_admin es una puerta trasera, y
        // este scaffold se clona a cada sistema municipal —la puerta se abriría una vez
        // por instalación—. En local sirven para entrar sin trámite y ahí sí se crean.
        //
        // Y van con firstOrCreate, no con updateOrCreate: sembrar de nuevo no debe pisar
        // la credencial de una cuenta que ya existe. Con updateOrCreate, un
        // `env:provision` de mantenimiento devolvía la contraseña del administrador real
        // a «password» sin que nadie lo notara.
        if (! app()->environment('production')) {
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Super Administrador',
                    'password' => Hash::make('password'),
                ]
            );
            // assignRole y no syncRoles: `syncRoles()` hace `detach()` de TODOS los
            // roles antes de volver a asignarlos, y eso choca de frente con
            // «GuardaDeRolAdministrador», que impide dejar al sistema sin un solo
            // super_admin. La primera siembra pasa porque no hay nada que quitar; de
            // la segunda en adelante revienta, y como el entrypoint de desarrollo
            // siembra en cada `make up`, el contenedor quedaba en bucle de reinicio.
            // `assignRole()` solo agrega lo que falta: es idempotente y además no le
            // arrebata a la cuenta ningún rol que alguien le haya dado a mano.
            $adminUser->assignRole($superAdmin->name);

            $stdUser = User::firstOrCreate(
                ['email' => 'user@example.com'],
                [
                    'name' => 'Usuario Estándar',
                    'password' => Hash::make('password'),
                ]
            );
            $stdUser->assignRole($userRole->name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Los permisos de páginas y widgets que Shield exige, menos los reservados a
     * super_admin. Solo los que ya existen: `shield:generate` los crea antes de
     * sembrar (entrypoint y `env:provision`); en una BD sin ellos no se inventan.
     *
     * @return list<string>
     */
    private function permisosDeAdministrador(): array
    {
        if (Filament::getCurrentPanel() === null) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
        }

        $shield = app(FilamentShield::class);

        $permisos = collect($shield->getPages())
            ->merge($shield->getWidgets())
            ->reject(fn ($entrada, $llave): bool => in_array(
                is_array($entrada) ? ($entrada['class'] ?? $llave) : $llave,
                self::SOLO_SUPER_ADMIN,
                true,
            ))
            ->flatMap(fn (array $entrada): array => array_keys($entrada['permissions']))
            ->unique()
            ->values()
            ->all();

        return Permission::whereIn('name', $permisos)->where('guard_name', 'web')->pluck('name')->all();
    }
}

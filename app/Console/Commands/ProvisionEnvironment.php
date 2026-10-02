<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aprovisiona/actualiza el entorno: regenera los permisos de Shield para el panel admin,
 * siembra roles y onboarding, y crea o promueve al super administrador.
 *
 *   php artisan env:provision \
 *       --admin-email=admin@example.com --admin-name="Admin" --admin-password=...
 *
 * Sin `--admin-*` solo hace Shield + roles + onboarding.
 */
class ProvisionEnvironment extends Command
{
    protected $signature = 'env:provision
        {--admin-email= : Email del super administrador a crear/promover (opcional)}
        {--admin-name=Administrador : Nombre del super administrador}
        {--admin-password= : Contraseña; si se omite y hay terminal, se pregunta}
        {--reset-password : Fijar la contraseña aunque la cuenta ya exista}
        {--skip-shield : No regenerar los permisos de Shield}';

    protected $description = 'Provisiona el entorno: permisos Shield + roles + onboarding + super admin (idempotente).';

    public function handle(): int
    {
        if (! $this->option('skip-shield')) {
            $this->info('🛡  Generando permisos de Shield para el panel admin...');
            Artisan::call('shield:generate', [
                '--all' => true,
                '--panel' => 'admin',
                '--no-interaction' => true,
                // SOLO los permisos. Sin esta opción, `shield:generate` además
                // REESCRIBE las policies en disco con el formato crudo de su
                // plantilla, y de paso borra los métodos que no son los estándar:
                // en rrhh se llevó el `export()` de tres policies y la acción de
                // exportar quedó oculta para todos, sin que nada avisara. Este
                // comando corre en el despliegue y en la suite; en los dos casos lo
                // que hace falta son los permisos, no regenerar código fuente.
                '--option' => 'permissions',
            ], $this->getOutput());
        }

        $this->info('🌱 Sembrando roles/permisos y onboarding...');
        Artisan::call('db:seed', ['--class' => 'RolesSeeder', '--force' => true], $this->getOutput());
        Artisan::call('db:seed', ['--class' => 'OnboardingToursSeeder', '--force' => true], $this->getOutput());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($email = $this->option('admin-email')) {
            return $this->crearSuperAdmin($email);
        }

        $this->newLine();
        $this->info('✅ Listo. Roles y permisos sembrados.');

        return self::SUCCESS;
    }

    private function crearSuperAdmin(string $email): int
    {
        if (! Role::where('name', 'super_admin')->where('guard_name', 'web')->exists()) {
            $this->error("No existe el rol 'super_admin'. Corré primero la siembra de roles.");

            return self::FAILURE;
        }

        $password = $this->option('admin-password')
            ?: ($this->input->isInteractive() ? $this->secret('Contraseña del super admin') : null);

        if (! $password) {
            $this->error('Falta la contraseña: pasá --admin-password=... (o ejecutá con terminal interactiva).');

            return self::FAILURE;
        }

        // Crear si no está, pero NO pisar la credencial de una cuenta que ya existe:
        // este comando se corre en el servidor para tareas de mantenimiento, y con
        // updateOrCreate cada ejecución devolvía la contraseña del administrador real a
        // la que llevara el script de despliegue. Para cambiarla de verdad está
        // --reset-password, que obliga a decirlo a propósito.
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $this->option('admin-name'), 'password' => $password],
        );

        if (! $user->wasRecentlyCreated && $this->option('reset-password')) {
            $user->update(['password' => $password]);
            $this->warn("Contraseña restablecida para {$email}.");
        } elseif (! $user->wasRecentlyCreated) {
            $this->line("La cuenta {$email} ya existía: se conserva su contraseña (usá --reset-password para cambiarla).");
        }

        // Idempotente: asignar SOLO si falta. `syncRoles()` quita todos los roles y los
        // vuelve a poner, y quitarle `super_admin` a la única cuenta que lo tiene choca
        // con «GuardaDeRolAdministrador» (ValidationException) aunque el rol sea el mismo.
        // Es el caso de `--reset-password` sobre el único administrador en producción.
        // El candado no se toca: simplemente no hay nada que quitar.
        if (! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        $this->newLine();
        $this->info("✅ Super administrador listo: {$email}");

        return self::SUCCESS;
    }
}

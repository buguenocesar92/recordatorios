<?php

namespace Database\Seeders;

use Kraftdo\Shared\Onboarding\OnboardingTour;
use Illuminate\Database\Seeder;

/**
 * Sembrador de tours de onboarding interactivos de ejemplo.
 */
class OnboardingToursSeeder extends Seeder
{
    public function run(): void
    {
        $def = [
            'system' => 'admin',
            'role' => 'super_admin',
            'surface' => 'panel',
            'name' => 'Panel de Administración · Tour Inicial',
            'steps' => [
                [
                    'title' => '👋 Bienvenido/a al Panel de Control',
                    'position' => 'bottom',
                    'description' => 'Esta guía te dará un recorrido rápido por la interfaz administrativa del sistema. ¡Comencemos!',
                ],
                [
                    'title' => 'Menú Lateral',
                    'selector' => '.fi-sidebar',
                    'position' => 'right',
                    'description' => 'Aquí encuentras todos los recursos del sistema: Usuarios, Registro de Auditorías y la Configuración de Guías.',
                ],
                [
                    'title' => 'Gestión de Usuarios',
                    'selector' => '[href$="/admin/users"]',
                    'position' => 'right',
                    'description' => 'Desde esta sección puedes registrar nuevos usuarios administrativos, asignarles roles de acceso y activar el doble factor de autenticación.',
                ],
                [
                    'title' => 'Historial de Auditoría',
                    'selector' => '[href$="/admin/activities"]',
                    'position' => 'right',
                    'description' => 'Consulta el registro detallado de todas las acciones que realizan los usuarios: quién, qué y cuándo modificó información.',
                ],
                [
                    'title' => 'Personalizar Guías',
                    'selector' => '[href$="/admin/onboarding-tours"]',
                    'position' => 'right',
                    'description' => 'Aquí puedes editar, reordenar o crear nuevos recorridos interactivos guiados con lector por voz para tus usuarios.',
                ],
                [
                    'title' => 'Tu Menú de Usuario',
                    'selector' => '.fi-user-menu',
                    'position' => 'bottom',
                    'description' => 'En el menú superior derecho puedes editar tu perfil personal, cambiar tu contraseña de forma segura y cerrar tu sesión.',
                ],
                [
                    'title' => 'Botón de Ayuda',
                    'selector' => '.fi-topbar',
                    'position' => 'bottom',
                    'description' => 'Si alguna vez olvidas cómo funciona una sección, presiona el botón "Ayuda" que se inyecta en la barra superior para repetir este recorrido.',
                ],
            ],
        ];

        $tour = OnboardingTour::updateOrCreate(
            ['system' => $def['system'], 'role' => $def['role']],
            ['name' => $def['name'], 'surface' => $def['surface'], 'active' => true],
        );

        $tour->steps()->delete();
        foreach ($def['steps'] as $i => $step) {
            $tour->steps()->create([
                'order' => $i + 1,
                'title' => $step['title'],
                'description' => $step['description'],
                'element_selector' => $step['selector'] ?? null,
                'position' => $step['position'] ?? 'bottom',
            ]);
        }
    }
}

<?php

declare(strict_types=1);

use App\Filament\Pages\Escritorio;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

// Formato de Shield v4. Migrado desde el config v3: auth_provider_model pasó de
// ['fqcn' => ...] a string, `entities`/`generator`/`permission_prefixes` se
// reemplazaron por `shield_resource.tabs`/`policies`/`permissions`, y
// register_role_policy pasó de array a bool.
return [

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    'tenant_model' => null,

    'auth_provider_model' => 'App\\Models\\User',

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        // `define_via_gate` venía en `false` desde el scaffold original: con ese valor
        // Shield NO registra ningún Gate::before pese a que `intercept_gate` dice
        // 'before' (ver FilamentShieldServiceProvider::boot(), que solo actúa cuando
        // esta bandera es true). El acceso de super_admin lo daba, en realidad, el
        // Gate::before manual de AppServiceProvider. En true, Shield registra el
        // antes por su cuenta y ese manual sobra: dos caminos para lo mismo es
        // difícil de razonar y una policy futura podría saltarse por cualquiera de
        // los dos. Ver tests/Feature/GateBeforeUnicoTest.php.
        'define_via_gate' => true,
        'intercept_gate' => 'before',
    ],

    'panel_user' => [
        'enabled' => true,
        'name' => 'panel_user',
    ],

    // IMPORTANTE: snake + '_' reproduce la nomenclatura de v3 (view_any_user).
    // Con los valores por defecto de v4 (pascal + ':') los permisos ya existentes
    // en la BD dejarían de coincidir y los roles perderían sus permisos sin que
    // ningún test lo note.
    'permissions' => [
        'separator' => '_',
        'case' => 'snake',
        'generate' => true,
    ],

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => true,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    'resources' => [
        'subject' => 'model',
        'manage' => [
            RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
        ],
        'exclude' => [
            //
        ],
    ],

    'pages' => [
        'subject' => 'class',
        'prefix' => 'page',
        'exclude' => [
            // El escritorio va excluido, igual que lo estaba el de fábrica: es la
            // pantalla a la que llega todo el mundo al identificarse, y gatearla
            // con un permiso deja a quien no lo tenga sin ningún sitio al que ir.
            Escritorio::class,
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'widget',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    'custom_permissions' => [
        'access_panel_admin',
    ],

    'discovery' => [
        'discover_all_resources' => true,
        'discover_all_widgets' => true,
        'discover_all_pages' => true,
    ],

    'register_role_policy' => true,

];

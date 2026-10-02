<?php

use App\Providers\AppServiceProvider;
use App\Providers\FeaturesServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    FeaturesServiceProvider::class,
    AdminPanelProvider::class,
];

<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Una cuenta nueva nace sin roles, así que el «antes» está vacío: lo que
        // interesa anotar es con qué roles se creó y quién la creó.
        $cuenta = $this->getRecord();

        if ($cuenta instanceof User) {
            UserResource::anotarCambioDeRoles($cuenta, []);
        }
    }
}

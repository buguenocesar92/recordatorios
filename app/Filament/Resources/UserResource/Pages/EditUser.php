<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Los roles que tenía la cuenta antes de guardar.
     *
     * Se leen antes porque después ya están sincronizados y no hay con qué
     * comparar: sin el «antes», la anotación diría qué roles quedaron pero no
     * cuáles se quitaron.
     *
     * @var list<string>
     */
    protected array $rolesAntesDeGuardar = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $cuenta = $this->getRecord();

        if ($cuenta instanceof User) {
            $this->rolesAntesDeGuardar = array_values(array_map(
                strval(...),
                $cuenta->roles()->pluck('name')->all(),
            ));
        }
    }

    protected function afterSave(): void
    {
        $cuenta = $this->getRecord();

        if ($cuenta instanceof User) {
            UserResource::anotarCambioDeRoles($cuenta, $this->rolesAntesDeGuardar);
        }
    }
}

<?php

namespace App\Filament\Resources\OnboardingTourResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProgressRelationManager extends RelationManager
{
    protected static string $relationship = 'progress';

    protected static ?string $title = 'Progreso de usuarios';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')->label('Usuario')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Email')->toggleable(),
                IconColumn::make('completed')->label('Completado')->boolean(),
                TextColumn::make('completed_at')->label('Fecha')->dateTime('d/m/Y H:i')
                    ->placeholder('—')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('completed')->label('Completado'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Resetear'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Resetear seleccionados'),
                ]),
            ]);
    }
}

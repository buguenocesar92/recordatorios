<?php

namespace App\Filament\Resources\OnboardingTourResource\RelationManagers;

use Kraftdo\Shared\Onboarding\OnboardingTour;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Pasos del tour';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Descripción (se lee en voz alta)')
                ->required()
                ->rows(3)
                ->columnSpanFull()
                ->helperText('Este texto se muestra en el popover y lo lee el lector de voz.'),
            TextInput::make('element_selector')
                ->label('Selector CSS del elemento')
                ->maxLength(255)
                ->placeholder('[data-tour="personas"]')
                ->helperText('Usa atributos data- estables (ej: [data-tour="..."]). Déjalo vacío para un paso centrado.'),
            TextInput::make('pre_action_selector')
                ->label('Pre-acción: clic antes del paso (opcional)')
                ->maxLength(255)
                ->placeholder('[data-tour="nav-registro"]')
                ->helperText('Selector de una pestaña/botón que el tour pulsará antes de mostrar este paso.'),
            Select::make('position')
                ->label('Posición del popover')
                ->required()
                ->default('bottom')
                ->options([
                    'top' => 'Arriba',
                    'bottom' => 'Abajo',
                    'left' => 'Izquierda',
                    'right' => 'Derecha',
                ]),
            TextInput::make('order')
                ->label('Orden')
                ->numeric()
                ->default(function () {
                    /** @var OnboardingTour $tour */
                    $tour = $this->getOwnerRecord();

                    return ((int) $tour->steps()->max('order')) + 1;
                })
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('order')->label('#')->sortable(),
                TextColumn::make('title')->label('Título')->searchable()->wrap(),
                TextColumn::make('element_selector')->label('Selector')
                    ->placeholder('— centrado —')->fontFamily('mono')->toggleable(),
                TextColumn::make('position')->label('Posición')->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('Nuevo paso'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

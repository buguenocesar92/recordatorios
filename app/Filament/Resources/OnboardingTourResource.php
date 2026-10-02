<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnboardingTourResource\Pages\CreateOnboardingTour;
use App\Filament\Resources\OnboardingTourResource\Pages\EditOnboardingTour;
use App\Filament\Resources\OnboardingTourResource\Pages\ListOnboardingTours;
use App\Filament\Resources\OnboardingTourResource\RelationManagers\ProgressRelationManager;
use App\Filament\Resources\OnboardingTourResource\RelationManagers\StepsRelationManager;
use Kraftdo\Shared\Onboarding\OnboardingTour;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Panel de administración de los Tours de Onboarding.
 */
class OnboardingTourResource extends Resource
{
    protected static ?string $model = OnboardingTour::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Onboarding';

    protected static ?string $modelLabel = 'tour';

    protected static ?string $pluralModelLabel = 'Tours de onboarding';

    protected static ?string $slug = 'onboarding-tours';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('system')
                ->label('Sistema')
                ->required()
                ->options([
                    'default' => 'Sistema principal',
                    'hub' => 'Inicio (hub)',
                ]),
            Select::make('role')
                ->label('Rol')
                ->required()
                ->options(fn () => class_exists(Role::class) ? Role::orderBy('name')->pluck('name', 'name') : ['admin' => 'Administrador', 'user' => 'Usuario'])
                ->helperText('El tour se mostrará a los usuarios con este rol.'),
            Select::make('surface')
                ->label('Superficie')
                ->options([
                    'app' => 'App operativa (PWA)',
                    'panel' => 'Panel de administración',
                    'hub' => 'Hub /inicio',
                ])
                ->helperText('Dónde aplica el tour.'),
            Toggle::make('active')
                ->label('Activo')
                ->default(true)
                ->helperText('Desactívalo para apagar el tour sin borrarlo.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('system')->label('Sistema')->badge(),
                TextColumn::make('role')->label('Rol')->badge(),
                TextColumn::make('surface')->label('Superficie')->badge()->color('gray'),
                TextColumn::make('steps_count')->label('Pasos')
                    ->counts('steps')->badge()->color('gray'),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Previsualizar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(fn (OnboardingTour $record, $livewire) => $livewire->js(
                        'window.onboardingPreview && window.onboardingPreview('.$record->id.')'
                    )),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
            ProgressRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnboardingTours::route('/'),
            'create' => CreateOnboardingTour::route('/create'),
            'edit' => EditOnboardingTour::route('/{record}/edit'),
        ];
    }
}

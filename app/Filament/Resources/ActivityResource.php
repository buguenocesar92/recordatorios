<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * Visor de auditoría (solo lectura) sobre spatie/laravel-activitylog.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    protected static ?string $slug = 'auditoria';

    protected static ?int $navigationSort = 90;

    public static function canViewAny(): bool
    {
        // `view_any_activity` es el permiso que Shield genera para este recurso y el
        // único que aparece tildable en la pantalla de Roles. `view_any_auditoria`
        // —derivado del slug— NO existe como permiso: dejaba la trazabilidad invisible
        // para cualquiera que no fuese super_admin.
        return auth()->user()?->can('view_any_activity') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('causer.name')->label('Usuario')->default('Sistema')->searchable(),
                TextColumn::make('description')->label('Acción')->badge()->color('info'),
                TextColumn::make('subject_type')->label('Entidad')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('subject_id')->label('ID registro')->toggleable(),
                TextColumn::make('event')->label('Evento')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success', 'updated' => 'warning', 'deleted' => 'danger', default => 'gray',
                    })->toggleable(),
                TextColumn::make('properties')->label('Cambios')
                    ->formatStateUsing(fn ($state): string => filled($state) ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—') : '—')
                    ->limit(80)->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('log_name')->label('Log')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->label('Evento')->options([
                    'created' => 'Creado', 'updated' => 'Actualizado', 'deleted' => 'Eliminado',
                ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}

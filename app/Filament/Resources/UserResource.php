<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de cuentas de usuario y asignación de roles.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $slug = 'usuarios';

    protected static ?int $navigationSort = 80;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_user') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('email')->label('Correo')->email()->required()
                ->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('password')->label('Contraseña')->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('En edición, dejar en blanco para mantener la actual.')
                ->maxLength(255)
                // La política del sistema —doce caracteres con letras y
                // números— regía en el restablecimiento por correo y no acá,
                // que es por donde se crean todas las cuentas: un administrador
                // podía dejar «1». La condición evita que salte al editar sin
                // tocar la contraseña, que es el caso normal y llega en blanco.
                //
                // «Password::default()» agrega «uncompromised()» salvo que
                // «seguridad.filtraciones.modo» esté en 'apagado' (ver
                // «AppServiceProvider::fijarPoliticaDeContrasenas()»): cada alta
                // o cambio de contraseña desde ESTE formulario hace una llamada
                // de red saliente a la API de Have I Been Pwned —solo manda un
                // fragmento del resumen k-anonymity de la contraseña, nunca la
                // contraseña en sí— para comprobar que no esté en una filtración
                // conocida.
                //
                // Sin salida a internet desde el servidor, lo que pasa lo decide
                // ese modo y NO se decide solo: en 'cerrado' el alta se bloquea
                // y queda escrito por qué; en 'apagado' no se comprueba nada y
                // la política tampoco lo promete. Lo que ya no puede pasar es lo
                // que hacía el verificador de fábrica, que daba la contraseña
                // por limpia sin haberla comprobado.
                ->rule(Password::default(), fn (?string $state): bool => filled($state))
                ->suffixAction(
                    Action::make('generatePassword')
                        ->icon('heroicon-m-key')
                        ->tooltip('Generar contraseña segura')
                        ->action(function (Set $set) {
                            $password = Str::password(16, symbols: true);
                            $set('password', $password);

                            Notification::make()
                                ->title('Contraseña segura generada')
                                ->body('Haz clic en el icono del ojo para verla.')
                                ->success()
                                ->send();
                        })
                ),
            Select::make('roles')->label('Roles')
                ->relationship('roles', 'name')
                ->multiple()->preload()->required()
                // Nadie reparte el rol que lo puede todo si no lo tiene. Sin
                // esto, a quien se le delegaba el alta de personal —solo
                // «update_user»— se marcaba la casilla y pasaba a mandar en el
                // sistema entero.
                //
                // No es solo cosmético: Filament vacía la regla «in» del campo
                // cuando el valor enviado corresponde a una opción
                // deshabilitada, así que reenviar la petición a mano tampoco
                // sirve. Se compara la etiqueta porque el valor de la opción es
                // la clave del rol, no su nombre.
                ->disableOptionWhen(fn (mixed $label): bool => $label === static::rolQueLoPuedeTodo()
                    && ! static::quienEdita()?->hasRole(static::rolQueLoPuedeTodo()))
                // Sin esto el rechazo se ve como «el campo roles seleccionado
                // no es válido», que no dice nada: quien lo lea va a pensar que
                // el formulario está roto en vez de entender que le falta el
                // rol. Aparece también al editar una cuenta que YA lo tiene, y
                // ahí es donde más falta hace la explicación.
                // La clave lleva «*.» porque Filament valida los desplegables
                // múltiples elemento por elemento: el mensaje cuelga de
                // «roles.*.in», y con «in» a secas no se aplica ninguno.
                ->validationMessages([
                    '*.in' => 'Ese rol solo lo puede asignar quien ya lo tiene. Pídeselo a un administrador.',
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('Roles')->badge()->color('info'),
                TextColumn::make('created_at')->label('Creado')->dateTime('d-m-Y')->toggleable(),
            ])
            ->recordActions([EditAction::make(), self::deleteAction()])
            ->toolbarActions([BulkActionGroup::make([self::deleteBulkAction()])]);
    }

    /**
     * Borrar UNA cuenta, sin dejar el candado de «GuardaDeRolAdministrador»
     * en silencio.
     *
     * «HasRoles::bootHasRoles()» (spatie/laravel-permission) ya desata los
     * roles antes de borrar —es lo que hace que el candado se entere—, así
     * que la única cuenta con el rol que lo puede todo NO se llega a borrar.
     * Pero de fábrica esa «ValidationException» solo llega al log
     * (`report($exception)` dentro de las acciones de Filament): quien hizo
     * clic ve que «no pasó nada» sin ninguna explicación. Acá se atrapa y se
     * muestra el motivo real.
     */
    private static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->action(function (User $record): void {
                try {
                    DB::transaction(fn () => $record->delete());

                    Notification::make()->title('Usuario eliminado')->success()->send();
                } catch (ValidationException $excepcion) {
                    self::notificarFalloDeEliminacion($excepcion);
                }
            });
    }

    /**
     * Borrar VARIAS cuentas a la vez, con el mismo candado.
     *
     * Envuelta en transacción a propósito: el «DeleteBulkAction» de fábrica
     * borra registro por registro y sigue con el resto si uno falla —bien
     * para un error de datos suelto, mal acá, porque dejaría borrados a
     * algunos administradores y al último con el rol intacto, un estado a
     * medias que nadie pidió—. Si el candado corta a cualquiera del lote,
     * no se borra ninguno.
     */
    private static function deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->action(function (EloquentCollection $records): void {
                try {
                    DB::transaction(function () use ($records): void {
                        $records->each->delete();
                    });

                    Notification::make()->title('Usuarios eliminados')->success()->send();
                } catch (ValidationException $excepcion) {
                    self::notificarFalloDeEliminacion($excepcion);
                }
            });
    }

    private static function notificarFalloDeEliminacion(ValidationException $excepcion): void
    {
        Notification::make()
            ->title('No se pudo eliminar')
            ->body(collect($excepcion->errors())->flatten()->implode(' '))
            ->danger()
            ->send();
    }

    /**
     * El nombre del rol que lo puede todo.
     *
     * No se escribe a mano: en varios sistemas del ecosistema el rol de
     * administración se llama «administrador», y un nombre equivocado acá deja
     * el candado sin efecto en silencio.
     */
    public static function rolQueLoPuedeTodo(): string
    {
        return (string) config('filament-shield.super_admin.name', 'super_admin');
    }

    /** La cuenta que está usando el formulario, o null fuera de una sesión. */
    public static function quienEdita(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    /**
     * Deja constancia de un cambio de roles.
     *
     * Va acá y no en «syncRoles» del modelo porque el formulario de Filament no
     * pasa por ahí: guarda la relación con «sync()» sobre la tabla intermedia,
     * que no dispara ningún evento del modelo. Un registro colgado de syncRoles
     * no se habría escrito nunca por el camino que de verdad se usa.
     *
     * @param  list<string>  $antes  Los roles que tenía la cuenta antes de guardar.
     */
    public static function anotarCambioDeRoles(User $cuenta, array $antes): void
    {
        $despues = $cuenta->roles()->pluck('name')->all();

        sort($antes);
        sort($despues);

        // Guardar el formulario sin tocar los roles es lo normal: sin esto, cada
        // corrección de un correo dejaría una anotación que no dice nada y el
        // registro se vuelve ilegible justo cuando hace falta leerlo.
        if ($antes === $despues) {
            return;
        }

        activity()
            ->causedBy(static::quienEdita())
            ->performedOn($cuenta)
            ->withProperties(['antes' => $antes, 'despues' => $despues])
            ->log('cambio de roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}

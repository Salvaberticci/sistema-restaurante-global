<?php

namespace App\Filament\Resources;

use App\Domain\Iam\Roles as DefinicionRoles;
use App\Filament\Navegacion;
use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * Solo lectura a propósito.
 *
 * Los cinco roles del sistema son parte de la lógica: el código los nombra para
 * decidir qué ve cada quien. Editarlos desde el panel parece-nameservience de
 * libertad y en realidad abre la puerta a que alguien cree un rol "supercajero"
 * con permisos que nadie probó y descubra en producción que el IGTF no le sale.
 *
 * Los permisos, en cambio, se administran en la matriz: ahí sí hay que poder
 * tocar, porque cambian con el negocio.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'roles';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = Navegacion::ADMINISTRACION;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'roles';

    protected static bool $shouldRegisterNavigation = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Rol')
                ->schema([
                    TextInput::make('name')->label('Nombre')->disabled(),
                    Textarea::make('descripcion')->label('Qué puede hacer')->disabled()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            // `permissions` y `users` se usan en varias columnas. Sin la carga
            // previa, cada fila hace una consulta aparte y además el modelo
            // tiene prohibido el lazy loading.
            ->modifyQueryUsing(fn ($query) => $query->with(['permissions', 'users']))
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->description(fn (Role $record) => DefinicionRoles::todos()[$record->name]['descripcion'] ?? ''),

                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->badge()
                    ->color(fn (Role $record) => $record->name === DefinicionRoles::ADMINISTRADOR ? 'danger' : 'info'),

                TextColumn::make('usuarios_count')
                    ->label('Personas')
                    ->counts('users')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('permisos')
                    ->label('Puede')
                    ->state(fn (Role $record) => $record->permissions->pluck('name')->implode(', ') ?: '—')
                    ->badge()
                    ->color('gray')
                    ->limit(8)
                    ->tooltip(fn (Role $record) => $record->permissions->pluck('name')->implode(', '))
                    ->toggleable(),
            ])
            ->actions([
                EditAction::make()->label('Ver detalle'),
            ])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}

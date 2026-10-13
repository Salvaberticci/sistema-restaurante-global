<?php

namespace App\Filament\Resources;

use App\Filament\Navegacion;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'personal';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = Navegacion::ADMINISTRACION;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'personal';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Persona')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(191)
                        ->unique(ignoreRecord: true),

                    TextInput::make('cedula')
                        ->label('Cédula')
                        ->maxLength(20)
                        ->placeholder('V-12345678'),

                    TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make('Acceso')
                ->columns(2)
                ->schema([
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                        ->helperText('Vacía el campo para dejar la contraseña como está.'),

                    Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->required()
                        ->helperText('Un mismo usuario puede tener más de un rol.'),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->helperText('Un usuario desactivado conserva su historial y no puede operar.')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email),

                TextColumn::make('cedula')->label('Cédula')->searchable()->placeholder('—'),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('info')
                    ->separator(', '),

                TextColumn::make('iniciales')
                    ->label('')
                    ->badge()
                    ->color(fn (User $record) => $record->puedeOperar() ? 'primary' : 'gray')
                    ->state(fn (User $record) => $record->iniciales())
                    ->toggleable(),

                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->preload(),

                TernaryFilter::make('activo')->label('Solo activos')->default(true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

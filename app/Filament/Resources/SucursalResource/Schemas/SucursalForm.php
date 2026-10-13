<?php

namespace App\Filament\Resources\SucursalResource\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class SucursalForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identificación')
                    ->description('El código es el que aparece en los documentos fiscales.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->helperText('Tres letras. Ejemplo: SAB, PAE'),
                    ]),

                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('direccion')->label('Dirección fiscal')->maxLength(255),
                        TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                        TextInput::make('rif')->label('RIF')->maxLength(20),
                        TextInput::make('ciudad')->label('Ciudad')->maxLength(80),
                        TextInput::make('estado')->label('Estado')->maxLength(60),
                    ]),

                Section::make('Estado')
                    ->schema([
                        Toggle::make('activo')
                            ->label('Activa')
                            ->helperText('Una sucursal con órdenes y facturas no se borra: se desactiva.')
                            ->default(true),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DogResource\Pages;
use App\Models\Client;
use App\Models\Dog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DogResource extends Resource
{
    protected static ?string $model = Dog::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Dogs';

    protected static ?string $modelLabel = 'Dog';

    protected static ?string $pluralModelLabel = 'Dogs';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'last_name')
                        ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->first_name . ' ' . $record->last_name)
                        ->searchable(['first_name', 'last_name'])
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('breed')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.last_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Dog $record): ?string => $record->client?->first_name),
                Tables\Columns\TextColumn::make('breed')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDogs::route('/'),
            'create' => Pages\CreateDog::route('/create'),
            'edit' => Pages\EditDog::route('/{record}/edit'),
        ];
    }
}

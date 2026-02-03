<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Appointments';

    protected static ?string $modelLabel = 'Appointment';

    protected static ?string $pluralModelLabel = 'Appointments';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Cliente')
                        ->relationship('client', 'last_name')
                        ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->first_name . ' ' . $record->last_name)
                        ->searchable(['first_name', 'last_name'])
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Set $set) => $set('dog_id', null))
                        ->createOptionForm([
                            Forms\Components\TextInput::make('first_name')
                                ->label('Nome')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('last_name')
                                ->label('Cognome')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone')
                                ->label('Telefono')
                                ->tel()
                                ->maxLength(50),
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            return Client::create($data)->getKey();
                        }),
                    Forms\Components\Select::make('dog_id')
                        ->label('Cane')
                        ->options(function (Get $get): array {
                            $clientId = $get('client_id');

                            if (blank($clientId)) {
                                return [];
                            }

                            return Dog::query()
                                ->where('client_id', $clientId)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (Get $get): bool => blank($get('client_id')))
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('breed')
                                ->label('Razza')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('notes')
                                ->label('Note')
                                ->columnSpanFull(),
                        ])
                        ->createOptionUsing(function (array $data, Get $get): int {
                            $clientId = $get('client_id');

                            return Dog::create([
                                'client_id' => $clientId,
                                'name' => $data['name'],
                                'breed' => $data['breed'] ?? null,
                                'notes' => $data['notes'] ?? null,
                            ])->getKey();
                        }),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Data e ora inizio')
                        ->minDate(fn () => now()->addMinute()->startOfMinute())
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            $set('status', filled($state) ? 'confirmed' : 'pending');
                        })
                        ->seconds(false),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'In attesa',
                            'confirmed' => 'Confermata',
                            'cancelled' => 'Annullata',
                        ])
                        ->default('pending')
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Appointment $record): string => $record->status === 'confirmed' ? 'bg-green-50' : '')
            ->columns([
                Tables\Columns\TextColumn::make('client.last_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Appointment $record): ?string => $record->client?->first_name),
                Tables\Columns\TextColumn::make('dog.name')
                    ->label('Cane')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'In attesa',
                        'confirmed' => 'Confermata',
                        'cancelled' => 'Annullata',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
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
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}

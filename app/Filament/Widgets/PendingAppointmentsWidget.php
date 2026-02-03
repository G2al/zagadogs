<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Closure;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PendingAppointmentsWidget extends TableWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Appuntamenti da programmare')
            ->poll('5s')
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('client.last_name')
                    ->label('Cliente')
                    ->sortable()
                    ->description(fn (Appointment $record): ?string => $record->client?->first_name),
                Tables\Columns\TextColumn::make('dog.name')
                    ->label('Cane')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('schedule')
                    ->label('Programma')
                    ->icon('heroicon-o-calendar')
                    ->modalHeading('Programma appuntamento')
                    ->form([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Data e ora')
                            ->required()
                            ->minDate(fn () => now()->addMinute()->startOfMinute())
                            ->reactive()
                            ->seconds(false),
                        Checkbox::make('whatsapp_sent')
                            ->label('Confermo invio messaggio WhatsApp (obbligatorio)')
                            ->helperText('Spunta per abilitare il bottone WhatsApp e confermare la prenotazione.')
                            ->accepted()
                            ->required()
                            ->dehydrated(false)
                            ->rules([
                                function (Appointment $record): Closure {
                                    return function (string $attribute, $value, Closure $fail) use ($record): void {
                                        if (blank($this->getWhatsappPhone($record))) {
                                            $fail('Inserisci il numero WhatsApp del cliente.');
                                        }
                                    };
                                },
                            ]),
                        FormActions::make([
                            FormAction::make('open_whatsapp')
                                ->label('Apri WhatsApp')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->url(fn (Appointment $record, Get $get): ?string => $this->getWhatsappUrl($record, $get('starts_at')), true)
                                ->openUrlInNewTab()
                                ->disabled(fn (Appointment $record, Get $get): bool => blank($this->getWhatsappUrl($record, $get('starts_at'))) || ! $get('whatsapp_sent')),
                        ])
                            ->key('whatsapp_actions')
                            ->fullWidth(),
                        Placeholder::make('whatsapp_missing')
                            ->label('WhatsApp')
                            ->content('Inserisci il numero WhatsApp del cliente per inviare il messaggio.')
                            ->visible(fn (Appointment $record): bool => blank($this->getWhatsappPhone($record))),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        $startsAt = Carbon::parse($data['starts_at']);

                        $record->update([
                            'starts_at' => $startsAt,
                        ]);

                        $this->dispatch('filament-fullcalendar--refresh');
                    }),
            ])
            ->emptyStateHeading('Nessun appuntamento da programmare');
    }

    protected function getTableQuery(): Builder
    {
        return Appointment::query()
            ->with(['client', 'dog'])
            ->whereNull('starts_at')
            ->where('status', 'pending');
    }

    protected function getWhatsappUrl(Appointment $record, ?string $startsAt): ?string
    {
        if (blank($startsAt)) {
            return null;
        }

        $record->loadMissing(['client', 'dog']);

        return $this->buildWhatsappUrl(
            $record->client?->phone,
            $record->client?->first_name,
            $record->dog?->name,
            $startsAt,
        );
    }

    protected function getWhatsappPhone(Appointment $record): ?string
    {
        $record->loadMissing(['client']);

        $phone = preg_replace('/\D+/', '', $record->client?->phone ?? '');

        return $phone !== '' ? $phone : null;
    }

    protected function buildWhatsappUrl(?string $phone, ?string $clientName, ?string $dogName, ?string $startsAt): ?string
    {
        $phone = preg_replace('/\D+/', '', $phone ?? '');
        $phone = $this->normalizeItalianPhone($phone);

        if (blank($phone)) {
            return null;
        }

        $message = $this->buildWhatsappMessage($clientName, $dogName, $startsAt);

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    protected function buildWhatsappMessage(?string $clientName, ?string $dogName, ?string $startsAt): string
    {
        $clientName = trim($clientName ?? '');
        $dogName = trim($dogName ?? '');
        $when = $this->formatWhatsappWhen($startsAt);

        $greeting = $clientName !== '' ? "Ciao {$clientName}," : 'Ciao,';
        $dogLine = $dogName !== '' ? "Cane: {$dogName}." : '';

        return trim("{$greeting} prenotazione confermata per {$when}. {$dogLine} Saluti da Zagadogs!");
    }

    protected function normalizeItalianPhone(string $phone): string
    {
        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (! str_starts_with($phone, '39') && strlen($phone) === 10) {
            return '39' . $phone;
        }

        return $phone;
    }

    protected function formatWhatsappWhen(?string $startsAt): string
    {
        if (blank($startsAt)) {
            return '';
        }

        $date = Carbon::parse($startsAt);
        $today = Carbon::now($date->getTimezone())->startOfDay();
        $target = $date->copy()->startOfDay();
        $time = $date->format('H:i');

        if ($target->equalTo($today)) {
            return "oggi alle ore {$time}";
        }

        if ($target->equalTo($today->copy()->addDay())) {
            return "domani alle ore {$time}";
        }

        $dateLabel = $date->locale('it')->translatedFormat('j F');

        return "il {$dateLabel} alle ore {$time}";
    }
}

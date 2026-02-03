<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\Carbon;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendarWidget extends FullCalendarWidget
{
    protected static string $view = 'filament.widgets.appointment-calendar-widget';

    protected static ?int $sort = 1;

    public function mount(): void
    {
        $this->model = Appointment::class;
    }

    public function config(): array
    {
        return [
            'eventDisplay' => 'block',
            'eventColor' => '#16a34a',
            'eventTextColor' => '#ffffff',
            'eventBorderColor' => '#15803d',
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return Appointment::query()
            ->with(['client', 'dog'])
            ->whereBetween('starts_at', [$fetchInfo['start'], $fetchInfo['end']])
            ->get()
            ->map(function (Appointment $appointment): array {
                $dogName = $appointment->dog?->name ?? '';
                $clientName = trim(
                    ($appointment->client?->first_name ?? '') . ' ' . ($appointment->client?->last_name ?? '')
                );

                $title = trim(implode(' - ', array_filter([$dogName, $clientName], fn (string $value): bool => $value !== '')));
                $event = [
                    'id' => $appointment->getKey(),
                    'title' => $title !== '' ? $title : 'Appuntamento',
                    'start' => $appointment->starts_at?->toIso8601String(),
                ];

                return $event;
            })
            ->all();
    }

    public function getFormSchema(): array
    {
        return [
            ...AppointmentResource::form($this->makeForm())->getComponents(),
            Section::make('WhatsApp')
                ->schema([
                    Checkbox::make('whatsapp_sent')
                        ->label('Confermo invio messaggio WhatsApp (obbligatorio)')
                        ->helperText('Spunta per abilitare il bottone WhatsApp e confermare la prenotazione.')
                        ->accepted()
                        ->required()
                        ->dehydrated(false)
                        ->rules([
                            function (Get $get, ?Appointment $record): Closure {
                                return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                    if (! $this->shouldRequireWhatsapp($get, $record)) {
                                        return;
                                    }

                                    if (blank($this->getWhatsappPhone($get, $record))) {
                                        $fail('Inserisci il numero WhatsApp del cliente.');
                                    }
                                };
                            },
                        ])
                        ->visible(fn (Get $get, ?Appointment $record): bool => $this->shouldRequireWhatsapp($get, $record)),
                    FormActions::make([
                        FormAction::make('open_whatsapp')
                            ->label('Apri WhatsApp')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->url(fn (Get $get, ?Appointment $record): ?string => $this->getWhatsappUrl($get, $record), true)
                            ->openUrlInNewTab()
                            ->disabled(fn (Get $get, ?Appointment $record): bool => blank($this->getWhatsappUrl($get, $record)) || ! $get('whatsapp_sent')),
                    ])
                        ->key('whatsapp_actions')
                        ->fullWidth(),
                    Placeholder::make('whatsapp_missing')
                        ->label('WhatsApp')
                        ->content('Inserisci il numero WhatsApp del cliente per inviare il messaggio.')
                        ->visible(fn (Get $get, ?Appointment $record): bool => $this->shouldRequireWhatsapp($get, $record) && blank($this->getWhatsappPhone($get, $record))),
                ])
                ->visible(fn (Get $get, ?Appointment $record): bool => $this->shouldRequireWhatsapp($get, $record)),
        ];
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mountUsing(function (Form $form, array $arguments): void {
                    $start = $arguments['start'] ?? null;

                    $form->fill([
                        'starts_at' => $start,
                        'status' => filled($start) ? 'confirmed' : 'pending',
                    ]);
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            $this->editAction(),
        ];
    }

    protected function viewAction(): Action
    {
        return $this->makeEditAction('view')
            ->extraModalFooterActions([
                Actions\DeleteAction::make(),
            ]);
    }

    protected function editAction(): Actions\EditAction
    {
        return $this->makeEditAction('edit');
    }

    protected function makeEditAction(string $name): Actions\EditAction
    {
        return Actions\EditAction::make($name)
            ->mountUsing(function (Appointment $record, Form $form, array $arguments): void {
                $event = $arguments['event'] ?? [];

                $form->fill([
                    'starts_at' => $event['start'] ?? $record->starts_at,
                    'status' => $record->status === 'cancelled'
                        ? 'cancelled'
                        : (filled($event['start'] ?? $record->starts_at) ? 'confirmed' : 'pending'),
                ]);
            });
    }

    protected function shouldRequireWhatsapp(Get $get, ?Appointment $record): bool
    {
        $startsAt = $get('starts_at');

        if (blank($startsAt)) {
            return false;
        }

        if ($record && filled($record->starts_at)) {
            return false;
        }

        return true;
    }

    protected function getWhatsappUrl(Get $get, ?Appointment $record): ?string
    {
        $startsAt = $get('starts_at');

        if (blank($startsAt)) {
            return null;
        }

        if ($record) {
            $record->loadMissing(['client', 'dog']);

            return $this->buildWhatsappUrl(
                $record->client?->phone,
                $record->client?->first_name,
                $record->dog?->name,
                $startsAt,
            );
        }

        $clientId = $get('client_id');
        $dogId = $get('dog_id');

        if (blank($clientId)) {
            return null;
        }

        $client = Client::find($clientId);
        $dog = $dogId ? Dog::find($dogId) : null;

        return $this->buildWhatsappUrl(
            $client?->phone,
            $client?->first_name,
            $dog?->name,
            $startsAt,
        );
    }

    protected function getWhatsappPhone(Get $get, ?Appointment $record): ?string
    {
        $phone = null;

        if ($record) {
            $record->loadMissing(['client']);
            $phone = $record->client?->phone;
        } else {
            $clientId = $get('client_id');
            $phone = $clientId ? Client::find($clientId)?->phone : null;
        }

        $phone = preg_replace('/\D+/', '', $phone ?? '');

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

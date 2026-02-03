<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'dog_id',
        'starts_at',
        'status',
        'notes',
    ];

    /**
     * @return BelongsTo<Client, Appointment>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Dog, Appointment>
     */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Appointment $appointment): void {
            if ($appointment->status === 'cancelled') {
                return;
            }

            $appointment->status = filled($appointment->starts_at) ? 'confirmed' : 'pending';
        });
    }
}

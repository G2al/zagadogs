<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
    ];

    /**
     * @return HasMany<Dog>
     */
    public function dogs(): HasMany
    {
        return $this->hasMany(Dog::class);
    }

    /**
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

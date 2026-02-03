<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dog extends Model
{
    /** @use HasFactory<\Database\Factories\DogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'name',
        'breed',
        'notes',
    ];

    /**
     * @return BelongsTo<Client, Dog>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

<?php

namespace App\Filament\Resources\DogResource\Pages;

use App\Filament\Resources\DogResource;
use Filament\Resources\Pages\EditRecord;

class EditDog extends EditRecord
{
    protected static string $resource = DogResource::class;
}

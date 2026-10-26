<?php

namespace App\Models;

use App\Enums\Criticality;
use Illuminate\Database\Eloquent\Model;

class CriticalityLevel extends Model
{
    protected $fillable = ['slug', 'name', 'weight', 'color'];

    public function isCritical(): bool
    {
        return $this->slug === Criticality::Critical->value;
    }
}

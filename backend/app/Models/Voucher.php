<?php

// app/Models/Voucher.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'quota' => 'integer',
            'used' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
}

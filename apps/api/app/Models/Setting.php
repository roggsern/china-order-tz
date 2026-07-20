<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];
}

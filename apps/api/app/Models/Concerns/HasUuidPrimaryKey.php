<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasUuidPrimaryKey
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}

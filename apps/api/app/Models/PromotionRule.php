<?php

namespace App\Models;

use App\Enums\PromotionRuleType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRule extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'promotion_id',
        'rule_type',
        'rule_value',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => PromotionRuleType::class,
            'rule_value' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}

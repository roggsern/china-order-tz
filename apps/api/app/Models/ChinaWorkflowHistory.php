<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaWorkflowHistory extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'china_workflow_record_id',
        'order_id',
        'admin_id',
        'action',
        'from_stage',
        'to_stage',
        'reason',
        'metadata',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ChinaWorkflowRecord::class, 'china_workflow_record_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

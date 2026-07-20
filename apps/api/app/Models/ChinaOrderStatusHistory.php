<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaOrderStatusHistory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'china_order_status_history';

    protected $fillable = [
        'china_order_request_id',
        'china_order_quote_id',
        'changed_by_admin_id',
        'changed_by_user_id',
        'previous_status',
        'new_status',
        'notes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderRequest::class, 'china_order_request_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(ChinaOrderQuote::class, 'china_order_quote_id');
    }

    public function changedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

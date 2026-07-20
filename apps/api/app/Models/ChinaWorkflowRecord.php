<?php

namespace App\Models;

use App\Enums\ChinaExportReadiness;
use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinaWorkflowRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'fulfillment_id',
        'stage',
        'qc_status',
        'qc_notes',
        'qc_admin_id',
        'qc_at',
        'consolidation_batch',
        'consolidation_completed_at',
        'export_checklist',
        'export_ready_at',
        'export_approved_by',
        'agent_name',
        'agent_contact',
        'agent_evidence',
        'agent_handed_off_at',
        'agent_admin_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ChinaWorkflowStage::class,
            'qc_status' => ChinaQcStatus::class,
            'qc_at' => 'datetime',
            'consolidation_completed_at' => 'datetime',
            'export_checklist' => 'array',
            'export_ready_at' => 'datetime',
            'agent_handed_off_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Sole authoritative Export Readiness flag.
     * Only ChinaWorkflowEngine may set export_ready_at.
     */
    public function exportReadiness(): ChinaExportReadiness
    {
        return $this->export_ready_at !== null
            ? ChinaExportReadiness::ExportReady
            : ChinaExportReadiness::NotReady;
    }

    public function isAuthoritativelyExportReady(): bool
    {
        return $this->exportReadiness() === ChinaExportReadiness::ExportReady;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ChinaWorkflowHistory::class);
    }
}

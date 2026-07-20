<?php

namespace App\Enums;

/**
 * Authoritative Export Readiness values owned exclusively by ChinaWorkflowEngine.
 * NOT_READY  = export_ready_at is null
 * EXPORT_READY = export_ready_at is set (after all requirements pass)
 */
enum ChinaExportReadiness: string
{
    case NotReady = 'not_ready';
    case ExportReady = 'export_ready';

    public function label(): string
    {
        return match ($this) {
            self::NotReady => 'Not ready for export',
            self::ExportReady => 'Export ready',
        };
    }
}

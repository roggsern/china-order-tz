<?php

namespace App\Enums;

enum ChinaWorkflowStage: string
{
    case AwaitingProcurement = 'awaiting_procurement';
    case ProcurementInProgress = 'procurement_in_progress';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case QcPending = 'qc_pending';
    case QcFailed = 'qc_failed';
    case QcPassed = 'qc_passed';
    case Consolidating = 'consolidating';
    case Consolidated = 'consolidated';
    case ExportReady = 'export_ready';
    case AgentHandedOff = 'agent_handed_off';
    case CompanyShippingReady = 'company_shipping_ready';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingProcurement => 'Awaiting procurement',
            self::ProcurementInProgress => 'Procurement in progress',
            self::PartiallyReceived => 'Partially received',
            self::Received => 'Received at China warehouse',
            self::QcPending => 'QC pending',
            self::QcFailed => 'QC failed',
            self::QcPassed => 'QC passed',
            self::Consolidating => 'Consolidating',
            self::Consolidated => 'Consolidated',
            self::ExportReady => 'Export ready',
            self::AgentHandedOff => 'Agent handed off',
            self::CompanyShippingReady => 'Company shipping ready',
        };
    }

    /**
     * Workflow-stage convenience only. Authoritative Export Ready is
     * ChinaWorkflowRecord.export_ready_at (owned solely by ChinaWorkflowEngine).
     */
    public function isExportReady(): bool
    {
        return in_array($this, [
            self::ExportReady,
            self::AgentHandedOff,
            self::CompanyShippingReady,
        ], true);
    }
}

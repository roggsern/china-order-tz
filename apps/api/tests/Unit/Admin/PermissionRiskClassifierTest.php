<?php

namespace Tests\Unit\Admin;

use App\Support\Admin\AdminPermissions;
use App\Support\Admin\PermissionRiskClassifier;
use App\Support\Admin\PermissionRiskTier;
use Tests\TestCase;

class PermissionRiskClassifierTest extends TestCase
{
    public function test_classifies_view_permissions_as_low_risk(): void
    {
        $this->assertSame(
            PermissionRiskTier::Low,
            PermissionRiskClassifier::classify(AdminPermissions::ORDERS_VIEW),
        );
    }

    public function test_classifies_admin_and_payment_permissions_as_high_risk(): void
    {
        $this->assertSame(
            PermissionRiskTier::High,
            PermissionRiskClassifier::classify(AdminPermissions::ROLES_MANAGE_PERMISSIONS),
        );
        $this->assertSame(
            PermissionRiskTier::High,
            PermissionRiskClassifier::classify(AdminPermissions::PAYMENTS_REFUND),
        );
    }

    public function test_classifies_operational_mutations_as_medium_risk(): void
    {
        $this->assertSame(
            PermissionRiskTier::Medium,
            PermissionRiskClassifier::classify(AdminPermissions::CATALOG_UPDATE),
        );
    }
}

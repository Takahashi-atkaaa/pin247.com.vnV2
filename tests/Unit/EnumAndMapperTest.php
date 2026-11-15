<?php

namespace Tests\Unit;

use App\Enums\OrderSyncStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductSource;
use App\Enums\SyncState;
use App\Services\BigSeller\BigSellerStatusMapper;
use PHPUnit\Framework\TestCase;

class EnumAndMapperTest extends TestCase
{
    public function test_order_sync_status_transitions(): void
    {
        $this->assertTrue(OrderSyncStatus::PendingSync->canTransitionTo(OrderSyncStatus::Syncing));
        $this->assertTrue(OrderSyncStatus::PendingSync->canTransitionTo(OrderSyncStatus::SyncFailed));
        $this->assertTrue(OrderSyncStatus::SyncFailed->canTransitionTo(OrderSyncStatus::PendingSync));

        // Đơn đã hủy / hoàn tất là trạng thái cuối => không chuyển tiếp được.
        $this->assertTrue(OrderSyncStatus::Cancelled->isTerminal());
        $this->assertTrue(OrderSyncStatus::Completed->isTerminal());
        $this->assertFalse(OrderSyncStatus::Cancelled->canTransitionTo(OrderSyncStatus::Synced));
    }

    public function test_order_requires_push(): void
    {
        $this->assertTrue(OrderSyncStatus::PendingSync->requiresPush());
        $this->assertTrue(OrderSyncStatus::SyncFailed->requiresPush());
        $this->assertFalse(OrderSyncStatus::Synced->requiresPush());
        $this->assertFalse(OrderSyncStatus::Cancelled->requiresPush());
    }

    public function test_status_mapper_maps_known_statuses(): void
    {
        $mapper = new BigSellerStatusMapper;

        $this->assertSame(OrderSyncStatus::PendingSync, $mapper->map('PENDING'));
        $this->assertSame(OrderSyncStatus::Processing, $mapper->map('PENDING_CONFIRM'));
        $this->assertSame(OrderSyncStatus::Processing, $mapper->map('PACKED'));
        $this->assertSame(OrderSyncStatus::Shipped, $mapper->map('SHIPPED'));
        $this->assertSame(OrderSyncStatus::Completed, $mapper->map('COMPLETED'));
        $this->assertSame(OrderSyncStatus::Cancelled, $mapper->map('CANCELLED'));
        $this->assertSame(OrderSyncStatus::SyncFailed, $mapper->map('FAILED'));
    }

    public function test_status_mapper_is_case_insensitive(): void
    {
        $mapper = new BigSellerStatusMapper;

        $this->assertSame(OrderSyncStatus::Shipped, $mapper->map('shipped'));
        $this->assertSame(OrderSyncStatus::Completed, $mapper->map('  completed '));
    }

    public function test_status_mapper_returns_null_for_unknown_status(): void
    {
        $mapper = new BigSellerStatusMapper;

        // Trạng thái lạ KHÔNG được đoán -> trả null để caller giữ nguyên.
        $this->assertNull($mapper->map('SOME_NEW_STATUS'));
        $this->assertNull($mapper->map(null));
        $this->assertNull($mapper->map(''));
    }

    public function test_status_mapper_map_or_keep_preserves_current_state(): void
    {
        $mapper = new BigSellerStatusMapper;

        $this->assertSame(
            OrderSyncStatus::Processing,
            $mapper->mapOrKeep('UNKNOWN_STATUS', OrderSyncStatus::Processing)
        );
    }

    public function test_enum_labels_are_vietnamese(): void
    {
        $this->assertSame('Chờ đồng bộ', OrderSyncStatus::PendingSync->label());
        $this->assertSame('Đã đồng bộ', SyncState::Synced->label());
        $this->assertSame('Đồng bộ từ BigSeller', ProductSource::BigSeller->label());
        $this->assertNotEmpty(PaymentStatus::Paid->label());
    }

    public function test_enum_values_match_database_columns(): void
    {
        $this->assertContains('PENDING_SYNC', OrderSyncStatus::values());
        $this->assertContains('SYNCED', SyncState::values());
        $this->assertContains('MANUAL', ProductSource::values());
        $this->assertContains('BIGSELLER', ProductSource::values());
    }
}

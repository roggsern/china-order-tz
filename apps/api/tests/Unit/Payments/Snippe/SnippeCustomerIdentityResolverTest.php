<?php

namespace Tests\Unit\Payments\Snippe;

use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Payments\Gateways\Snippe\SnippeCustomerIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SnippeCustomerIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_order_customer_account_over_shipping_recipient(): void
    {
        $user = User::factory()->create([
            'first_name' => 'SeppRise',
            'last_name' => 'Joseph',
            'name' => 'SeppRise Joseph',
            'email' => 'sepprise.joseph@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => 'ABC',
            'last_name' => 'COMPANY LTD',
            'email' => 'warehouse@example.com',
        ]);

        $identity = SnippeCustomerIdentityResolver::resolve($order->fresh());

        $this->assertSame('SeppRise', $identity['firstname']);
        $this->assertSame('Joseph', $identity['lastname']);
        $this->assertSame('sepprise.joseph@example.com', $identity['email']);
    }

    public function test_company_recipient_does_not_become_snippe_payer_identity(): void
    {
        $identity = $this->resolveWithRecipient('ABC', 'COMPANY LTD');

        $this->assertSame('SeppRise', $identity['firstname']);
        $this->assertSame('Joseph', $identity['lastname']);
    }

    public function test_warehouse_recipient_does_not_block_snippe_identity(): void
    {
        $identity = $this->resolveWithRecipient('Warehouse', '');

        $this->assertSame('SeppRise', $identity['firstname']);
        $this->assertSame('Joseph', $identity['lastname']);
    }

    public function test_single_token_recipient_does_not_block_snippe_identity(): void
    {
        $identity = $this->resolveWithRecipient('John', '');

        $this->assertSame('SeppRise', $identity['firstname']);
        $this->assertSame('Joseph', $identity['lastname']);
    }

    public function test_uses_user_profile_fields_when_no_shipping_snapshot_exists(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'name' => 'Jane Buyer',
            'email' => 'jane.buyer@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        $identity = SnippeCustomerIdentityResolver::resolve($order->fresh());

        $this->assertSame('Jane', $identity['firstname']);
        $this->assertSame('Buyer', $identity['lastname']);
        $this->assertSame('jane.buyer@example.com', $identity['email']);
    }

    public function test_parses_two_token_user_name_when_profile_names_missing(): void
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'name' => 'John Smith',
            'email' => 'john.smith@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        $identity = SnippeCustomerIdentityResolver::resolve($order->fresh());

        $this->assertSame('John', $identity['firstname']);
        $this->assertSame('Smith', $identity['lastname']);
    }

    public function test_single_token_customer_name_does_not_fabricate_last_name(): void
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'name' => 'Madonna',
            'email' => 'madonna@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => 'Warehouse',
            'last_name' => 'Dock',
            'email' => 'dock@example.com',
        ]);

        $this->expectException(ValidationException::class);

        try {
            SnippeCustomerIdentityResolver::resolve($order->fresh());
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer.lastname', $exception->errors());

            throw $exception;
        }
    }

    public function test_missing_email_fails(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'name' => 'Jane Buyer',
            'email' => '',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->expectException(ValidationException::class);

        try {
            SnippeCustomerIdentityResolver::resolve($order->fresh());
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer.email', $exception->errors());

            throw $exception;
        }
    }

    public function test_malformed_email_fails(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'name' => 'Jane Buyer',
            'email' => 'not-an-email',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->expectException(ValidationException::class);

        try {
            SnippeCustomerIdentityResolver::resolve($order->fresh());
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer.email', $exception->errors());

            throw $exception;
        }
    }

    public function test_whitespace_is_normalized_for_valid_identity(): void
    {
        $user = User::factory()->create([
            'first_name' => '  Jane  ',
            'last_name' => '  Buyer  ',
            'name' => 'Jane Buyer',
            'email' => '  Jane.Buyer@Example.COM  ',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        $identity = SnippeCustomerIdentityResolver::resolve($order->fresh());

        $this->assertSame('Jane', $identity['firstname']);
        $this->assertSame('Buyer', $identity['lastname']);
        $this->assertSame('jane.buyer@example.com', $identity['email']);
    }

    public function test_incomplete_shipping_recipient_does_not_fail_when_customer_identity_is_complete(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Fallback',
            'last_name' => 'Profile',
            'name' => 'Fallback Profile',
            'email' => 'fallback@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => '',
            'last_name' => 'Recipient',
            'email' => 'snapshot@example.com',
        ]);

        $identity = SnippeCustomerIdentityResolver::resolve($order->fresh());

        $this->assertSame('Fallback', $identity['firstname']);
        $this->assertSame('Profile', $identity['lastname']);
        $this->assertSame('fallback@example.com', $identity['email']);
    }

    /**
     * @return array{firstname: string, lastname: string, email: string}
     */
    private function resolveWithRecipient(string $recipientFirst, string $recipientLast): array
    {
        $user = User::factory()->create([
            'first_name' => 'SeppRise',
            'last_name' => 'Joseph',
            'name' => 'SeppRise Joseph',
            'email' => 'sepprise.joseph@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => $recipientFirst,
            'last_name' => $recipientLast,
            'email' => 'recipient@example.com',
        ]);

        return SnippeCustomerIdentityResolver::resolve($order->fresh());
    }
}

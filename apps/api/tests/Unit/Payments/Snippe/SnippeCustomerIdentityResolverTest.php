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

    public function test_prefers_order_shipping_address_snapshot_over_user_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'name' => 'Robert Musa',
            'email' => 'robert.identity@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => 'Snapshot',
            'last_name' => 'Recipient',
            'email' => 'snapshot.recipient@example.com',
        ]);

        $identity = SnippeCustomerIdentityResolver::resolve($order->fresh());

        $this->assertSame('Snapshot', $identity['firstname']);
        $this->assertSame('Recipient', $identity['lastname']);
        $this->assertSame('snapshot.recipient@example.com', $identity['email']);
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

    public function test_single_token_name_does_not_fabricate_last_name(): void
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'name' => 'Madonna',
            'email' => 'madonna@example.com',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

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

    public function test_missing_first_name_on_shipping_snapshot_fails(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Fallback',
            'last_name' => 'Profile',
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

        $this->expectException(ValidationException::class);

        try {
            SnippeCustomerIdentityResolver::resolve($order->fresh());
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer.firstname', $exception->errors());

            throw $exception;
        }
    }
}

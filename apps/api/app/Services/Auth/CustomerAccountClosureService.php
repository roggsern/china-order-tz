<?php

namespace App\Services\Auth;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\CustomerLifecycleStatus;
use App\Enums\CustomerRegistrationSource;
use App\Enums\CustomerTimelineEventType;
use App\Events\Audit\CustomerAccountClosedAudit;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\CustomerProfile;
use App\Models\DeliveryAddress;
use App\Models\EmailChangeRequest;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserProfile;
use App\Models\Wishlist;
use App\Services\Crm\CustomerCodeGenerator;
use App\Services\Crm\CustomerMetricsService;
use App\Services\Crm\CustomerTimelineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative customer account closure (hybrid soft-delete + anonymize).
 *
 * Does not force-delete users or mutate order/payment/refund/shipment rows.
 */
final class CustomerAccountClosureService
{
    public function __construct(
        private readonly CustomerCodeGenerator $codes,
        private readonly CustomerMetricsService $metrics,
        private readonly CustomerTimelineService $timeline,
    ) {}

    /**
     * @param  array{current_password: string, acknowledge: bool}  $payload
     * @return array{success: bool, message: string, requires_reauthentication: bool, already_closed: bool}
     */
    public function close(User $user, array $payload): array
    {
        return DB::transaction(function () use ($user, $payload) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($this->isClosed($locked)) {
                $this->ensureFullyClosed($locked);

                return $this->successPayload(alreadyClosed: true);
            }

            if (! Hash::check((string) $payload['current_password'], (string) $locked->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect.'],
                ]);
            }

            if (! filter_var($payload['acknowledge'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'acknowledge' => ['You must acknowledge that closing your account cannot be undone from this screen.'],
                ]);
            }

            $this->scrubDisposablePersonalData($locked);
            $this->closeCrmProfile($locked);

            $tombstoneEmail = $this->makeTombstoneEmail($locked->id);

            $locked->forceFill([
                'email' => $tombstoneEmail,
                'name' => 'Deleted Customer',
                'first_name' => 'Deleted',
                'last_name' => 'Customer',
                'phone' => null,
                'password' => Str::password(64),
                'is_active' => false,
                'email_verified_at' => null,
            ])->save();

            // Belt-and-suspenders: boot hooks also revoke on is_active / deleting.
            $locked->tokens()->delete();
            $locked->revokeActivePushTokens();

            if (! $locked->trashed()) {
                $locked->delete();
            }

            $fresh = User::withTrashed()->whereKey($locked->id)->first() ?? $locked;
            event(CustomerAccountClosedAudit::forUser($fresh));

            return $this->successPayload(alreadyClosed: false);
        });
    }

    private function isClosed(User $user): bool
    {
        if ($user->trashed()) {
            return true;
        }

        $profile = CustomerProfile::query()->where('user_id', $user->id)->first();

        return $profile?->lifecycle_status === CustomerLifecycleStatus::Closed
            || ($user->is_active === false && str_starts_with((string) $user->email, 'deleted+'));
    }

    private function ensureFullyClosed(User $user): void
    {
        $user->tokens()->delete();
        $user->revokeActivePushTokens();

        if ($user->is_active) {
            $user->forceFill(['is_active' => false])->save();
        }

        if (! $user->trashed()) {
            $user->delete();
        }

        $profile = CustomerProfile::query()->where('user_id', $user->id)->first();
        if ($profile !== null && $profile->lifecycle_status !== CustomerLifecycleStatus::Closed) {
            $profile->update([
                'lifecycle_status' => CustomerLifecycleStatus::Closed,
                'marketing_opt_in' => false,
                'date_of_birth' => null,
                'notes_summary' => null,
                'blocked_at' => $profile->blocked_at ?? now(),
                'block_reason' => $profile->block_reason ?: 'Customer-initiated account closure',
            ]);
        }
    }

    private function scrubDisposablePersonalData(User $user): void
    {
        UserAddress::query()
            ->where('user_id', $user->id)
            ->each(function (UserAddress $address): void {
                $address->forceFill([
                    'label' => 'Removed',
                    'recipient_name' => 'Removed',
                    'phone' => '0000000000',
                    'address_line_1' => 'Removed',
                    'address_line_2' => null,
                    'city' => 'Removed',
                    'region' => null,
                    'postal_code' => null,
                    'country' => 'TZ',
                    'is_default' => false,
                ])->save();
                $address->delete();
            });

        // Address-book style shipping rows only — retain order-linked snapshots for fulfillment history.
        ShippingAddress::query()
            ->where('user_id', $user->id)
            ->whereNull('order_id')
            ->each(function (ShippingAddress $address): void {
                $address->forceFill([
                    'first_name' => 'Removed',
                    'last_name' => 'Customer',
                    'phone' => '0000000000',
                    'email' => null,
                    'address_line_1' => 'Removed',
                    'address_line_2' => null,
                    'city' => 'Removed',
                    'region' => null,
                    'postal_code' => null,
                    'country' => 'TZ',
                    'is_default' => false,
                ])->save();
                $address->delete();
            });

        DeliveryAddress::query()
            ->where('user_id', $user->id)
            ->each(function (DeliveryAddress $address): void {
                $address->forceFill([
                    'recipient_name' => 'Removed',
                    'phone' => '0000000000',
                    'country' => 'TZ',
                    'region' => null,
                    'city' => 'Removed',
                    'district' => null,
                    'street' => 'Removed',
                    'landmark' => null,
                    'postal_code' => null,
                ])->save();
                $address->delete();
            });

        UserProfile::query()
            ->where('user_id', $user->id)
            ->each(function (UserProfile $profile): void {
                $profile->delete();
            });

        Cart::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [CartStatus::Active, CartStatus::CheckoutSession])
            ->each(function (Cart $cart): void {
                $cart->forceFill(['status' => CartStatus::Abandoned])->save();
                $cart->delete();
            });

        Wishlist::query()
            ->where('user_id', $user->id)
            ->each(function (Wishlist $wishlist): void {
                $wishlist->delete();
            });

        CheckoutSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [CheckoutSessionStatus::Draft, CheckoutSessionStatus::Validated])
            ->each(function (CheckoutSession $session): void {
                $session->forceFill(['status' => CheckoutSessionStatus::Expired])->save();
                $session->delete();
            });

        NotificationPreference::query()->where('user_id', $user->id)->delete();

        Notification::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('customer_id', $user->id);
            })
            ->each(function (Notification $notification): void {
                $notification->delete();
            });

        EmailChangeRequest::query()->where('user_id', $user->id)->delete();
    }

    private function closeCrmProfile(User $user): void
    {
        $profile = CustomerProfile::query()->where('user_id', $user->id)->first();

        if ($profile === null) {
            if (! $user->hasRole('customer')) {
                return;
            }

            $profile = CustomerProfile::query()->create([
                'user_id' => $user->id,
                'customer_code' => $this->codes->generate(),
                'registration_source' => CustomerRegistrationSource::SelfRegistration,
                'lifecycle_status' => CustomerLifecycleStatus::Closed,
                'preferred_currency' => 'TZS',
                'marketing_opt_in' => false,
                'date_of_birth' => null,
                'notes_summary' => null,
                'blocked_at' => now(),
                'block_reason' => 'Customer-initiated account closure',
            ]);
            $this->metrics->ensure($profile);
            $this->timeline->append(
                $profile,
                CustomerTimelineEventType::AccountClosed,
                'Customer closed account',
                'Self-service account closure completed',
                User::class,
                $user->id,
                [
                    'previous_status' => null,
                    'to' => CustomerLifecycleStatus::Closed->value,
                ],
            );
        } else {
            $before = $profile->lifecycle_status;
            $profile->update([
                'lifecycle_status' => CustomerLifecycleStatus::Closed,
                'marketing_opt_in' => false,
                'date_of_birth' => null,
                'notes_summary' => null,
                'blocked_at' => now(),
                'block_reason' => 'Customer-initiated account closure',
            ]);
            $profile = $profile->fresh() ?? $profile;

            $this->timeline->append(
                $profile,
                CustomerTimelineEventType::AccountClosed,
                'Customer closed account',
                'Self-service account closure completed',
                User::class,
                $user->id,
                [
                    'previous_status' => $before?->value,
                    'to' => CustomerLifecycleStatus::Closed->value,
                ],
            );
        }
    }

    public function makeTombstoneEmail(string $userId): string
    {
        $token = strtolower(str_replace('-', '', $userId));

        return 'deleted+'.$token.'@invalid.local';
    }

    /**
     * @return array{success: bool, message: string, requires_reauthentication: bool, already_closed: bool}
     */
    private function successPayload(bool $alreadyClosed): array
    {
        return [
            'success' => true,
            'message' => $alreadyClosed
                ? 'This account is already closed.'
                : 'Your account has been closed. You have been signed out.',
            'requires_reauthentication' => true,
            'already_closed' => $alreadyClosed,
        ];
    }
}

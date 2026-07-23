<?php

namespace App\Http\Controllers\Admin;

use App\Events\Audit\CustomerMetricsRebuiltAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCustomerTagRequest;
use App\Http\Requests\Admin\StoreCustomerNoteRequest;
use App\Http\Requests\Admin\UpdateCustomerNoteRequest;
use App\Http\Requests\Admin\UpdateCustomerStatusRequest;
use App\Http\Resources\CustomerNoteResource;
use App\Http\Resources\CustomerProfileResource;
use App\Http\Resources\CustomerTimelineEventResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentTransactionResource;
use App\Http\Resources\RefundTransactionResource;
use App\Http\Resources\ReturnRequestResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Admin;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\ShippingAddress;
use App\Models\UserAddress;
use App\Services\Crm\CustomerMetricsService;
use App\Services\Crm\CustomerNoteService;
use App\Services\Crm\CustomerProfileService;
use App\Services\Crm\CustomerSegmentationService;
use App\Services\Crm\CustomerStatusService;
use App\Services\Crm\CustomerTimelineService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCustomerController extends Controller
{
    public function __construct(
        private readonly CustomerProfileService $profiles,
        private readonly CustomerStatusService $statuses,
        private readonly CustomerSegmentationService $segmentation,
        private readonly CustomerNoteService $notes,
        private readonly CustomerTimelineService $timeline,
        private readonly CustomerMetricsService $metrics,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return CustomerProfileResource::collection(
            $this->profiles->paginate($request->query(), $perPage),
        )->additional(['success' => true]);
    }

    public function summary(): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->profiles->summary(),
        ]);
    }

    public function show(CustomerProfile $customer): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new CustomerProfileResource($this->profiles->show($customer)),
        ]);
    }

    public function updateStatus(
        CustomerProfile $customer,
        UpdateCustomerStatusRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $updated = $this->statuses->updateStatus(
            $customer,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer status updated.',
            'data' => new CustomerProfileResource($updated),
        ]);
    }

    public function rebuildMetrics(CustomerProfile $customer): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_UPDATE);

        $admin = auth('sanctum')->user();
        $metric = $this->metrics->recalculate($customer);
        event(CustomerMetricsRebuiltAudit::fromMetric(
            $metric,
            $admin instanceof Admin ? $admin : null,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Customer metrics rebuilt.',
            'data' => new CustomerProfileResource($this->profiles->show($customer->fresh() ?? $customer)),
        ]);
    }

    public function orders(CustomerProfile $customer, Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $orders = Order::query()
            ->real()
            ->where('user_id', $customer->user_id)
            ->latest('created_at')
            ->paginate($perPage);

        return OrderResource::collection($orders)->additional(['success' => true]);
    }

    public function payments(CustomerProfile $customer, Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $legacy = Payment::query()
            ->where('user_id', $customer->user_id)
            ->latest('created_at')
            ->limit(100)
            ->get();

        $transactions = PaymentTransaction::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $customer->user_id)->where('is_demo', false))
            ->with('order:id,order_number,status')
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => PaymentTransactionResource::collection($transactions)->resolve(),
                'legacy_payments' => $legacy->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'order_id' => $p->order_id,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
                    'method' => $p->method instanceof \BackedEnum ? $p->method->value : $p->method,
                    'paid_at' => $p->paid_at,
                    'created_at' => $p->created_at,
                ]),
            ],
            'meta' => [
                'transactions' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }

    public function shipments(CustomerProfile $customer, Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $shipments = Shipment::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $customer->user_id)->where('is_demo', false))
            ->with('order:id,order_number,status')
            ->latest('created_at')
            ->paginate($perPage);

        return ShipmentResource::collection($shipments)->additional(['success' => true]);
    }

    public function returns(CustomerProfile $customer, Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $returns = ReturnRequest::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->user_id)
                    ->orWhereHas('order', fn ($oq) => $oq->where('user_id', $customer->user_id));
            })
            ->with('order:id,order_number,status')
            ->latest('created_at')
            ->paginate($perPage);

        $refunds = RefundTransaction::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $customer->user_id)->where('is_demo', false))
            ->with('order:id,order_number,status')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'returns' => ReturnRequestResource::collection($returns)->resolve(),
                'refunds' => RefundTransactionResource::collection($refunds)->resolve(),
            ],
            'meta' => [
                'returns' => [
                    'current_page' => $returns->currentPage(),
                    'last_page' => $returns->lastPage(),
                    'total' => $returns->total(),
                ],
            ],
        ]);
    }

    public function addresses(CustomerProfile $customer): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $userId = $customer->user_id;

        return response()->json([
            'success' => true,
            'data' => [
                'user_addresses' => UserAddress::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('is_default')
                    ->get()
                    ->map(fn (UserAddress $a) => [
                        'id' => $a->id,
                        'type' => 'user_address',
                        'label' => $a->label,
                        'recipient_name' => $a->recipient_name,
                        'phone' => $a->phone,
                        'address_line_1' => $a->address_line_1,
                        'address_line_2' => $a->address_line_2,
                        'city' => $a->city,
                        'region' => $a->region,
                        'postal_code' => $a->postal_code,
                        'country' => $a->country,
                        'is_default' => $a->is_default,
                    ]),
                'delivery_address' => DeliveryAddress::query()
                    ->where('user_id', $userId)
                    ->first()
                    ?->only([
                        'id', 'recipient_name', 'phone', 'country', 'region', 'city',
                        'district', 'street', 'landmark', 'postal_code',
                    ]),
                'shipping_addresses' => ShippingAddress::query()
                    ->where('user_id', $userId)
                    ->whereNull('order_id')
                    ->latest()
                    ->get()
                    ->map(fn (ShippingAddress $a) => [
                        'id' => $a->id,
                        'type' => 'shipping_address',
                        'recipient_name' => $a->fullName(),
                        'phone' => $a->phone,
                        'address_line_1' => $a->address_line_1,
                        'city' => $a->city,
                        'region' => $a->region,
                        'country' => $a->country,
                        'is_default' => (bool) $a->is_default,
                    ]),
            ],
        ]);
    }

    public function timeline(CustomerProfile $customer, Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return CustomerTimelineEventResource::collection(
            $this->timeline->paginate($customer, $perPage),
        )->additional(['success' => true]);
    }

    public function assignTag(
        CustomerProfile $customer,
        AssignCustomerTagRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $tag = CustomerTag::query()->findOrFail($request->validated('tag_id'));
        $updated = $this->segmentation->assignTag(
            $customer,
            $tag,
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Tag assigned.',
            'data' => new CustomerProfileResource($updated),
        ]);
    }

    public function removeTag(CustomerProfile $customer, CustomerTag $tag): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_MANAGE_TAGS);

        $admin = auth('sanctum')->user();
        $updated = $this->segmentation->removeTag(
            $customer,
            $tag,
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Tag removed.',
            'data' => new CustomerProfileResource($updated),
        ]);
    }

    public function storeNote(
        CustomerProfile $customer,
        StoreCustomerNoteRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $note = $this->notes->create(
            $customer,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Note added.',
            'data' => new CustomerNoteResource($note),
        ], 201);
    }

    public function updateNote(
        CustomerProfile $customer,
        CustomerNote $note,
        UpdateCustomerNoteRequest $request,
    ): JsonResponse {
        $this->assertNoteBelongs($customer, $note);
        $admin = auth('sanctum')->user();
        $updated = $this->notes->update(
            $note,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Note updated.',
            'data' => new CustomerNoteResource($updated),
        ]);
    }

    public function destroyNote(CustomerProfile $customer, CustomerNote $note): JsonResponse
    {
        $this->authorize(AdminPermissions::CUSTOMERS_MANAGE_NOTES);

        $this->assertNoteBelongs($customer, $note);
        $admin = auth('sanctum')->user();
        $this->notes->delete($note, $admin instanceof Admin ? $admin : null);

        return response()->json([
            'success' => true,
            'message' => 'Note deleted.',
        ]);
    }

    public function notes(CustomerProfile $customer, Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CUSTOMERS_VIEW);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return CustomerNoteResource::collection(
            $this->notes->paginate($customer, $perPage),
        )->additional(['success' => true]);
    }

    private function assertNoteBelongs(CustomerProfile $customer, CustomerNote $note): void
    {
        abort_unless($note->customer_profile_id === $customer->id, 404);
    }
}

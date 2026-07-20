<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PosReceiptLayout;
use App\Http\Controllers\Controller;
use App\Http\Resources\PosReceiptResource;
use App\Http\Resources\PosSessionResource;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PaymentMethodDefinition;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\Store;
use App\Models\User;
use App\Services\Pos\PosCatalogService;
use App\Services\Pos\PosReceiptService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSessionService;
use App\Services\Stores\ActiveStoreContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminPosController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActiveStoreContext $storeContext,
        private readonly PosSessionService $sessions,
        private readonly PosSaleService $sales,
        private readonly PosCatalogService $catalog,
        private readonly PosReceiptService $receipts,
    ) {}

    public function myStores(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->storeContext->assignedStores($admin)->load(['terminals', 'defaultInventoryLocation']),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('viewAny', PosSession::class);

        $dash = $this->sessions->cashierDashboard($admin);

        return response()->json([
            'success' => true,
            'data' => [
                'session' => $dash['session']
                    ? new PosSessionResource($dash['session'], $dash['summary'])
                    : null,
                'summary' => $dash['summary'],
            ],
        ]);
    }

    public function listSessions(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('viewAny', PosSession::class);

        $filters = $request->validate([
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'status' => ['nullable', 'string', 'in:open,closed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->sessions->listForManager($admin, $filters);

        $data = $paginator->getCollection()->map(function (PosSession $session) {
            $summary = $this->sessions->summaryPayload($session);

            return (new PosSessionResource($session, $summary))->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showSession(Request $request, PosSession $session): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('view', $session);

        $session = $this->sessions->show($admin, $session);
        $summary = $this->sessions->summaryPayload($session);

        return response()->json([
            'success' => true,
            'data' => new PosSessionResource($session, $summary),
        ]);
    }

    public function openSession(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('open', PosSession::class);

        $data = $request->validate([
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'terminal_id' => ['required', 'uuid', 'exists:pos_terminals,id'],
            'opening_float' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $store = $this->storeContext->resolveActiveStore($admin, $data['store_id'] ?? null);
        $terminal = PosTerminal::query()->findOrFail($data['terminal_id']);

        $session = $this->sessions->open(
            $admin,
            $store,
            $terminal,
            (float) $data['opening_float'],
            $data['notes'] ?? null,
        );

        $summary = $this->sessions->summaryPayload($session);

        return response()->json([
            'success' => true,
            'data' => new PosSessionResource($session, $summary),
        ], 201);
    }

    public function currentSession(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $session = $this->sessions->currentOpen($admin);
        $summary = $session ? $this->sessions->summaryPayload($session) : null;

        return response()->json([
            'success' => true,
            'data' => $session ? new PosSessionResource($session, $summary) : null,
        ]);
    }

    public function closeSession(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $session = $this->sessions->currentOpen($admin);
        abort_if($session === null, 422, 'No open POS session.');
        $this->authorize('close', $session);

        $data = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'in:customer_change_mistake,cash_counting_error,other'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $this->sessions->close($session, $admin, $data);
        $summary = $this->sessions->summaryPayload($session);

        return response()->json([
            'success' => true,
            'data' => new PosSessionResource($session, $summary),
        ]);
    }

    public function updateFloat(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $session = $this->sessions->currentOpen($admin);
        abort_if($session === null, 422, 'No open POS session.');
        $this->authorize('updateFloat', $session);

        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $this->sessions->updateFloat(
            $session,
            $admin,
            (float) $data['opening_float'],
            $data['notes'] ?? null,
        );
        $summary = $this->sessions->summaryPayload($session);

        return response()->json([
            'success' => true,
            'data' => new PosSessionResource($session, $summary),
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $session = $this->sessions->currentOpen($admin);
        abort_if($session === null, 422, 'Open a POS session first.');

        $store = Store::query()->findOrFail($session->store_id);
        $this->storeContext->assertCanAccess($admin, $store);

        $perPage = min(50, max(1, (int) $request->query('per_page', 24)));
        $results = $this->catalog->search($store, $request->query('q'), $perPage);

        return response()->json([
            'success' => true,
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
                'store_id' => $store->id,
                'store_name' => $store->name,
                'query' => $request->query('q'),
            ],
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $session = $this->sessions->currentOpen($admin);
        abort_if($session === null, 422, 'Open a POS session first.');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'promotion_code' => ['nullable', 'string', 'max:64'],
            'promotion_id' => ['nullable', 'uuid', 'exists:promotions,id'],
        ]);

        $customer = isset($data['customer_id']) ? User::query()->find($data['customer_id']) : null;
        $quote = $this->sales->quote(
            $admin,
            $session,
            $data['items'],
            $customer,
            $data['promotion_code'] ?? null,
            $data['promotion_id'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $quote]);
    }

    public function paymentMethods(): JsonResponse
    {
        $methods = PaymentMethodDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (PaymentMethodDefinition $m) => ($m->config['pos_enabled'] ?? true) === true)
            ->values();

        return response()->json(['success' => true, 'data' => $methods]);
    }

    public function completeSale(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $session = $this->sessions->currentOpen($admin);
        if ($session === null) {
            \App\Support\Pos\PosErrors::sessionRequired();
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'manual_confirmed' => ['sometimes', 'boolean'],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'promotion_code' => ['nullable', 'string', 'max:64'],
            'promotion_id' => ['nullable', 'uuid', 'exists:promotions,id'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $customer = isset($data['customer_id']) ? User::query()->find($data['customer_id']) : null;

        $result = $this->sales->complete(
            $admin,
            $session,
            $data['items'],
            $data['payment_method'],
            isset($data['amount_received']) ? (float) $data['amount_received'] : null,
            (bool) ($data['manual_confirmed'] ?? false),
            $customer,
            $data['promotion_code'] ?? null,
            $data['promotion_id'] ?? null,
            $data['idempotency_key'] ?? null,
        );

        $status = ! empty($result['idempotent_replay']) ? 200 : 201;

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $result['order'],
                'receipt' => new PosReceiptResource($result['receipt']->load(['store', 'session.terminal', 'order'])),
                'payment' => $result['payment'],
                'change' => $result['change'],
                'quote' => $result['quote'],
                'idempotent_replay' => (bool) ($result['idempotent_replay'] ?? false),
                'session_summary' => $this->sessions->summaryPayload($session->fresh()),
            ],
        ], $status);
    }

    public function showSale(Request $request, Order $order): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $sale = $this->sales->show($admin, $order);

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    public function listReceipts(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('viewAny', PosReceipt::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'receipt_number' => ['nullable', 'string', 'max:64'],
            'order_number' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:120'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'cashier_id' => ['nullable', 'uuid', 'exists:admins,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->receipts->search($admin, $filters);

        return response()->json([
            'success' => true,
            'data' => PosReceiptResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showReceipt(Request $request, PosReceipt $receipt): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('view', $receipt);
        $row = $this->receipts->show($admin, $receipt);

        return response()->json([
            'success' => true,
            'data' => new PosReceiptResource($row),
        ]);
    }

    public function previewReceipt(Request $request, PosReceipt $receipt): Response
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('view', $receipt);

        $layout = PosReceiptLayout::tryFrom((string) $request->query('layout', PosReceiptLayout::Thermal80->value))
            ?? PosReceiptLayout::Thermal80;

        $html = $this->receipts->previewHtml($admin, $receipt, $layout);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function printReceipt(Request $request, PosReceipt $receipt): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('print', $receipt);

        $data = $request->validate([
            'layout' => ['nullable', 'string', 'in:thermal_80,a4,pdf'],
        ]);
        $layout = PosReceiptLayout::tryFrom($data['layout'] ?? PosReceiptLayout::Thermal80->value)
            ?? PosReceiptLayout::Thermal80;

        $result = $this->receipts->print($admin, $receipt, $layout);

        return response()->json([
            'success' => true,
            'data' => [
                'receipt' => new PosReceiptResource($result['receipt']),
                'html' => $result['html'],
                'layout' => $result['layout'],
            ],
        ]);
    }

    public function reprintReceipt(Request $request, PosReceipt $receipt): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('reprint', $receipt);

        $data = $request->validate([
            'layout' => ['nullable', 'string', 'in:thermal_80,a4,pdf'],
        ]);
        $layout = PosReceiptLayout::tryFrom($data['layout'] ?? PosReceiptLayout::Thermal80->value)
            ?? PosReceiptLayout::Thermal80;

        $result = $this->receipts->reprint($admin, $receipt, $layout);

        return response()->json([
            'success' => true,
            'data' => [
                'receipt' => new PosReceiptResource($result['receipt']),
                'html' => $result['html'],
                'layout' => $result['layout'],
            ],
        ]);
    }

    public function downloadReceiptPdf(Request $request, PosReceipt $receipt): Response
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->authorize('view', $receipt);

        $result = $this->receipts->pdf($admin, $receipt);

        return response($result['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }

    public function orderReceipt(Request $request, Order $order): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $receipt = $this->receipts->forOrder($admin, $order);
        $this->authorize('view', $receipt);

        return response()->json([
            'success' => true,
            'data' => new PosReceiptResource($receipt),
        ]);
    }
}

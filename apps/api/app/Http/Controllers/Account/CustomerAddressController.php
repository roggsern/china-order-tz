<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreCustomerAddressRequest;
use App\Http\Requests\Account\UpdateCustomerAddressRequest;
use App\Http\Resources\UserAddressResource;
use App\Models\User;
use App\Services\Profile\CustomerAddressService;
use Illuminate\Http\JsonResponse;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly CustomerAddressService $addresses,
    ) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => UserAddressResource::collection($this->addresses->listFor($user)),
            'meta' => [
                'default_id' => $this->addresses->defaultFor($user)?->id,
            ],
        ]);
    }

    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $address = $this->addresses->create($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'data' => new UserAddressResource($address),
        ], 201);
    }

    public function update(UpdateCustomerAddressRequest $request, string $address): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $owned = $this->addresses->findOwned($user, $address);
        $updated = $this->addresses->update($user, $owned, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => new UserAddressResource($updated),
        ]);
    }

    public function destroy(string $address): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $owned = $this->addresses->findOwned($user, $address);
        $this->addresses->delete($user, $owned);

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

    public function setDefault(string $address): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $owned = $this->addresses->findOwned($user, $address);
        $updated = $this->addresses->setDefault($user, $owned);

        return response()->json([
            'success' => true,
            'message' => 'Default address updated.',
            'data' => new UserAddressResource($updated),
        ]);
    }
}

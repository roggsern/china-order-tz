<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\AdminProducts\ProductDeletionLifecycle;
use App\Services\AdminProducts\ProductForceDeleteEligibilityService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ForceDeleteProductAction
{
    public function __construct(
        private readonly ProductDeletionLifecycle $lifecycle,
        private readonly ProductForceDeleteEligibilityService $eligibility,
    ) {}

    /**
     * @return array{
     *     product_id: string,
     *     slug: string,
     *     name: string,
     *     media_cleanup: array{deleted_files: int, missing_files: int, shared_files_skipped: int, file_errors: list<string>}
     * }
     */
    public function handle(string $id, ?string $confirmation = null, ?string $actorAdminId = null): array
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $evaluation = $this->eligibility->evaluate($product);

        if (! $evaluation['can_force_delete']) {
            Log::info('product_force_delete_blocked', [
                'actor_admin_id' => $actorAdminId,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'blocking_dependencies' => $evaluation['blocking_dependencies'],
            ]);

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'This product cannot be permanently deleted because protected dependencies remain.',
                'error_code' => 'product_force_delete_blocked',
                'data' => [
                    'can_force_delete' => false,
                    'blocking_dependencies' => $evaluation['blocking_dependencies'],
                    'deletable_dependencies' => $evaluation['deletable_dependencies'],
                    'confirmation_phrase' => $evaluation['confirmation_phrase'],
                ],
            ], 422));
        }

        $expected = $evaluation['confirmation_phrase'];
        if ($confirmation === null || ! hash_equals($expected, $confirmation)) {
            throw ValidationException::withMessages([
                'confirmation' => [
                    'Type the exact confirmation phrase to permanently delete this product: '.$expected,
                ],
            ]);
        }

        Log::info('product_force_delete_requested', [
            'actor_admin_id' => $actorAdminId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'deletable_dependencies' => $evaluation['deletable_dependencies'],
        ]);

        $cleanup = $this->lifecycle->forceDelete($product);

        Log::info('product_force_delete_completed', [
            'actor_admin_id' => $actorAdminId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'media_cleanup' => $cleanup,
        ]);

        if ($cleanup['file_errors'] !== []) {
            Log::warning('product_force_delete_media_cleanup_incomplete', [
                'actor_admin_id' => $actorAdminId,
                'product_id' => $product->id,
                'file_errors' => $cleanup['file_errors'],
            ]);
        }

        return [
            'product_id' => $product->id,
            'slug' => (string) $product->slug,
            'name' => (string) $product->name,
            'media_cleanup' => $cleanup,
        ];
    }
}

<?php

namespace App\Services\ProductMedia;

use App\Models\ProductMedia;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotent backfill of storefront display derivatives for existing product_media images.
 */
final class StorefrontImageDerivativeBackfillService
{
    public function __construct(
        private readonly StorefrontImageDerivativeService $derivatives,
    ) {}

    /**
     * @param  array{
     *     dry_run?: bool,
     *     product_id?: string|null,
     *     limit?: int|null,
     * }  $options
     * @return array{
     *     processed: int,
     *     generated: int,
     *     linked_existing: int,
     *     skipped: int,
     *     failed: int,
     *     dry_run: bool,
     *     rows: list<array{media_id: string, product_id: string, action: string, detail: string}>
     * }
     */
    public function backfill(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $productId = filled($options['product_id'] ?? null) ? (string) $options['product_id'] : null;
        $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;

        $query = ProductMedia::query()
            ->images()
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $processed = 0;
        $generated = 0;
        $linkedExisting = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];

        /** @var ProductMedia $media */
        foreach ($query->cursor() as $media) {
            $processed++;
            $originalPath = $this->derivatives->resolvePublicRelativePathFromUrl($media->url);

            if ($originalPath === null || ! str_starts_with($originalPath, 'products/')) {
                $skipped++;
                $rows[] = [
                    'media_id' => $media->id,
                    'product_id' => $media->product_id,
                    'action' => 'skipped',
                    'detail' => 'non-local or unresolvable url',
                ];

                continue;
            }

            $derivativePath = $this->derivatives->derivativeRelativePath($originalPath);
            $derivativeUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($derivativePath);
            $hasFile = $this->derivatives->derivativeExistsForOriginal($originalPath);
            $hasDisplayUrl = filled($media->display_url);

            if ($hasFile && $hasDisplayUrl && (string) $media->display_url === $derivativeUrl) {
                $skipped++;
                $rows[] = [
                    'media_id' => $media->id,
                    'product_id' => $media->product_id,
                    'action' => 'skipped',
                    'detail' => 'derivative already linked',
                ];

                continue;
            }

            if ($hasFile && (! $hasDisplayUrl || (string) $media->display_url !== $derivativeUrl)) {
                if (! $dryRun) {
                    $media->forceFill(['display_url' => $derivativeUrl])->save();
                }
                $linkedExisting++;
                $rows[] = [
                    'media_id' => $media->id,
                    'product_id' => $media->product_id,
                    'action' => $dryRun ? 'would_link' : 'linked',
                    'detail' => $derivativePath,
                ];

                continue;
            }

            if ($dryRun) {
                $generated++;
                $rows[] = [
                    'media_id' => $media->id,
                    'product_id' => $media->product_id,
                    'action' => 'would_generate',
                    'detail' => $derivativePath,
                ];

                continue;
            }

            try {
                $result = $this->derivatives->generateFromPublicPath($originalPath);
                if ($result === null) {
                    $failed++;
                    $rows[] = [
                        'media_id' => $media->id,
                        'product_id' => $media->product_id,
                        'action' => 'failed',
                        'detail' => 'derivative generation returned null',
                    ];

                    continue;
                }

                $media->forceFill(['display_url' => $result['url']])->save();
                $generated++;
                $rows[] = [
                    'media_id' => $media->id,
                    'product_id' => $media->product_id,
                    'action' => 'generated',
                    'detail' => $result['path'],
                ];
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('storefront_image_derivative.backfill_failed', [
                    'media_id' => $media->id,
                    'message' => $exception->getMessage(),
                ]);
                $rows[] = [
                    'media_id' => $media->id,
                    'product_id' => $media->product_id,
                    'action' => 'failed',
                    'detail' => $exception->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'generated' => $generated,
            'linked_existing' => $linkedExisting,
            'skipped' => $skipped,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'rows' => $rows,
        ];
    }
}

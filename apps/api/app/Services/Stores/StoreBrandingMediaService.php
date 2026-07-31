<?php

namespace App\Services\Stores;

use App\Events\Audit\StoreBrandingUpdatedAudit;
use App\Models\Admin;
use App\Models\Store;
use App\Support\Security\SecureImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Admin-managed store logo/banner uploads. Paths are stored on the Store entity;
 * secrets are never accepted.
 */
final class StoreBrandingMediaService
{
    /**
     * @param  array{logo?: UploadedFile|null, banner?: UploadedFile|null}  $files
     */
    public function upload(Store $store, array $files, ?Admin $actor = null): Store
    {
        $logo = $files['logo'] ?? null;
        $banner = $files['banner'] ?? null;

        if (! $logo instanceof UploadedFile && ! $banner instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'logo' => ['Upload at least one branding image (logo or banner).'],
            ]);
        }

        $before = [
            'logo_path' => $store->logo_path,
            'banner_path' => $store->banner_path,
            'logo_url' => $store->logoUrl(),
            'banner_url' => $store->bannerUrl(),
        ];

        $directory = 'stores/'.$store->id;
        $updates = [];
        $oldPaths = [];

        if ($logo instanceof UploadedFile) {
            $oldPaths[] = $store->logo_path;
            $updates['logo_path'] = SecureImageUpload::storePublic($logo, $directory);
        }

        if ($banner instanceof UploadedFile) {
            $oldPaths[] = $store->banner_path;
            $updates['banner_path'] = SecureImageUpload::storePublic($banner, $directory);
        }

        $store->fill($updates)->save();
        $store = $store->fresh() ?? $store;

        foreach ($oldPaths as $oldPath) {
            $this->deleteManagedPath($oldPath);
        }

        $after = [
            'logo_path' => $store->logo_path,
            'banner_path' => $store->banner_path,
            'logo_url' => $store->logoUrl(),
            'banner_url' => $store->bannerUrl(),
        ];

        event(StoreBrandingUpdatedAudit::fromChange($store, $before, $after, $actor));

        return $store;
    }

    private function deleteManagedPath(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $normalized = ltrim((string) $path, '/');
        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return;
        }

        if (! str_starts_with($normalized, 'stores/')) {
            return;
        }

        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsFeaturedContent;
use App\Models\Product;
use App\Models\Store;
use App\Services\CMS\CmsFeaturedContentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CmsFeaturedContent */
class CmsFeaturedContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStorefront = $request->is('api/v1/storefront/*');

        $payload = [
            'id' => $this->id,
            'cms_homepage_section_id' => $this->cms_homepage_section_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'source_type' => $this->source_type instanceof \BackedEnum
                ? $this->source_type->value
                : $this->source_type,
            'limit' => (int) $this->limit,
            'sort_order' => $this->sort_order,
            'display_style' => $this->display_style instanceof \BackedEnum
                ? $this->display_style->value
                : $this->display_style,
            'configuration' => $this->when(! $isStorefront, $this->configuration ?? []),
            'position' => (int) $this->position,
            'status' => $this->when(
                ! $isStorefront,
                $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            ),
            'is_visible' => $this->when(! $isStorefront, (bool) $this->is_visible),
            'created_by' => $this->when(! $isStorefront, $this->created_by),
            'created_at' => $this->when(! $isStorefront, $this->created_at),
            'updated_at' => $this->when(! $isStorefront, $this->updated_at),
        ];

        if ($isStorefront || $request->boolean('resolve_items')) {
            $payload['items'] = $this->resolvedItems();
        }

        return $payload;
    }

    /**
     * @return list<array{item_type: string, id: string, data: array<string, mixed>}>
     */
    private function resolvedItems(): array
    {
        /** @var CmsFeaturedContentService $service */
        $service = app(CmsFeaturedContentService::class);
        $resolved = $service->resolveItems($this->resource);

        return array_map(function (array $row) {
            $entity = $row['entity'];
            $data = match (true) {
                $entity instanceof Product => (new CustomerProductCardResource($entity))->resolve(),
                $entity instanceof Store => [
                    'id' => $entity->id,
                    'code' => $entity->code,
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'theme_color' => $entity->theme_color,
                    'is_active' => $entity->is_active,
                ],
                $entity instanceof Brand => (new BrandResource($entity))->resolve(),
                $entity instanceof Category => (new CategoryResource($entity))->resolve(),
                default => ['id' => $row['id']],
            };

            return [
                'item_type' => $row['item_type'],
                'id' => $row['id'],
                'data' => $data,
            ];
        }, $resolved);
    }
}

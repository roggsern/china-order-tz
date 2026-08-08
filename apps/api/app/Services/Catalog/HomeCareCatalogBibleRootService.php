<?php

namespace App\Services\Catalog;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use Database\Support\CatalogBible;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ensures the China CatalogBible Home Care root category exists and parents
 * the existing Home Care department category leaves under it.
 *
 * Does not create or mutate the Home Care Department row.
 */
class HomeCareCatalogBibleRootService
{
    public const ROOT_SLUG = 'home-care';

    /**
     * @var list<string>
     */
    public const CHILD_SLUGS = [
        'pest-control',
        'cleaning-hygiene',
        'household-essentials',
        'smart-home-care',
    ];

    /**
     * @return array{
     *     dry_run: bool,
     *     steps: list<string>,
     *     root_id: string|null,
     *     root_created: bool,
     *     reparented_slugs: list<string>,
     *     already_attached_slugs: list<string>,
     *     missing_child_slugs: list<string>
     * }
     */
    public function ensure(bool $dryRun = true): array
    {
        if ($dryRun) {
            return $this->buildPlan(dryRun: true);
        }

        return DB::transaction(fn () => $this->buildPlan(dryRun: false));
    }

    /**
     * Create/normalize only the CatalogBible root (no child re-parenting).
     *
     * @return array{dry_run: bool, steps: list<string>, root_id: string|null, root_created: bool}
     */
    public function ensureRoot(bool $dryRun = true): array
    {
        $plan = $this->ensureRootPlan($dryRun);

        return [
            'dry_run' => $dryRun,
            'steps' => $plan['steps'],
            'root_id' => $plan['root']?->id,
            'root_created' => $plan['root_created'],
        ];
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     steps: list<string>,
     *     root_id: string|null,
     *     root_created: bool,
     *     reparented_slugs: list<string>,
     *     already_attached_slugs: list<string>,
     *     missing_child_slugs: list<string>
     * }
     */
    private function buildPlan(bool $dryRun): array
    {
        $rootPlan = $this->ensureRootPlan($dryRun);
        $root = $rootPlan['root'];
        $steps = $rootPlan['steps'];
        $reparentedSlugs = [];
        $alreadyAttachedSlugs = [];
        $missingChildSlugs = [];
        $rootId = $root?->id;

        foreach (self::CHILD_SLUGS as $index => $childSlug) {
            $child = Category::query()
                ->where('slug', $childSlug)
                ->whereNull('store_id')
                ->where('origin', CatalogOrigin::China)
                ->first();

            if ($child === null) {
                $steps[] = "Child category {$childSlug} not found (left unchanged)";
                $missingChildSlugs[] = $childSlug;

                continue;
            }

            if ($rootId !== null && $child->parent_id === $rootId) {
                $steps[] = "Child category {$childSlug} already under Home Care root";
                $alreadyAttachedSlugs[] = $childSlug;

                if (! $dryRun) {
                    $child->update([
                        'store_id' => null,
                        'origin' => CatalogOrigin::China,
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                    ]);
                }

                continue;
            }

            $steps[] = "Re-parent category {$childSlug} under CatalogBible root Home Care";
            $reparentedSlugs[] = $childSlug;

            if (! $dryRun && $rootId !== null) {
                $child->update([
                    'parent_id' => $rootId,
                    'store_id' => null,
                    'origin' => CatalogOrigin::China,
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        }

        return [
            'dry_run' => $dryRun,
            'steps' => $steps,
            'root_id' => $rootId,
            'root_created' => $rootPlan['root_created'],
            'reparented_slugs' => $reparentedSlugs,
            'already_attached_slugs' => $alreadyAttachedSlugs,
            'missing_child_slugs' => $missingChildSlugs,
        ];
    }

    /**
     * @return array{steps: list<string>, root: ?Category, root_created: bool}
     */
    private function ensureRootPlan(bool $dryRun): array
    {
        $definition = collect(CatalogBible::categories())->firstWhere('slug', self::ROOT_SLUG);

        if ($definition === null) {
            throw new RuntimeException(
                'CatalogBible is missing the home-care navigation root.',
            );
        }

        $steps = [];
        $rootCreated = false;

        $root = Category::withTrashed()
            ->where('slug', self::ROOT_SLUG)
            ->whereNull('store_id')
            ->where('origin', CatalogOrigin::China)
            ->first();

        if ($root === null) {
            $steps[] = 'Create CatalogBible root category Home Care (department_id=null)';
            $rootCreated = true;

            if (! $dryRun) {
                $root = Category::query()->create([
                    'name' => $definition['name'],
                    'slug' => self::ROOT_SLUG,
                    'origin' => CatalogOrigin::China,
                    'department_id' => null,
                    'store_id' => null,
                    'parent_id' => null,
                    'description' => null,
                    'image' => null,
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]);
            }
        } else {
            if ($root->trashed()) {
                $steps[] = 'Restore soft-deleted CatalogBible root category Home Care';
                if (! $dryRun) {
                    $root->restore();
                }
            } else {
                $steps[] = 'CatalogBible root category Home Care already present';
            }

            $steps[] = 'Normalize CatalogBible root category Home Care (department_id=null, parent_id=null)';
            if (! $dryRun) {
                $root->refresh();
                $root->update([
                    'name' => $definition['name'],
                    'origin' => CatalogOrigin::China,
                    'department_id' => null,
                    'store_id' => null,
                    'parent_id' => null,
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]);
            }
        }

        return [
            'steps' => $steps,
            'root' => $root,
            'root_created' => $rootCreated,
        ];
    }
}

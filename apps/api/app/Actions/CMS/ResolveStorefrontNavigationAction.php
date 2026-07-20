<?php

namespace App\Actions\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Services\CMS\CmsNavigationResolver;

class ResolveStorefrontNavigationAction
{
    public function __construct(private readonly CmsNavigationResolver $resolver) {}

    /**
     * @param  'guest'|'authenticated'|'admin_preview'  $audience
     * @return array<string, mixed>
     */
    public function handle(
        CmsCommerceContext $context,
        ?CmsNavigationType $type = null,
        string $audience = 'guest',
        bool $hydrateMegaMenus = true,
    ): array {
        if ($type === null) {
            return $this->resolver->resolveAll($context, $audience, $hydrateMegaMenus);
        }

        return $this->resolver->resolve($context, $type, $audience, $hydrateMegaMenus);
    }
}

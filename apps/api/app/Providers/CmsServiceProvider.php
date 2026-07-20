<?php

namespace App\Providers;

use App\Events\Audit\CmsPlatformAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Models\CmsCampaign;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\CmsNavigationShell;
use App\Policies\CmsCampaignPolicy;
use App\Policies\CmsFeaturedContentPolicy;
use App\Policies\CmsHeroSlidePolicy;
use App\Policies\CmsHomepageLayoutPolicy;
use App\Policies\CmsHomepageSectionPolicy;
use App\Policies\CmsNavigationShellPolicy;
use App\Services\CMS\CmsCampaignService;
use App\Services\CMS\CmsCtaTargetValidationService;
use App\Services\CMS\CmsFeaturedContentResolver;
use App\Services\CMS\CmsFeaturedContentService;
use App\Services\CMS\CmsFeaturedContentValidationService;
use App\Services\CMS\CmsHeroSlideService;
use App\Services\CMS\CmsHomepageService;
use App\Services\CMS\CmsNavigationItemValidationService;
use App\Services\CMS\CmsNavigationResolver;
use App\Services\CMS\CmsNavigationShellService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CmsCampaignService::class);
        $this->app->singleton(CmsHomepageService::class);
        $this->app->singleton(CmsCtaTargetValidationService::class);
        $this->app->singleton(CmsHeroSlideService::class);
        $this->app->singleton(CmsFeaturedContentValidationService::class);
        $this->app->singleton(CmsFeaturedContentResolver::class);
        $this->app->singleton(CmsFeaturedContentService::class);
        $this->app->singleton(CmsNavigationItemValidationService::class);
        $this->app->singleton(CmsNavigationShellService::class);
        $this->app->singleton(CmsNavigationResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(CmsHomepageLayout::class, CmsHomepageLayoutPolicy::class);
        Gate::policy(CmsHomepageSection::class, CmsHomepageSectionPolicy::class);
        Gate::policy(CmsHeroSlide::class, CmsHeroSlidePolicy::class);
        Gate::policy(CmsFeaturedContent::class, CmsFeaturedContentPolicy::class);
        Gate::policy(CmsCampaign::class, CmsCampaignPolicy::class);
        Gate::policy(CmsNavigationShell::class, CmsNavigationShellPolicy::class);

        Event::listen(CmsPlatformAudit::class, [RecordActivityLog::class, 'record']);
    }
}

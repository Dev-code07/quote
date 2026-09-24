<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Policies\ClientPolicy;
use App\Policies\QuotePolicy;
use App\Policies\QuoteTemplatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Authorization is enforced through policies, never ad hoc in
        // controllers (rules.md section 6). Quote policies arrive in Phase 4.
        Gate::policy(Client::class, ClientPolicy::class);

        if (class_exists(Quote::class)) {
            Gate::policy(Quote::class, QuotePolicy::class);
        }

        if (class_exists(QuoteTemplate::class)) {
            Gate::policy(QuoteTemplate::class, QuoteTemplatePolicy::class);
        }

        // Sidebar quote count badge (prototype .nav-badge), shared with the
        // layout so the nav and the list pages cannot disagree.
        View::composer('layouts.app', function ($view): void {
            $view->with('quoteCount', Quote::query()->count());
        });
    }
}

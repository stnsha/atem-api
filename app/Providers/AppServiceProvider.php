<?php

namespace App\Providers;

use App\Models\Atem;
use App\Observers\AtemObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Atem::observe(AtemObserver::class);
    }
}

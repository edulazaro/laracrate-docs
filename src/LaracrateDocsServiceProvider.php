<?php

namespace EduLazaro\LaracrateDocs;

use Illuminate\Support\ServiceProvider;

class LaracrateDocsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}

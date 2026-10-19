<?php

namespace EduLazaro\LaracrateDocs;

use EduLazaro\Laracrate\Models\File;
use EduLazaro\Laracrate\Support\ViewerRegistry;
use EduLazaro\LaracrateDocs\Models\Document;
use Illuminate\Support\ServiceProvider;

class LaracrateDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laracrate-docs.php', 'laracrate-docs');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/laracrate-docs.php' => config_path('laracrate-docs.php'),
        ], 'laracrate-docs-config');

        $this->registerPreview();
    }

    /**
     * A document opens in Laracrate's viewer as its HTML, with a link to the app's editor
     * (laracrate-docs.edit_route).
     */
    protected function registerPreview(): void
    {
        $this->app->make(ViewerRegistry::class)->html(Document::MIME, function (File $file) {
            $document = Document::forFile($file);
            if (! $document) {
                return null;
            }

            $route = config('laracrate-docs.edit_route');

            return [
                'html' => (string) $document->content_html,
                'edit' => $route ? route($route, $file->slug) : null,
            ];
        });
    }
}

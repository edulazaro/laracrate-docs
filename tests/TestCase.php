<?php

namespace EduLazaro\LaracrateDocs\Tests;

use EduLazaro\Laracrate\LaracrateServiceProvider;
use EduLazaro\Laractions\LaractionsServiceProvider;
use EduLazaro\LaracrateDocs\LaracrateDocsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaractionsServiceProvider::class,
            LaracrateServiceProvider::class,
            LaracrateDocsServiceProvider::class,
        ];
    }

    /**
     * MySQL y no SQLite, por la misma razón que en Laracrate: sus migraciones usan
     * DDL que solo existe en MySQL (índices FULLTEXT, consultas a information_schema,
     * ALTER ... MODIFY). Como este paquete tiene una clave ajena a `laracrate_files`,
     * necesita esas migraciones de verdad y no una tabla inventada que se quedaría
     * desincronizada en cuanto Laracrate cambiara.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $storage = sys_get_temp_dir() . '/laracrate-docs-test-storage';
        foreach (['app', 'framework/cache', 'framework/views', 'framework/sessions', 'logs'] as $sub) {
            if (! is_dir($dir = $storage . '/' . $sub)) {
                @mkdir($dir, 0777, true);
            }
        }
        $app->useStoragePath($storage);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'laracrate_docs_test'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', 'root'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]);
    }

    /**
     * Un modelo cualquiera al que colgar los documentos.
     *
     * El paquete no sabe qué es un usuario ni una organización: el dueño y el
     * contenedor son morphs, y esto es lo que lo demuestra.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('test_actors')) {
            Schema::create('test_actors', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }
    }
}

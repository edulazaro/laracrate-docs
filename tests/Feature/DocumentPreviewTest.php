<?php

namespace EduLazaro\LaracrateDocs\Tests\Feature;

use EduLazaro\Laracrate\Support\ViewerRegistry;
use EduLazaro\LaracrateDocs\Actions\CreateDocumentAction;
use EduLazaro\LaracrateDocs\Actions\SaveDocumentContentAction;
use EduLazaro\LaracrateDocs\Models\Document;
use EduLazaro\LaracrateDocs\Tests\Support\TestActor;
use EduLazaro\LaracrateDocs\Tests\TestCase;

/**
 * A document opens in Laracrate's viewer as its HTML, with a link to the app's editor
 * (laracrate-docs.edit_route). Needs a Laracrate with the viewer registry.
 */
class DocumentPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ViewerRegistry::class)) {
            $this->markTestSkipped('This Laracrate has no viewer registry.');
        }
    }

    protected function defineRoutes($router): void
    {
        $router->get('/docs/{slug}', fn () => 'editor')->name('docs.edit');
    }

    private function make(): Document
    {
        $actor = TestActor::create(['name' => 'Someone']);

        return app(CreateDocumentAction::class)->handle(
            title: 'Lease',
            owner: $actor,
            fileable: $actor,
            creator: $actor,
        );
    }

    public function test_a_document_shows_its_html_with_a_link_to_the_editor(): void
    {
        config()->set('laracrate-docs.edit_route', 'docs.edit');
        $document = $this->make();
        app(SaveDocumentContentAction::class)->handle($document, ['type' => 'doc'], '<p>Signed in Madrid</p>', 3);

        $preview = app(ViewerRegistry::class)->previewFor($document->file->fresh());

        $this->assertSame('<p>Signed in Madrid</p>', $preview['html']);
        $this->assertSame(url('/docs/' . $document->file->slug), $preview['edit']);
    }

    public function test_without_an_editor_route_there_is_no_link(): void
    {
        $document = $this->make();

        $this->assertNull(app(ViewerRegistry::class)->previewFor($document->file)['edit']);
    }
}

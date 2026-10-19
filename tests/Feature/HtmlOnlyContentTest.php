<?php

namespace EduLazaro\LaracrateDocs\Tests\Feature;

use EduLazaro\LaracrateDocs\Actions\CreateDocumentAction;
use EduLazaro\LaracrateDocs\Actions\SaveDocumentContentAction;
use EduLazaro\LaracrateDocs\Models\Document;
use EduLazaro\LaracrateDocs\Tests\Support\TestActor;
use EduLazaro\LaracrateDocs\Tests\TestCase;

/**
 * Content written outside the editor (an agent, an import) arrives as HTML, with no
 * JSON until an editor opens it.
 */
class HtmlOnlyContentTest extends TestCase
{
    private function make(): Document
    {
        $actor = TestActor::create(['name' => 'Someone']);

        return app(CreateDocumentAction::class)->handle(
            title: 'Draft',
            owner: $actor,
            fileable: $actor,
            creator: $actor,
        );
    }

    private function saveHtml(Document $document, string $html, int $words): Document
    {
        return app(SaveDocumentContentAction::class)->handle($document, null, $html, $words);
    }

    public function test_html_can_be_saved_without_json(): void
    {
        $document = $this->make();
        $document->file->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

        $html = '<h1>Claim</h1><p>' . str_repeat('word ', 200) . '</p>';
        $this->saveHtml($document, $html, 201);

        $fresh = $document->fresh();
        $this->assertNull($fresh->content_json);
        $this->assertSame($html, $fresh->content_html);
        $this->assertSame(201, $fresh->word_count);
        // The drive shows the size of what there is: the HTML.
        $this->assertSame(strlen($html), $document->file->fresh()->size);
    }

    public function test_content_needs_json_or_html(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(SaveDocumentContentAction::class)->handle($this->make(), null);
    }

    public function test_a_revision_of_html_only_content_keeps_the_html(): void
    {
        $document = $this->saveHtml($this->make(), '<p>First draft</p>', 2);

        $revision = $document->snapshot('before the agent');

        $this->assertNull($revision->content_json);
        $this->assertSame('<p>First draft</p>', $revision->fresh()->content_html);
    }

    public function test_a_revision_with_json_does_not_duplicate_the_html(): void
    {
        $document = $this->make();
        app(SaveDocumentContentAction::class)->handle($document, Document::EMPTY_CONTENT, '<p></p>', 0);

        $revision = $document->snapshot();

        $this->assertNotNull($revision->content_json);
        $this->assertNull($revision->fresh()->content_html);
    }

    public function test_reverting_to_html_only_content_brings_the_html_back(): void
    {
        $document = $this->saveHtml($this->make(), '<p>First draft</p>', 2);
        $first = $document->snapshot('before the agent');

        $this->saveHtml($document, '<p>Rewritten by the agent</p>', 4);
        $document->revertTo($first);

        $fresh = $document->fresh();
        $this->assertNull($fresh->content_json);
        $this->assertSame('<p>First draft</p>', $fresh->content_html);
        $this->assertSame(2, $fresh->word_count);
    }
}

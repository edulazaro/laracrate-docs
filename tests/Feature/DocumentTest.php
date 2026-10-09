<?php

namespace EduLazaro\LaracrateDocs\Tests\Feature;

use EduLazaro\Laracrate\Models\File;
use EduLazaro\LaracrateDocs\Actions\CreateDocumentAction;
use EduLazaro\LaracrateDocs\Actions\SaveDocumentContentAction;
use EduLazaro\LaracrateDocs\Models\Document;
use EduLazaro\LaracrateDocs\Models\DocumentField;
use EduLazaro\LaracrateDocs\Tests\Support\TestActor;
use EduLazaro\LaracrateDocs\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class DocumentTest extends TestCase
{
    private function make(string $title = 'Draft'): Document
    {
        $actor = TestActor::create(['name' => 'Someone']);

        return app(CreateDocumentAction::class)->handle(
            title: $title,
            owner: $actor,
            fileable: $actor,
            creator: $actor,
        );
    }

    public function test_a_document_gets_a_file_row_with_no_binary(): void
    {
        Storage::fake('local');

        $document = $this->make('Loan agreement');
        $file = $document->file;

        $this->assertSame(Document::MIME, $file->mime_type);
        $this->assertSame(Document::EXTENSION, $file->extension);
        $this->assertSame('Loan agreement', $document->title);

        // Nada se ha escrito en ningún disco: el contenido vive en la base.
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_the_processing_pipeline_is_not_launched(): void
    {
        $document = $this->make();

        // La puerta del observer de Laracrate: con el estado ya puesto no intenta
        // leer el binario inexistente para extraerle el texto.
        $this->assertNotNull($document->file->processing_status);
    }

    public function test_saving_content_keeps_the_drive_row_current(): void
    {
        $document = $this->make();
        $before = $document->file->size;

        // El File se refresca cada FILE_SYNC_SECONDS, así que para la prueba se
        // envejece su fecha a mano en lugar de esperar medio minuto.
        $document->file->forceFill([
            'updated_at' => now()->subMinutes(5),
        ])->saveQuietly();

        app(SaveDocumentContentAction::class)->handle(
            $document,
            ['type' => 'doc', 'content' => array_fill(0, 40, ['type' => 'paragraph'])],
            '<p>...</p>',
            320,
        );

        $this->assertGreaterThan($before, $document->file->fresh()->size);
        $this->assertSame(320, $document->fresh()->word_count);
    }

    public function test_renaming_writes_both_name_and_title(): void
    {
        $document = $this->make('Old name');

        $document->rename('New name');

        $file = $document->file->fresh();
        $this->assertSame('New name', $file->name);
        $this->assertSame('New name', $file->title);
    }

    public function test_revisions_are_numbered_and_keep_their_author(): void
    {
        $document = $this->make();
        $author = TestActor::create(['name' => 'Writer']);

        $first = $document->snapshot('first', $author);
        $second = $document->snapshot('second', $author);

        $this->assertSame(1, $first->revision);
        $this->assertSame(2, $second->revision);
        $this->assertTrue($second->author->is($author));
    }

    public function test_reverting_keeps_the_current_state_first(): void
    {
        $document = $this->make();
        $original = $document->snapshot('original');

        app(SaveDocumentContentAction::class)->handle($document, ['type' => 'doc', 'content' => []], null, 999);

        $document->revertTo($original);

        // Tres: la original, la que guarda lo que había antes de volver, y nada más.
        $this->assertSame(2, $document->revisions()->count());
        $this->assertSame(0, $document->fresh()->word_count);
    }

    public function test_a_key_cannot_hold_two_values(): void
    {
        $document = $this->make();

        $document->fields()->create([
            'key' => 'minimum_fee', 'label' => 'Minimum fee', 'type' => 'money', 'value' => '7292.10',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $document->fields()->create([
            'key' => 'minimum_fee', 'label' => 'Minimum fee again', 'type' => 'money', 'value' => '7292.11',
        ]);
    }

    public function test_a_document_is_incomplete_while_a_field_is_empty(): void
    {
        $document = $this->make();

        $document->fields()->create(['key' => 'principal', 'label' => 'Principal', 'type' => 'money', 'value' => '180000']);
        $document->fields()->create([
            'key' => 'fee', 'label' => 'Fee', 'type' => 'money',
            'source' => DocumentField::SOURCE_FORMULA, 'expression' => 'principal*0.1',
        ]);

        $this->assertFalse($document->isComplete());
        $this->assertSame(1, $document->missingFields()->count());
    }

    public function test_deleting_the_file_takes_the_content_with_it(): void
    {
        $document = $this->make();
        $id = $document->id;

        $document->file->forceDelete();

        $this->assertNull(Document::find($id));
    }

    public function test_the_document_can_be_found_from_its_file(): void
    {
        $document = $this->make();

        $this->assertSame($document->id, Document::forFile($document->file)?->id);
    }
}

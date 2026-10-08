<?php

namespace EduLazaro\LaracrateDocs\Actions;

use EduLazaro\LaracrateDocs\Models\Document;
use Illuminate\Support\Carbon;

/**
 * Guarda el contenido del documento y mantiene al día su ficha en el drive.
 *
 * El contenido vive en `laracrate_documents`, pero el drive ordena y pinta desde el
 * File: su `size` y su `updated_at`. Sin esta sincronización, un documento que se
 * escribe durante una hora seguiría apareciendo en el drive con 47 bytes y la fecha
 * de cuando se creó, que es justo lo que uno mira para encontrar en qué estaba
 * trabajando.
 *
 * Pero el File NO se toca en cada guardado: el autosave del editor va cada 800 ms y
 * eso serían dos escrituras por segundo en dos tablas para mover una fecha que nadie
 * mira a esa resolución. Se refresca cada FILE_SYNC_SECONDS, que para un listado es
 * indistinguible de hacerlo siempre.
 */
class SaveDocumentContentAction
{
    /** Cada cuánto, como mucho, se refresca la ficha del drive. */
    public const FILE_SYNC_SECONDS = 30;

    public function handle(
        Document $document,
        array $contentJson,
        ?string $contentHtml = null,
        ?int $wordCount = null,
    ): Document {
        $document->content_json = $contentJson;

        if ($contentHtml !== null) {
            $document->content_html = $contentHtml;
        }

        if ($wordCount !== null) {
            $document->word_count = $wordCount;
        }

        $document->save();

        $this->syncFile($document);

        return $document;
    }

    private function syncFile(Document $document): void
    {
        $file = $document->file;

        if (! $file) {
            return;
        }

        $stale = $file->updated_at === null
            || $file->updated_at->lt(Carbon::now()->subSeconds(self::FILE_SYNC_SECONDS));

        if (! $stale) {
            return;
        }

        // Un solo UPDATE: el tamaño que el drive muestra y la fecha por la que ordena.
        $file->forceFill([
            'size' => strlen((string) json_encode($document->content_json)),
        ])->save();
    }
}

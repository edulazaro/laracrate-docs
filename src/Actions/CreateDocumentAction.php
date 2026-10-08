<?php

namespace EduLazaro\LaracrateDocs\Actions;

use EduLazaro\Laracrate\Models\File;
use EduLazaro\Laracrate\Models\Folder;
use EduLazaro\LaracrateDocs\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea un documento nativo: su ficha en el drive (un File) y su contenido.
 *
 * NO pasa por `addFile()` a propósito. Ese método espera un fichero que subir, y aquí
 * no hay ninguno: el contenido vive en MySQL y se edita cada pocos segundos. Escribir
 * un binario en el almacén en la creación dejaría en R2 un fichero que miente desde la
 * primera tecla.
 *
 * Los seis campos que laracrate exige NOT NULL (`disk`, `path`, `extension`,
 * `mime_type`, `original_name`, `size`) se rellenan con valores sintéticos coherentes.
 * Es lo mismo que hace Drive con sus documentos nativos: un mime propio y cero bytes.
 *
 * `processing_status` va puesto DESDE LA CREACIÓN porque es la puerta que mira
 * `FileObserver::created()` para no lanzar el pipeline: sin eso, laracrate intentaría
 * leer el binario que no existe para extraerle el texto.
 */
class CreateDocumentAction
{
    public function handle(
        string $title,
        Model $owner,
        Model $fileable,
        ?Model $creator = null,
        ?Folder $folder = null,
        string $collection = 'drive',
        string $context = 'drive',
    ): Document {
        $slug = (string) Str::ulid();
        $content = Document::EMPTY_CONTENT;

        return DB::transaction(function () use ($title, $owner, $fileable, $creator, $folder, $collection, $context, $slug, $content) {
            $file = new File([
                'slug'          => $slug,
                'name'          => $title,
                'title'         => $title,
                'original_name' => $title,

                // Disco de la colección, para que la ruta sea coherente con el resto
                // del drive. No hay objeto detrás: el mime propio y el estado de
                // procesado ya lo dicen.
                'disk'      => (string) config("laracrate.collections.{$collection}.disk", 'local'),
                'path'      => $this->syntheticPath($fileable, $slug),
                'extension' => Document::EXTENSION,
                'mime_type' => Document::MIME,
                'size'      => strlen((string) json_encode($content)),

                'type'       => 'document',
                'collection' => $collection,
                'context'    => $context,

                'access'     => 'signed',
                'visibility' => 'private',

                // La puerta del observer: sin esto intentaría procesar un binario
                // inexistente.
                'processing_status' => 'completed',
            ]);

            $file->fileable()->associate($fileable);
            $file->owner()->associate($owner);

            if ($creator) {
                $file->creator()->associate($creator);
            }

            if ($folder) {
                $file->folder()->associate($folder);
            }

            $file->save();

            return Document::create([
                'file_id'      => $file->id,
                'content_json' => $content,
                'content_html' => '',
                'word_count'   => 0,
                'status'       => 'draft',
            ]);
        });
    }

    /**
     * Ruta sintética, con la misma forma que las de verdad.
     *
     * No existe en el almacén, pero deja el hueco preparado si algún día se quiere
     * guardar ahí un volcado del contenido, y hace que un listado del bucket no
     * desconcierte a quien lo mire.
     */
    private function syntheticPath(Model $fileable, string $slug): string
    {
        $scope = Str::snake(class_basename($fileable)) . '/' . $fileable->getKey();

        return "{$scope}/documents/{$slug}.json";
    }
}

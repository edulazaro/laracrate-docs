<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos nativos: el contenido editable de un File de laracrate.
 *
 * El File es el índice del drive (nombre, tamaño, carpeta, dueño, papelera), igual
 * que un Google Doc es una fila del recurso `files` de Drive con 0 bytes. Aquí va
 * solo lo que el File no sabe: el contenido.
 *
 * NO hay `title`: el título es del File. Duplicarlo devuelve el problema de las dos
 * verdades, y el listado del drive ordena por el del File.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laracrate_documents', function (Blueprint $table) {
            $table->id();

            // El File es el dueño de la identidad del documento en el drive.
            // cascadeOnDelete hace lo correcto con la papelera: un borrado blando del
            // File deja el contenido (restaurar lo recupera) y un forceDelete se lo lleva.
            $table->foreignId('file_id')->unique()
                ->constrained('laracrate_files')->cascadeOnDelete();

            // La verdad del documento. El HTML es derivado: se regenera al guardar y
            // sirve para pintar y para el PDF.
            $table->json('content_json')->nullable();

            // longText y no text: una escritura de 6.000 palabras son unos 80 KB de
            // HTML y text se queda en 64 KB. Con STRICT_TRANS_TABLES eso no trunca,
            // lanza 1406 Data too long, y lo que falla es GUARDAR.
            $table->longText('content_html')->nullable();

            $table->unsignedInteger('word_count')->default(0);
            $table->string('status', 20)->default('draft'); // draft | review | final

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laracrate_documents');
    }
};

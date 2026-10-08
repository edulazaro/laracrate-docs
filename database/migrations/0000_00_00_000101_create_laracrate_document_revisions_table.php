<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial del documento, solo de escritura (append-only).
 *
 * Las revisiones se crean por HITOS, no por guardado: el autosave es cada 800 ms y
 * una versión por frase escrita hace el historial ilegible. Los hitos son cerrar la
 * sesión de edición, pulsar "guardar versión" y, obligatoriamente, ANTES de que la
 * IA toque el documento: es lo que permite deshacer lo que hizo la IA sin perder lo
 * que escribió la persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laracrate_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('laracrate_documents')->cascadeOnDelete();

            $table->unsignedInteger('revision');

            // Sin content_html: se regenera del JSON, y duplicar 80 KB por versión
            // multiplica la tabla sin dar nada.
            $table->json('content_json');

            // El título del momento, porque el del File cambia y una versión tiene
            // que poder decir cómo se llamaba el documento cuando se guardó.
            $table->string('title')->nullable();
            $table->string('label')->nullable(); // "antes de la IA", "enviado al notario"

            // Morph y no user_id: el autor puede ser un agente, y distinguir una
            // versión escrita por la IA de una escrita por la persona es justo lo que
            // querrás saber cuando algo salga mal.
            $table->nullableMorphs('author');

            $table->unsignedInteger('word_count')->default(0);

            // Solo created_at: una revisión no se modifica nunca.
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laracrate_document_revisions');
    }
};

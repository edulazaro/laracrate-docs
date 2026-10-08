<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los datos del documento, fuera del contenido.
 *
 * En el JSON un hueco guarda solo su `key`; el valor vive aquí. Eso da cuatro cosas:
 * listar lo que falta sin leer el documento, rellenarlo desde un formulario, pintar
 * una línea en blanco cuando está vacío, y que un importe usado en tres cláusulas
 * sea UNA fila.
 *
 * El `unique(document_id, key)` es la línea que importa: hace imposible que el mismo
 * dato tenga dos valores. Un dictamen real traía 7.292,10 € y 7.292,11 € para la
 * misma cantidad, en el mismo documento y después de anunciar que estaba corregido,
 * porque el número vivía como texto repetido en cinco sitios.
 *
 * `source` cubre los tres orígenes reales: escrito a mano, traído del expediente
 * (`client.nif`) o CALCULADO a partir de otros. La tabla de TAE efectiva de una
 * escritura no es un dato: es una función del principal y del plazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laracrate_document_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('laracrate_documents')->cascadeOnDelete();

            $table->string('key', 64);   // remuneracion_minima
            $table->string('label');     // "Remuneración mínima"
            $table->string('type', 20)->default('text'); // text|money|date|nif|percent|number

            $table->string('source', 20)->default('manual'); // manual | binding | formula
            // El binding (`client.nif`) o la fórmula (`principal*((1+tae)^(plazo/12)-1)`).
            $table->string('expression')->nullable();

            // Texto suelto a propósito: el formateo lo decide `type` al renderizar, así
            // una cifra se imprime en letra y número cuando toca sin cinco columnas
            // nullable esperando su turno.
            $table->text('value')->nullable();

            $table->timestamp('filled_at')->nullable();
            $table->nullableMorphs('filled_by');

            $table->timestamps();

            $table->unique(['document_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laracrate_document_fields');
    }
};

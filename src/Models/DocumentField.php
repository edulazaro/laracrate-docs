<?php

namespace EduLazaro\LaracrateDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un dato del documento: lo que en el contenido es un hueco con nombre.
 *
 * El contenido guarda la `key`, nunca el valor. Así un importe usado en tres
 * cláusulas y en una tabla es UNA fila, y corregirlo lo corrige en los cuatro
 * sitios. El unique(document_id, key) de la migración lo hace imposible de duplicar.
 */
class DocumentField extends Model
{
    protected $table = 'laracrate_document_fields';

    /** De dónde sale el valor. */
    public const SOURCE_MANUAL  = 'manual';   // lo escribe una persona
    public const SOURCE_BINDING = 'binding';  // viene del expediente: client.nif
    public const SOURCE_FORMULA = 'formula';  // se calcula de otros campos

    protected $fillable = [
        'key',
        'label',
        'type',
        'source',
        'expression',
        'value',
        'filled_at',
    ];

    protected $casts = [
        'filled_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function filledBy(): MorphTo
    {
        return $this->morphTo('filled_by');
    }

    public function isFilled(): bool
    {
        return $this->value !== null && $this->value !== '';
    }

    /** Un campo calculado no se rellena a mano: lo deriva el documento. */
    public function isDerived(): bool
    {
        return in_array($this->source, [self::SOURCE_BINDING, self::SOURCE_FORMULA], true);
    }
}

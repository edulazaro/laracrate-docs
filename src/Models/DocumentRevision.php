<?php

namespace EduLazaro\LaracrateDocs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Una versión congelada del documento. Solo se escribe: nunca se modifica.
 *
 * El autor es polimórfico porque puede ser un agente y no una persona, y saber cuál
 * de las dos escribió una versión es lo primero que se pregunta cuando algo sale mal.
 */
class DocumentRevision extends Model
{
    protected $table = 'laracrate_document_revisions';

    /** Una revisión no se actualiza, así que no hay updated_at que mantener. */
    const UPDATED_AT = null;

    protected $fillable = [
        'revision',
        'content_json',
        'content_html',
        'title',
        'label',
        'word_count',
    ];

    protected $casts = [
        'content_json' => 'array',
        'revision'     => 'integer',
        'word_count'   => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** Respeta el morph map de la aplicación: user, agente o lo que haya. */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}

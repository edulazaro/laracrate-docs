<?php

namespace EduLazaro\LaracrateDocs\Models;

use EduLazaro\Laracrate\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El contenido editable de un File de laracrate.
 *
 * Todo lo que es identidad en el drive (título, carpeta, dueño, papelera, tamaño,
 * permisos) vive en el File. Aquí solo el contenido, porque duplicar el título
 * devuelve el problema de las dos verdades.
 *
 * El File es el dueño: un documento no se borra solo. Se borra su File, y la clave
 * ajena se lleva esto por delante. Así la papelera del drive vale para las dos cosas
 * sin escribir la lógica dos veces.
 */
class Document extends Model
{
    protected $table = 'laracrate_documents';

    /**
     * El formato propio, al estilo de los nativos de Google Docs
     * (application/vnd.google-apps.document): un mime inventado que dice que esto no
     * es un binario, y por tanto que no hay nada que descargar hasta exportarlo.
     */
    public const MIME = 'application/vnd.laracrate.document';
    public const EXTENSION = 'doc';

    /** Un documento recién creado: un párrafo vacío, en el formato de ProseMirror. */
    public const EMPTY_CONTENT = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

    protected $fillable = [
        'file_id',
        'content_json',
        'content_html',
        'word_count',
        'status',
    ];

    protected $casts = [
        'content_json' => 'array',
        'word_count'   => 'integer',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id')->orderByDesc('revision');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class, 'document_id');
    }

    /** El documento de un File, cuando vienes del lado del drive. */
    public static function forFile(File|int $file): ?self
    {
        return static::where('file_id', $file instanceof File ? $file->id : $file)->first();
    }

    /**
     * El título es del File; aquí solo se lee.
     *
     * ⚠️ Carga la relación si no está: en un listado, usa `with('file')` o tendrás un
     * N+1 silencioso.
     */
    public function getTitleAttribute(): ?string
    {
        return $this->file?->title ?: $this->file?->name;
    }

    /**
     * Renombrar, en un solo sitio.
     *
     * El File tiene `name` y `title`, y el drive ordena por uno y muestra el otro.
     * Si cada consumidor escribe el suyo, acaban diciendo cosas distintas.
     */
    public function rename(string $title): static
    {
        $this->file?->forceFill(['name' => $title, 'title' => $title])->save();

        return $this;
    }

    /**
     * Los datos que el documento espera y todavía no tiene.
     *
     * Es lo que decide si un PDF sale marcado como borrador: un documento con huecos
     * vacíos no debe circular con aspecto de definitivo.
     */
    public function missingFields(): HasMany
    {
        return $this->fields()->whereNull('value');
    }

    public function isComplete(): bool
    {
        return ! $this->fields()->whereNull('value')->exists();
    }

    /**
     * Congela el estado actual como una revisión nueva.
     *
     * Se llama en HITOS, nunca en el autosave: al cerrar la edición, al pedirlo la
     * persona y OBLIGATORIAMENTE antes de que la IA escriba. Ese último es el que
     * permite descartar lo que hizo la IA sin perder lo anterior.
     *
     * El número se calcula leyendo el máximo, así que dos snapshots a la vez pueden
     * pedir el mismo. No se serializa a propósito: el unique(document_id, revision)
     * de la tabla lo impide, y el caso es tan raro (dos guardados de versión en el
     * mismo milisegundo sobre el mismo documento) que no compensa bloquear la fila en
     * cada guardado para evitarlo.
     */
    public function snapshot(?string $label = null, ?Model $author = null): DocumentRevision
    {
        $last = DocumentRevision::where('document_id', $this->id)->max('revision');

        $revision = new DocumentRevision([
            'revision'     => ((int) $last) + 1,
            'content_json' => $this->content_json,
            'title'        => $this->title,
            'label'        => $label,
            'word_count'   => $this->word_count,
        ]);

        $revision->document_id = $this->id;

        if ($author) {
            $revision->author()->associate($author);
        }

        $revision->save();

        return $revision;
    }

    /**
     * Vuelve a una versión anterior SIN perder la actual.
     *
     * No se llama `restore()` a propósito: ese nombre es de SoftDeletes, y el día que
     * alguien añada el trait al modelo tendríamos dos métodos con el mismo nombre y
     * significados opuestos.
     *
     * Restaurar no es deshacer: antes de pisar el contenido se guarda lo que hay, o
     * la vuelta atrás sería una forma elegante de perder trabajo.
     */
    public function revertTo(DocumentRevision $revision, ?Model $author = null): static
    {
        $this->snapshot('antes de restaurar la version ' . $revision->revision, $author);

        $this->content_json = $revision->content_json;
        $this->word_count = $revision->word_count;
        $this->save();

        return $this;
    }
}

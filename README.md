![Laracrate Docs](art/banner.png)

# Laracrate Docs

<p align="center">
    <a href="https://github.com/edulazaro/laracrate-docs/actions/workflows/tests.yml"><img src="https://github.com/edulazaro/laracrate-docs/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
    <a href="https://packagist.org/packages/edulazaro/laracrate-docs"><img src="https://img.shields.io/packagist/v/edulazaro/laracrate-docs" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/edulazaro/laracrate-docs"><img src="https://img.shields.io/packagist/dt/edulazaro/laracrate-docs" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/laracrate-docs"><img src="https://img.shields.io/packagist/php-v/edulazaro/laracrate-docs" alt="PHP Version"></a>
    <a href="https://github.com/edulazaro/laracrate-docs/blob/main/LICENSE"><img src="https://img.shields.io/packagist/l/edulazaro/laracrate-docs" alt="License"></a>
</p>

Native editable documents for [Laracrate](https://github.com/edulazaro/laracrate). A document is not a file you upload: it is a row in your drive with no binary behind it, like a Google Doc in Drive. The file is the index; the content, the versions and the named fields live apart.

## Table of contents

1. [What is Laracrate Docs?](#what-is-laracrate-docs)
2. [Philosophy](#philosophy)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Quick start](#quick-start)
6. [Data model](#data-model)
7. [Creating documents](#creating-documents)
8. [Saving content](#saving-content)
9. [Versions](#versions)
10. [Fields](#fields)
11. [The drive query](#the-drive-query)
12. [Previews in the viewer](#previews-in-the-viewer)
13. [Exporting](#exporting)
14. [What this package does not do](#what-this-package-does-not-do)
15. [Author](#author)
16. [License](#license)

## What is Laracrate Docs?

Laracrate stores files: a binary lands on R2 or S3, gets a row, and from then on it is read, previewed and downloaded. A document written in an editor is a different animal. It has no binary, it changes every few seconds, and what matters about it is the content, not the bytes.

Google solved this in Drive by keeping one index and two storages: a native Doc is a row in the `files` resource with `mimeType = application/vnd.google-apps.document` and zero bytes, while its content lives in the service. This package does the same thing on top of Laracrate.

The result is that your drive stays **one query**. A folder listing can sort by name, size or date, paginate, and mix uploaded PDFs with documents written in your app, without unions or merging two lists in PHP.

## Philosophy

**The file is the index.** Title, folder, owner, tenant, visibility, trash: all of that already exists in `laracrate_files` and is not duplicated here. A document that stored its own title would eventually disagree with the one the drive shows.

**Content is not a binary.** Nothing is written to your bucket when someone types. The path on the file row is synthetic: it keeps the shape of a real one so a bucket listing does not confuse anyone, and leaves the slot ready if you ever want to dump a snapshot there.

**Data lives outside the content.** A placeholder in the document stores a key, never a value. The value is a row, so an amount used in three clauses and a table is one row and correcting it corrects all four. A unique constraint makes a second value for the same key impossible.

**Versions are milestones, not keystrokes.** Autosave runs every few hundred milliseconds; a version per sentence is a history nobody can read.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [edulazaro/laracrate](https://github.com/edulazaro/laracrate) ^2.0 (for Laracrate 1.x, use laracrate-docs 1.x)

## Installation

```bash
composer require edulazaro/laracrate-docs
php artisan migrate
```

The three tables are created with the `laracrate_` prefix, next to the ones from Laracrate, and their migrations are numbered to run after them because `laracrate_documents` has a foreign key to `laracrate_files`.

Declare a collection for your drive in `config/laracrate.php` if you do not have one already. It is what defines the disk, the access mode and the mime types accepted for the files that *are* uploaded there; native documents skip all of it.

## Quick start

```php
use EduLazaro\LaracrateDocs\Actions\CreateDocumentAction;
use EduLazaro\LaracrateDocs\Actions\SaveDocumentContentAction;

$document = app(CreateDocumentAction::class)->handle(
    title: 'Loan agreement (draft)',
    owner: $user,              // who sees it in their drive
    fileable: $organization,   // where it belongs
    creator: $user,
    folder: $folder,           // optional
);

app(SaveDocumentContentAction::class)->handle(
    $document,
    contentJson: $editorState,
    contentHtml: $renderedHtml,
    wordCount: 1240,
);

$document->snapshot('sent to the notary', $user);
```

## Data model

| Table | What it holds |
|---|---|
| `laracrate_files` (Laracrate) | the drive index: name, size, folder, owner, tenant, visibility, trash |
| `laracrate_documents` | `content_json`, `content_html`, `word_count`, `status` |
| `laracrate_document_revisions` | append-only history, with a polymorphic author |
| `laracrate_document_fields` | the named data of the document and where each value comes from |

`laracrate_documents.file_id` is unique and cascades on delete, so the trash works for both sides without writing it twice: a soft deleted file keeps its content and restoring brings it back, a force delete takes the content with it.

## Creating documents

`CreateDocumentAction` does not go through `addFile()`. That method expects something to upload, and there is nothing: it creates the file row directly with synthetic values for the six columns Laracrate requires (`disk`, `path`, `extension`, `mime_type`, `original_name`, `size`).

It also sets `processing_status` from the start. That is the gate `FileObserver::created()` checks before launching the pipeline, so Laracrate never tries to read a binary that is not there.

## Saving content

`SaveDocumentContentAction` writes the content and keeps the drive row current, because the drive sorts by the file's `updated_at` and shows its `size`. Without it, a document written for an hour still lists as 47 bytes from the moment it was created.

The file row is **not** touched on every save. Autosave fires several times a minute; the row is refreshed at most every `FILE_SYNC_SECONDS` (30 by default), which for a listing is indistinguishable from doing it always.

Renaming goes through `$document->rename($title)`, which writes both `name` and `title` on the file in one place. The drive sorts by one and displays the other, and letting each caller pick is how they end up disagreeing.

### Content written outside the editor

An agent or an import has HTML, not the editor's JSON. Save it with a `null` JSON:

```php
app(SaveDocumentContentAction::class)->handle($document, null, $html, $wordCount);
```

The document then holds only `content_html`, and the drive shows its size. Your editor should load the HTML when there is no JSON and save once on opening, so the document gets its JSON from then on. Versions of HTML-only content keep the HTML, and reverting to one brings it back.

## Versions

```php
$revision = $document->snapshot('before the AI edited it', $author);

$document->revertTo($revision, $author);
```

Call `snapshot()` at milestones: when the editing session ends, when someone asks for it, and **always before an agent writes**. That last one is what lets you throw away what the AI did without losing what the person wrote.

`revertTo()` snapshots the current state before overwriting, because rolling back should not be an elegant way to lose work. It is not called `restore()` on purpose: that name belongs to SoftDeletes.

The author is polymorphic, so a version written by an agent is distinguishable from one written by a person. That is the first thing anyone asks when something goes wrong.

## Fields

A placeholder in the content stores only its key:

```json
{ "type": "placeholder", "attrs": { "key": "minimum_fee" } }
```

The value is a row, with three possible origins:

| `source` | Where the value comes from |
|---|---|
| `manual` | someone types it |
| `binding` | pulled from your data, via `expression` (`client.tax_id`) |
| `formula` | computed from other fields (`principal*((1+rate)^(months/12)-1)`) |

```php
$document->fields()->create([
    'key' => 'minimum_fee', 'label' => 'Minimum fee', 'type' => 'money',
    'source' => 'formula', 'expression' => 'principal*((1+rate)^(months/12)-1)',
]);

$document->missingFields()->count();   // what is still empty
$document->isComplete();               // false while anything is missing
```

`isComplete()` is what tells your export whether the PDF should carry a draft mark. A document with empty placeholders should not circulate looking final, and no word processor can tell you that because none of them knows a tax id is missing.

## The drive query

Because the index is the file row, listing a drive is one query and the documents come along:

```php
File::where('owner_type', 'user')->where('owner_id', $user->id)
    ->where('folder_id', $folder?->id)
    ->orderBy('name')
    ->paginate();
```

To go from a file to its content, `Document::forFile($file)`. In a listing, eager load with `with('file')` or the title accessor will give you a silent N+1.

## Previews in the viewer

With a Laracrate that has `ViewerRegistry`, documents open in its viewer (`laracrate::viewer`) like an image or a recording: `Laracrate.viewer.open($file->slug)` shows `content_html` small and scrolling, and readable at full size. Nothing to call; the package registers itself. With an older Laracrate the viewer keeps answering 422 for documents.

To add an "Edit" button to that card, tell the package which named route opens your editor. It receives the file's slug:

```bash
php artisan vendor:publish --tag=laracrate-docs-config
```

```php
// config/laracrate-docs.php
'edit_route' => 'documents.edit',   // Route::get('/documents/{slug}', ...)->name('documents.edit')
```

## Exporting

This package does not render PDFs: it keeps `content_html` up to date and that is what any renderer needs. Pagination, headers and footers belong to the export, not to the editor, which is what keeps you out of the worst rabbit hole in the field: ProseMirror has no concept of a page, and paginating on screen means measuring heights by hand.

## What this package does not do

- **It is not an editor.** It stores whatever document JSON you give it. The editor, its nodes and its toolbar are yours.
- **It does not know your domain.** Clause numbering, legal citations, a tax id field type: those are extensions you register, the same way Tiptap keeps its core generic.
- **It does not do permissions.** Who can read or edit is the file's business, and Laracrate's `visibility` is a label your application has to enforce.
- **It does not do real-time collaboration.** Yjs can sit on top, but be aware it wants to own the history, so the revisions table would hold its state instead of plain JSON.

## Sponsors

<p>
  <a href="https://kenodo.com"><img src="art/logo-kenodo.png" width="24" alt="Kenodo"></a>&nbsp;<a href="https://kenodo.com">Kenodo</a>&nbsp;&nbsp;&nbsp;&nbsp;
  <a href="https://andorradev.com"><img src="art/logo-andorradev.png" width="24" alt="AndorraDev"></a>&nbsp;<a href="https://andorradev.com">AndorraDev</a>
</p>

## Author

Created by [Edu Lazaro](https://edulazaro.com)

## License

MIT

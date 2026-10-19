<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisions of HTML-only documents. Content written outside the editor (an agent, an
 * import) arrives as HTML and has no JSON until someone opens it, and the version
 * saved before an agent rewrites it must still hold something. A revision keeps its
 * HTML only then; with JSON, the HTML is rebuilt from it as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laracrate_document_revisions', function (Blueprint $table) {
            $table->json('content_json')->nullable()->change();
            $table->longText('content_html')->nullable()->after('content_json');
        });
    }

    public function down(): void
    {
        Schema::table('laracrate_document_revisions', function (Blueprint $table) {
            $table->dropColumn('content_html');
        });
    }
};

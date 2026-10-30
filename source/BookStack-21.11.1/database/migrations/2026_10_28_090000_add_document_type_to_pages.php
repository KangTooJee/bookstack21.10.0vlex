<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDocumentTypeToPages extends Migration
{
    public function up()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('document_type', 16)->default('word')->index();
        });

        // Legacy HTML pages have no recorded import provenance. Keep them in Word.
        // Only IDs cross into PHP; inspect legacy HTML in bounded database updates.
        DB::table('pages')->select('id')->chunkById(500, function ($pages) {
            $ids = $pages->pluck('id')->all();
            DB::table('pages')->whereIn('id', $ids)->whereNotNull('pdf_attachment_id')
                ->update(['document_type' => 'pdf']);
            DB::table('pages')->whereIn('id', $ids)->whereNull('pdf_attachment_id')
                ->where('html', 'like', '%bookstack-excel-content%')
                ->update(['document_type' => 'excel']);
        });
    }

    public function down()
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['document_type']);
            $table->dropColumn('document_type');
        });
    }
}

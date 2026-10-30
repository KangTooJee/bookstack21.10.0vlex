<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddBookFoldersAndUploadedFiles extends Migration
{
    public function up()
    {
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedInteger('parent_book_id')->nullable()->index();
            $table->foreign('parent_book_id')->references('id')->on('books')->onDelete('restrict');
        });
        Schema::create('vlex_book_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('book_id')->index();
            $table->foreign('book_id')->references('id')->on('books')->onDelete('restrict');
            $table->string('name');
            $table->string('path')->unique();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->text('warnings');
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });
        // Serialize structural moves so two concurrent moves cannot form a cycle.
        Schema::create('vlex_book_hierarchy_lock', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('vlex_book_hierarchy_lock')->insert(['id' => 1]);
    }

    public function down()
    {
        Schema::dropIfExists('vlex_book_hierarchy_lock');
        Schema::dropIfExists('vlex_book_files');
        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['parent_book_id']);
            $table->dropColumn('parent_book_id');
        });
    }
}

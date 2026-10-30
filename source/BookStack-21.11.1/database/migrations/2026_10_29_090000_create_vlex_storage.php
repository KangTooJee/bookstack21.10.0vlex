<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVlexStorage extends Migration
{
    public function up()
    {
        Schema::create('vlex_storage_folders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('vlex_storage_folders')->onDelete('restrict');
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });
        Schema::create('vlex_storage_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('folder_id');
            $table->foreign('folder_id')->references('id')->on('vlex_storage_folders')->onDelete('restrict');
            $table->string('name');
            $table->string('path')->unique();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->text('warnings');
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vlex_storage_files');
        Schema::dropIfExists('vlex_storage_folders');
    }
}

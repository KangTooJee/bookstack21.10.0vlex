<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVlexWorkspace extends Migration
{
    public function up()
    {
        Schema::create('vlex_nodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('kind', 12);
            $table->string('name');
            $table->longText('content')->nullable();
            $table->boolean('favourite')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vlex_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('node_id')->index();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vlex_versions');
        Schema::dropIfExists('vlex_nodes');
    }
}

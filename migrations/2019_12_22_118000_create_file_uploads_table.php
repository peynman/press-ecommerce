<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFileUploadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('uploader_id', false, true);
            $table->string('title');
            $table->string('mime');
            $table->string('storage');
            $table->string('path');
            $table->string('filename');
            $table->string('access');
            $table->integer('size', false, true);
            $table->integer('flags', false, true)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploader_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_uploads');
    }
}

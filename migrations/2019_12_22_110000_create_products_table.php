<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
	        $table->bigInteger('author_id', false, true);
	        $table->bigInteger('parent_id', false, true)->nullable();
            $table->string('name');
            $table->string('group')->nullable();
            $table->json('data')->nullable();
            $table->integer('priority')->default(0);
            $table->integer('flags', false, true)->default(0);
            $table->dateTime('publish_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deleted_at', 'publish_at', 'expires_at', 'parent_id', 'group']);

	        $table->foreign('author_id')->references('id')->on('users');
	        $table->foreign('parent_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}

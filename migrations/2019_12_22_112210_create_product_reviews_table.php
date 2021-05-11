<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('author_id', false, true);
            $table->bigInteger('product_id', false, true);
            $table->text('description');
            $table->integer('stars', false, false)->default(0);
            $table->integer('flags', false, true)->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                [
                    'deleted_at',
                    'created_at',
                    'updated_at',
                    'author_id',
                    'product_id',
                    'flags',
                    'stars',
                ],
                'product_reviews_index'
            );

            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_reviews');
    }
}

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
            $table->bigInteger('author_id', false, true)->nullable();
            $table->bigInteger('product_id', false, true);
            $table->bigInteger('review_id', false, true)->nullable();
            $table->integer('stars', false, false)->default(0);
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->integer('flags', false, true)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                [
                    'deleted_at',
                    'created_at',
                    'updated_at',
                    'author_id',
                    'product_id',
                    'review_id',
                    'flags',
                    'stars',
                ],
                'product_reviews_index'
            );

            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('review_id')->references('id')->on('product_reviews');
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

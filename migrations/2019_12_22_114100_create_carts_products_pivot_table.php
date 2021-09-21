<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartsProductsPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carts_products_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cart_id', false, true);
            $table->bigInteger('product_id', false, true);
            $table->json('data')->nullable();

            $table->foreign('cart_id')->references('id')->on('carts');
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
        Schema::dropIfExists('carts_products_pivot');
    }
}

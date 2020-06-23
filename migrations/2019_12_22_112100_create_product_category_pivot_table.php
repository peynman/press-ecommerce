<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductCategoryPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_category_pivot', function (Blueprint $table) {
        	$table->bigInteger('product_id', false, true);
        	$table->bigInteger('product_category_id', false, true);

        	$table->foreign('product_id')->references('id')->on('products');
        	$table->foreign('product_category_id')->references('id')->on('product_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_category_pivot');
    }
}

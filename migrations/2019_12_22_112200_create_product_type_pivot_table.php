<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductTypePivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('product_type_pivot');
        Schema::create('product_type_pivot', function (Blueprint $table) {
	        $table->bigInteger('product_id', false, true);
	        $table->bigInteger('product_type_id', false, true);

	        $table->foreign('product_id')->references('id')->on('products');
	        $table->foreign('product_type_id')->references('id')->on('product_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_type_pivot');
    }
}

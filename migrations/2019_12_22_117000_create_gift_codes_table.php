<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGiftCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('author_id', false, true);
            $table->string('code');
            $table->decimal('amount', 12, 2);
            $table->integer('currency', false, true);
            $table->integer('status', false, true);
            $table->json('data')->nullable();
            $table->integer('flags', false, true)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['code', 'status']);

            $table->foreign('author_id')->references('id')->on('users');
        });
        Schema::create('gift_codes_use', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id', false, true);
            $table->bigInteger('code_id', false, true);
            $table->bigInteger('cart_id', false, true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['code_id', 'user_id', 'cart_id']);

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('cart_id')->references('id')->on('carts');
            $table->foreign('code_id')->references('id')->on('gift_codes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_codes_use');
        Schema::dropIfExists('gift_codes');
    }
}

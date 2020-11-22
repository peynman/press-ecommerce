<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBankGatewayTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_gateway_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('customer_id', false, true);
            $table->bigInteger('domain_id', false, true);
            $table->bigInteger('bank_gateway_id', false, true);
            $table->bigInteger('cart_id', false, true);
            $table->string('agent_ip')->nullable();
            $table->string('agent_client')->nullable();
            $table->decimal('amount', 12, 2);
            $table->integer('currency', false, true);
            $table->string('reference_code')->nullable();
            $table->string('tracking_code')->nullable();
            $table->integer('status', false, true);
            $table->json('data')->nullable();
            $table->integer('flags', false, true)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('users');
            $table->foreign('domain_id')->references('id')->on('domains');
            $table->foreign('bank_gateway_id')->references('id')->on('bank_gateways');
            $table->foreign('cart_id')->references('id')->on('carts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bank_gateways');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checkout_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_item_id')->nullable()->after('variant_id');
            $table->foreign('cart_item_id')->references('id')->on('cart_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_items', function (Blueprint $table) {
            $table->dropForeign(['cart_item_id']);
            $table->dropColumn('cart_item_id');
        });
    }
};

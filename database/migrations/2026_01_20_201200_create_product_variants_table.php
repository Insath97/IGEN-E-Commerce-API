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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('variant_name')->nullable();
            $table->enum('condition', ['new', 'used', 'refurbished'])->default('new');
            $table->string('sku')->unique();
            $table->string('barcode')->unique()->nullable();
            $table->string('imei')->unique()->nullable();
            $table->string('warranty_period')->nullable();
            $table->string('storage_size');
            $table->string('ram_size');
            $table->string('color');
            $table->decimal('price', 10, 2);
            $table->decimal('sales_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('is_offer')->default(false);
            $table->decimal('offer_price', 10, 2)->nullable();
            $table->boolean('is_trending')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

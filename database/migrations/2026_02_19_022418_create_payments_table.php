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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('checkout_session_id')->nullable()->constrained('checkout_sessions')->onDelete('set null');
            $table->enum('payment_method', [
                'card',
                'cash_on_delivery',
                'bank_transfer'
            ])->default('cash_on_delivery');
            $table->enum('payment_status', [
                'pending',
                'completed',
                'failed',
                'refunded',
                'cancelled'
            ])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->decimal('change_due', 10, 2)->default(0); // For cash payments
            $table->string('currency', 3)->default('LKR');

            // Transaction Details
            $table->string('transaction_id')->nullable()->unique(); // Gateway transaction ID
            $table->string('payment_reference')->nullable(); // Customer reference
            $table->string('gateway_reference')->nullable(); // Gateway reference

            // For Bank Transfers / Slip Uploads
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable(); // Masked for security
            $table->string('account_holder_name')->nullable();
            $table->string('slip_path')->nullable(); // Uploaded slip
            $table->timestamp('transfer_date')->nullable();

            // For Cash on Delivery
            $table->string('delivered_to')->nullable(); // Person who received
            $table->text('delivery_notes')->nullable();

            // Payment Processing
            $table->json('gateway_response')->nullable(); // Store raw gateway response
            $table->string('ip_address')->nullable(); // Customer IP for security
            $table->text('notes')->nullable(); // Additional notes

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

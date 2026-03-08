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
        Schema::create('cms_contents', function (Blueprint $table) {
            $table->id();
            $table->string('page')->index(); // e.g. 'home'
            $table->string('section')->index(); // e.g. 'hero', 'new_arrival'
            $table->string('key')->index(); // e.g. 'title', 'subtitle', 'image'
            $table->text('value')->nullable(); // The actual content (text or image path)
            $table->enum('type', ['text', 'textarea', 'image', 'link'])->default('text');
            $table->string('label')->nullable(); // Human readable label
            $table->timestamps();

            $table->unique(['page', 'section', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_contents');
    }
};

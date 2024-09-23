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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade'); // Foreign key to shops
            $table->foreignId('category_id')->constrained()->onDelete('cascade'); // Foreign key to categories
            $table->string('product_name');
            $table->double('price', 10, 2)->default(0.00);
            $table->string('product_code')->unique(); // Ensure product codes are unique
            $table->string('images')->nullable();
            $table->longText('description')->nullable();
            $table->enum('status', ['approved', 'pending', 'canceled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

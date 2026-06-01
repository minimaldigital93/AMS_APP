<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // basic | pro | max
            $table->string('name');
            $table->decimal('price_usd', 8, 2);
            $table->unsignedInteger('max_floors')->nullable();      // null = unlimited
            $table->unsignedInteger('max_apartments')->nullable();  // null = unlimited
            $table->unsignedInteger('billing_period_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

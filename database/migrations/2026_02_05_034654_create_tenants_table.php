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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->nullable()->constrained('apartments')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('managed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();

            $table->date('move_in_date')->nullable();
            $table->date('move_out_date')->nullable();
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->enum('status', ['active', 'moved_out'])->default('active');
            } else {
                $table->string('status')->default('active');
            }
            $table->decimal('deposit', 10, 2)->default(0);

            $table->string('photo_path')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

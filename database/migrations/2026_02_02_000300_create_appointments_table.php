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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dog_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'starts_at']);
            $table->index(['dog_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

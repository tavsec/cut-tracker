<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->integer('kcal')->nullable();
            $table->integer('protein_g')->nullable();
            $table->integer('carbs_g')->nullable();
            $table->integer('fat_g')->nullable();
            $table->integer('steps')->nullable();
            $table->decimal('sleep_hours', 3, 1)->nullable();
            $table->tinyInteger('hunger')->nullable();
            $table->tinyInteger('energy')->nullable();
            $table->boolean('refeed')->default(false);
            $table->enum('session', ['Push', 'Pull', 'Legs', 'Other'])->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->text('lifts')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->boolean('photos_taken')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('days');
    }
};

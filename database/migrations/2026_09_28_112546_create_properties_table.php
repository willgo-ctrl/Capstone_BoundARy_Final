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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('parcel_reference', 120)->nullable();
            $table->string('survey_plan_reference', 180)->nullable();
            $table->decimal('tie_latitude', 10, 7);
            $table->decimal('tie_longitude', 10, 7);
            $table->json('courses');
            $table->json('boundary');
            $table->decimal('closure_error_meters', 10, 3);
            $table->text('ocr_text')->nullable();
            $table->string('survey_document')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

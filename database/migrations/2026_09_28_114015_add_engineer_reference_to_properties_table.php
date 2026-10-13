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
        Schema::table('properties', function (Blueprint $table) {
            $table->json('reference_boundary')->nullable();
            $table->string('reference_engineer_name', 120)->nullable();
            $table->string('reference_engineer_license', 80)->nullable();
            $table->decimal('reference_deviation_meters', 10, 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'reference_boundary',
                'reference_engineer_name',
                'reference_engineer_license',
                'reference_deviation_meters',
            ]);
        });
    }
};

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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['hospital', 'clinician_group'])->default('clinician_group');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('level')->default(0); // For easier tree traversal
            $table->string('path')->nullable(); // Materialized path for efficient queries
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint for self-referencing
            $table->foreign('parent_id')->references('id')->on('groups')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['parent_id']);
            $table->index(['type']);
            $table->index(['is_active']);
            $table->index(['path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};

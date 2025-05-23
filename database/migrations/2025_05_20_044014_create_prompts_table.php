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
        Schema::create('prompts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('content');
            $table->uuid('next_prompt_id')->nullable();
            $table->uuid('parent_prompt_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('order')->default(0);
            $table->string('type')->default('text');
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreign('next_prompt_id')->references('id')->on('prompts')->nullOnDelete();
            $table->foreign('parent_prompt_id')->references('id')->on('prompts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->nullable()->index();
            $table->string('provider_id')->nullable()->index();
            $table->string('to')->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_messages');
    }
};

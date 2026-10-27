<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('source', 80);
            $table->string('source_key', 80);
            $table->text('notes')->nullable();
            $table->json('catalog')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_profiles');
    }
};

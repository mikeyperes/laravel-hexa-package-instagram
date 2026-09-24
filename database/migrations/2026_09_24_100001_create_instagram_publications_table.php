<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per story or feed post published through the package, so callers can tell whether their
     * item (source_key, e.g. "jpn-event:1979") is already live before publishing it again.
     */
    public function up(): void
    {
        if (Schema::hasTable('instagram_publications')) {
            return;
        }

        Schema::create('instagram_publications', function (Blueprint $table): void {
            $table->id();
            $table->string('profile', 120);
            $table->string('account', 120)->nullable();
            $table->string('kind', 10);
            $table->string('source_key', 191)->nullable();
            $table->string('media_pk', 40)->nullable();
            $table->string('media_code', 40)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('status', 20)->default('live');
            $table->text('caption')->nullable();
            $table->string('image_sha1', 40)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->index(['profile', 'kind', 'source_key']);
            $table->index('media_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_publications');
    }
};

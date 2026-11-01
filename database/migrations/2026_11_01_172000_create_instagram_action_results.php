<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('instagram_action_results', function (Blueprint $table) {
            $table->id(); $table->string('action', 80); $table->string('session', 80);
            $table->string('target', 512); $table->string('mode', 20); $table->string('outcome', 30);
            $table->string('repeat_key', 64)->nullable()->index(); $table->json('record');
            $table->timestamp('performed_at')->index(); $table->timestamps();
            $table->index(['session', 'action', 'performed_at'], 'instagram_result_actor_action');
        });
    }
    public function down(): void { Schema::dropIfExists('instagram_action_results'); }
};

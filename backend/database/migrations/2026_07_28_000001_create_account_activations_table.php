<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('intended_email');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('issuing_actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuance_method', 16);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'club_id']);
            $table->index('expires_at');
            $table->index(['user_id', 'club_id', 'consumed_at', 'revoked_at'], 'account_activations_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activations');
    }
};

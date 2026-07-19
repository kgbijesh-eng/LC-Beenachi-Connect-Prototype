<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('priority', 20)->default('normal');
            $table->string('audience', 30);
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('replaces_announcement_id')->nullable()->constrained('club_announcements')->restrictOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('published_by_role', 30)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('withdrawn_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['club_id', 'status', 'published_at']);
            $table->index(['club_id', 'audience', 'status', 'published_at']);
            $table->index(['club_id', 'priority', 'published_at']);
            $table->index(['club_id', 'expires_at']);
            $table->index(['club_id', 'scheduled_publish_at']);
            $table->index(['club_id', 'updated_at']);
            $table->index(['club_id', 'replaces_announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_announcements');
    }
};

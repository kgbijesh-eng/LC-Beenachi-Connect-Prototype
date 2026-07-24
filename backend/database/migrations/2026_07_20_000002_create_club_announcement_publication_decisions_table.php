<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_announcement_publication_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('club_announcement_id')->constrained('club_announcements')->restrictOnDelete();
            $table->unsignedInteger('announcement_lock_version');
            $table->char('content_fingerprint', 64);
            $table->foreignId('maker_user_id')->constrained('users')->restrictOnDelete();
            $table->string('maker_role', 20);
            $table->string('decision', 20);
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decided_by_role', 20);
            $table->foreignId('supersedes_decision_id')->nullable()
                ->constrained('club_announcement_publication_decisions', indexName: 'club_ann_pub_decisions_supersedes_fk')
                ->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamp('created_at');

            $table->index(['club_id', 'club_announcement_id', 'announcement_lock_version']);
            $table->index(['club_announcement_id', 'decision', 'decided_at']);
            $table->index('content_fingerprint');
            $table->unique('supersedes_decision_id', 'club_ann_pub_decisions_supersedes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_announcement_publication_decisions');
    }
};

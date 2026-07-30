<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('club_announcement_id')->constrained('club_announcements')->restrictOnDelete();
            $table->timestamp('read_at');
            $table->timestamp('created_at');

            $table->unique(
                ['club_id', 'user_id', 'club_announcement_id'],
                'club_announcement_reads_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_announcement_reads');
    }
};

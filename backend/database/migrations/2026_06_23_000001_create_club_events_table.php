<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('venue', 255)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['club_id', 'starts_at']);
            $table->index(['club_id', 'status', 'starts_at']);
            $table->index(['club_id', 'type', 'starts_at']);
            $table->index(['club_id', 'visibility', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_code');
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->date('joined_at')->nullable();
            $table->string('blood_group')->nullable();
            $table->boolean('blood_donor_consent')->default(false);
            $table->timestamps();

            $table->unique(['club_id', 'member_code']);
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'blood_group', 'blood_donor_consent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};

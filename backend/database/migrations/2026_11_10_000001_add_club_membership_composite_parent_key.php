<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_memberships', function (Blueprint $table): void {
            $table->unique(['club_id', 'id'], 'club_memberships_club_id_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('club_memberships', function (Blueprint $table): void {
            $table->dropUnique('club_memberships_club_id_id_unique');
        });
    }
};

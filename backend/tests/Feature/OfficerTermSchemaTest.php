<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OfficerTermTestCase;

class OfficerTermSchemaTest extends OfficerTermTestCase
{
    public function test_requires_valid_same_club_appointments_and_intervals(): void
    {
        $club = $this->club();
        $holder = $this->membership($club);
        $foreign = $this->club();
        foreach ([
            ['club_id' => $foreign->id],
            ['office' => 'other'],
            ['ends_at' => '2027-01-01 00:00:00'],
            ['ended_at' => '2027-01-10 00:00:00'],
            ['ended_at' => '2027-02-02 00:00:00', 'termination_reason' => 'Synthetic', 'termination_reference' => 'END'],
            ['approved_by_user_id' => $holder->user_id, 'confirmed_by_user_id' => $holder->user_id],
            ['recorded_by_user_id' => 999999],
            ['appointment_reference' => ' '],
        ] as $invalid) {
            $this->reject(fn () => DB::table('club_officer_terms')->insert($this->term($holder, $invalid)));
        }
        $this->assertDatabaseCount('club_officer_terms', 0);
    }

    public function test_excludes_overlaps_and_accepts_adjacency_after_an_early_end(): void
    {
        $club = $this->club();
        $holder = $this->membership($club);
        $successor = $this->membership($club);
        $table = DB::table('club_officer_terms');
        $id = $table->insertGetId($this->term($holder, ['appointment_reference' => 'FIRST']));
        $this->reject(fn () => $table->insert($this->term($successor)));
        $this->reject(fn () => $table->insert($this->term($holder, ['office' => 'secretary'])));
        $this->reject(fn () => $table->insert($this->term($successor, [
            'appointment_reference' => 'FIRST', 'office' => 'treasurer',
        ])));
        $table->clone()->where('id', $id)->update([
            'ended_at' => '2027-01-15 00:00:00',
            'termination_reason' => 'Synthetic resignation',
            'termination_reference' => 'END',
        ]);
        $this->reject(fn () => $table->insert($this->term($successor, ['starts_at' => '2027-01-14 23:59:59'])));
        $table->insert($this->term($successor, ['starts_at' => '2027-01-15 00:00:00']));
        $table->insert($this->term($holder, ['office' => 'secretary', 'starts_at' => '2027-01-15 00:00:00']));
        $table->insert($this->term($this->membership($this->club())));
        $this->assertDatabaseCount('club_officer_terms', 4);
    }

    public function test_history_resists_edits_deletion_and_nonempty_schema_rollback(): void
    {
        $holder = $this->membership($this->club());
        $id = DB::table('club_officer_terms')->insertGetId($this->term($holder));
        $term = fn () => DB::table('club_officer_terms')->where('id', $id);
        $this->reject(fn () => $term()->update(['appointment_reference' => 'Changed']));
        $this->reject(fn () => $term()->delete());
        $this->reject(fn () => DB::table('club_memberships')->where('id', $holder->id)->delete());
        $this->reject(fn () => DB::table('club_officer_terms')->truncate());
        if (DB::getDriverName() === 'sqlite') {
            $replacement = $this->term($holder, ['id' => $id, 'starts_at' => '2027-02-01 00:00:00', 'ends_at' => '2027-03-01 00:00:00']);
            $columns = implode(', ', array_keys($replacement));
            $parameters = implode(', ', array_fill(0, count($replacement), '?'));
            $this->reject(fn () => DB::statement("INSERT OR REPLACE INTO club_officer_terms ($columns) VALUES ($parameters)", array_values($replacement)));
        }
        $term()->update(['ended_at' => '2027-01-15 00:00:00', 'termination_reference' => 'END', 'termination_reason' => 'Synthetic']);
        $this->reject(fn () => $term()->update(['ended_at' => '2027-01-10 00:00:00']));
        $this->reject(fn () => $term()->update(['ended_at' => null, 'termination_reference' => null, 'termination_reason' => null]));
        $migration = require database_path('migrations/2026_11_10_000002_create_club_officer_terms_table.php');
        try {
            DB::transaction(fn () => $migration->down());
            $this->fail('Expected a nonempty rollback to be refused.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Refusing to discard officer term history.', $e->getMessage());
        }
        $this->assertDatabaseCount('club_officer_terms', 1);
    }

    public function test_empty_foundation_can_be_rolled_back_without_discarding_memberships(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Retained disposable suites truncate memberships between cases.
            DB::statement('TRUNCATE club_memberships RESTART IDENTITY CASCADE');
        }
        $this->membership($this->club());
        $terms = require database_path('migrations/2026_11_10_000002_create_club_officer_terms_table.php');
        $parentKey = require database_path('migrations/2026_11_10_000001_add_club_membership_composite_parent_key.php');
        DB::transaction(function () use ($terms, $parentKey): void {
            $terms->down();
            $parentKey->down();
        });
        $this->assertFalse(Schema::hasTable('club_officer_terms'));
        $this->assertDatabaseCount('club_memberships', 1);
    }

    private function reject(callable $write): void
    {
        try {
            $write();
            $this->fail('Expected the database to reject the write.');
        } catch (QueryException) {
            $this->assertSame(0, DB::transactionLevel());
        }
    }
}

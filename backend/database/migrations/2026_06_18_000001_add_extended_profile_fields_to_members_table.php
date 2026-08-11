<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('salutation')->nullable()->after('full_name');
            $table->string('first_name')->nullable()->after('salutation');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->date('birth_date')->nullable()->after('last_name');
            $table->string('occupation')->nullable()->after('birth_date');
            $table->string('spouse_name')->nullable()->after('occupation');
            $table->date('wedding_anniversary')->nullable()->after('spouse_name');
            $table->string('gender', 32)->nullable()->after('wedding_anniversary');
            $table->string('membership_type')->nullable()->after('gender');
            $table->string('membership_category')->nullable()->after('membership_type');
            $table->string('membership_program')->nullable()->after('membership_category');
            $table->string('membership_sponsor_name')->nullable()->after('membership_program');
            $table->string('home_phone')->nullable()->after('phone');
            $table->string('alternate_phone')->nullable()->after('home_phone');
            $table->string('address_line_1')->nullable()->after('alternate_phone');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city_locality')->nullable()->after('address_line_2');
            $table->string('district')->nullable()->after('city_locality');
            $table->string('state')->nullable()->after('district');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('country')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn([
                'salutation',
                'first_name',
                'middle_name',
                'last_name',
                'birth_date',
                'occupation',
                'spouse_name',
                'wedding_anniversary',
                'gender',
                'membership_type',
                'membership_category',
                'membership_program',
                'membership_sponsor_name',
                'home_phone',
                'alternate_phone',
                'address_line_1',
                'address_line_2',
                'city_locality',
                'district',
                'state',
                'postal_code',
                'country',
            ]);
        });
    }
};

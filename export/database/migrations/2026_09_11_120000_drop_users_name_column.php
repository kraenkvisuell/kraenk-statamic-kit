<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's default users table has a NOT NULL `name` column, but Statamic's
 * eloquent user stores `first_name`/`last_name` (resources/blueprints/user.yaml)
 * and never writes `name` – so creating a user in the CP failed with a
 * not-null violation. Nothing reads the column; it is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
        });
    }
};

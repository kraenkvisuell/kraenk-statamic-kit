<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statamic users keep their file-based UUIDs when imported into the database
 * (App\Models\User uses HasUuids), so the integer ids from the skeleton's users
 * migration and the pivot tables from `please auth:migration` become uuid columns.
 * Runs on empty tables only – it recreates the columns instead of casting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException('The users table must be empty to switch its ids to uuid.');
        }

        foreach (['role_user', 'group_user'] as $pivot) {
            Schema::table($pivot, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->index();
        });

        foreach (['role_user', 'group_user'] as $pivot) {
            Schema::table($pivot, function (Blueprint $table) {
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['role_user', 'group_user'] as $pivot) {
            Schema::table($pivot, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->id()->first();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index();
        });

        foreach (['role_user', 'group_user'] as $pivot) {
            Schema::table($pivot, function (Blueprint $table) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            });
        }
    }
};

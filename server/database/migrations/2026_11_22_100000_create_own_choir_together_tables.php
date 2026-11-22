<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A choir with friends (docs/choir-together-plan.md, behind own_choir_together): a song's members and its invites; a
 * friend's render is a job on the owner's song (parent_request_id), only their parts (only_parts), charged to the
 * owner (bill_to_user_id), held while the owner hasn't the minutes; a share's friend parts, with the name only if they
 * chose to show it. Guarded; safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('own_choir_members')) {
            Schema::create('own_choir_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('own_choir_request_id')->constrained('own_choir_requests')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->json('parts');
                $table->unsignedInteger('consent_version');
                $table->char('words_sha', 64)->nullable();
                $table->boolean('show_name')->default(false);
                $table->timestamp('joined_at');
                $table->timestamp('seen_at')->nullable();
                $table->timestamps();
                $table->unique(['own_choir_request_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('own_choir_invites')) {
            Schema::create('own_choir_invites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('own_choir_request_id')->constrained('own_choir_requests')->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->json('parts');
                $table->timestamp('expires_at');
                $table->foreignId('used_by_member_id')->nullable()->constrained('own_choir_members')->nullOnDelete();
                $table->timestamp('used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_requests', 'parent_request_id')) {
                $table->foreignId('parent_request_id')->nullable()->after('id')->constrained('own_choir_requests')->cascadeOnDelete();
                $table->foreignId('own_choir_member_id')->nullable()->after('parent_request_id')->constrained('own_choir_members')->cascadeOnDelete();
                $table->foreignId('bill_to_user_id')->nullable()->after('own_choir_member_id')->constrained('users')->nullOnDelete();
                $table->json('only_parts')->nullable()->after('part_key');
                $table->boolean('held_for_minutes')->default(false)->after('starts_after');
            }
        });

        Schema::table('harmonies', function (Blueprint $table): void {
            if (! Schema::hasColumn('harmonies', 'own_voice_friends')) {
                $table->json('own_voice_friends')->nullable()->after('own_voice_parts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table): void {
            if (Schema::hasColumn('harmonies', 'own_voice_friends')) {
                $table->dropColumn('own_voice_friends');
            }
        });

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('own_choir_requests', 'parent_request_id')) {
                $table->dropConstrainedForeignId('parent_request_id');
                $table->dropConstrainedForeignId('own_choir_member_id');
                $table->dropConstrainedForeignId('bill_to_user_id');
                $table->dropColumn(['only_parts', 'held_for_minutes']);
            }
        });

        Schema::dropIfExists('own_choir_invites');
        Schema::dropIfExists('own_choir_members');
    }
};

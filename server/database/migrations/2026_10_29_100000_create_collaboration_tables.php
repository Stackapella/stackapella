<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Making your own, together (contract/api.md, M7): templates people make, the creator's own
    // recorded guides, friends on a project, and choir groups. Only new tables and nullable
    // columns, so it's safe on the live database.
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Null for our own songs; a person's template keeps the UUID its device gave it in `slug`.
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('visibility', 16)->nullable();
            $table->boolean('library_opt_in')->default(false);
            $table->timestamp('deleted_at')->nullable();

            $table->index(['owner_id', 'deleted_at']);
        });

        // A creator's own recorded guide (Plus): one current per part of a template.
        Schema::create('template_recordings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 40);
            $table->string('status', 16);
            $table->string('failure', 24)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->integer('offset_frames');
            $table->unsignedBigInteger('upload_bytes');
            $table->char('sha256', 64);
            $table->string('upload_key');
            $table->string('file_key')->nullable();
            $table->unsignedBigInteger('stored_bytes')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'part_key', 'status']);
        });

        // Who made a part (null: the project's owner) and who sang a take. A take's user_id stays
        // the project's owner, whose account it counts toward and syncs to.
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('takes', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'updated_at']);
        });

        // Who left a project, or was removed from it: so their app can say which, not "deleted".
        Schema::create('project_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('removed');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['project_id', 'user_id']);
        });

        // Links people send themselves: only a hash of the token is kept.
        Schema::create('project_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('part_key', 40)->nullable();
            $table->string('part_name', 60)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'used_at', 'revoked_at']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('director_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->char('link_hash', 64)->nullable()->unique();
            $table->timestamp('link_created_at')->nullable();
            $table->timestamps();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('group_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'template_id']);
            $table->index('template_id');
        });

        Schema::create('group_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 40);
            $table->timestamps();

            $table->unique(['group_song_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_assignments');
        Schema::dropIfExists('group_songs');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('project_invites');
        Schema::dropIfExists('project_exits');
        Schema::dropIfExists('project_members');
        Schema::table('takes', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by'));
        Schema::table('parts', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by'));
        Schema::dropIfExists('template_recordings');
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'deleted_at']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['visibility', 'library_opt_in', 'deleted_at']);
        });
    }
};

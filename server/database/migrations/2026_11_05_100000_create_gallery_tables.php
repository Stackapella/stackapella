<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // N1, the gallery (docs/backend-plan.md D6; contract/api.md, "The gallery (N1)"): public
    // profiles, harmonies listed in the gallery, likes, follows, blocks, comments, friends'
    // consent, and the bell. New tables and nullable columns: safe on the live database, and
    // nothing shows until the gallery flag opens it.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('public_profile_at')->nullable()->after('bio');
        });

        Schema::table('harmonies', function (Blueprint $table) {
            $table->timestamp('rights_confirmed_at')->nullable()->after('gallery_opt_in_at');
            $table->timestamp('gallery_held_at')->nullable()->after('rights_confirmed_at');
            $table->timestamp('featured_at')->nullable()->after('gallery_held_at');
            $table->timestamp('comments_off_at')->nullable()->after('featured_at');
            $table->unsignedInteger('likes_count')->default(0)->after('comments_off_at');
            $table->unsignedInteger('comments_count')->default(0)->after('likes_count');
            $table->index(['gallery_opt_in_at', 'visibility']);
            $table->index('featured_at');
        });

        Schema::create('harmony_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harmony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['harmony_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['follower_id', 'followee_id']);
            $table->index('followee_id');
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harmony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            // Held by the word lists until Hillel looks; hidden by a moderator.
            $table->timestamp('held_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index(['harmony_id', 'id']);
            $table->index('held_at');
        });

        Schema::create('harmony_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harmony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['harmony_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('bell_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('harmony_id')->nullable()->constrained()->cascadeOnDelete();
            // Likes are grouped: one item per harmony a day, with how many more.
            $table->string('group_key', 80)->nullable();
            $table->unsignedInteger('others')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'group_key']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_items');
        Schema::dropIfExists('harmony_consents');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('harmony_likes');
        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropIndex(['gallery_opt_in_at', 'visibility']);
            $table->dropIndex(['featured_at']);
            $table->dropColumn(['rights_confirmed_at', 'gallery_held_at', 'featured_at', 'comments_off_at', 'likes_count', 'comments_count']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('public_profile_at');
        });
    }
};

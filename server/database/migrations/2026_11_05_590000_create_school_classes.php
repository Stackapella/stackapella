<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school offer (docs/school-offer-plan.md): a teacher's class codes, each student's free year
 * of Plus from one, and the user's school Plus date that App\Support\Usage\Plans reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->id();
            // A deleted teacher's codes stay for Admin, and stop taking students.
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('code', 8)->unique();
            $table->unsignedSmallInteger('seats');
            // Seats used, counted at each redemption: a student's account deleted later keeps its seat used.
            $table->unsignedSmallInteger('used')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by', 8)->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'closed_at']);
        });

        Schema::create('school_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            // One school year per account, ever; gone with the account.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('plus_until');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('school_plus_until')->nullable()->after('store_plus_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('school_plus_until'));
        Schema::dropIfExists('school_redemptions');
        Schema::dropIfExists('school_classes');
    }
};

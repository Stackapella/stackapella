<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email addresses compare byte for byte (docs/security-review.md): the database's default
 * collation ignores accents and width, so "victim@exämple.com" found victim@example.com's account,
 * and a reset or sign-in link could be mailed to a look-alike domain. Addresses are stored
 * lowercased (every way in lowercases them), so exact is what was always meant.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['users', 'email_tokens', 'sign_in_links', 'password_reset_tokens', 'email_suppressions', 'beta_singers', 'support_messages', 'mudeo_contacts'];

    public function up(): void
    {
        $this->collate('utf8mb4_bin');
    }

    public function down(): void
    {
        $this->collate('utf8mb4_unicode_ci');
    }

    private function collate(string $collation): void
    {
        foreach (self::TABLES as $table) {
            $column = DB::selectOne('select column_type as type, is_nullable as nullable from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?', [$table, 'email']);

            if ($column === null) {
                continue;
            }

            DB::statement(sprintf('alter table `%s` modify `email` %s character set utf8mb4 collate %s %s', $table, $column->type, $collation, $column->nullable === 'YES' ? 'null' : 'not null'));
        }
    }
};

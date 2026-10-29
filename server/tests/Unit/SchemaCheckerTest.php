<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Contract\SchemaChecker;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The contract's checker fails when it should (docs/parallel-agents-plan.md, "Verification").
 */
final class SchemaCheckerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/schemas-'.uniqid());
        File::ensureDirectoryExists($this->directory);
        File::put($this->directory.'/thing.json', (string) json_encode([
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'role', 'at', 'parts'],
            'properties' => [
                'id' => ['type' => 'string'],
                'role' => ['type' => 'string', 'enum' => ['admin', 'editor']],
                'at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'parts' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/part']],
                'link' => ['type' => 'string', 'format' => 'uri'],
                'count' => ['type' => 'integer'],
                'other' => ['$ref' => 'other.json'],
            ],
            '$defs' => ['part' => ['type' => 'object', 'required' => ['key'], 'properties' => ['key' => ['type' => 'string']]]],
        ]));
        File::put($this->directory.'/other.json', (string) json_encode(['type' => 'number']));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function check(string $json): array
    {
        return (new SchemaChecker($this->directory))->check(json_decode($json, false), 'thing.json');
    }

    public function test_a_matching_value_passes(): void
    {
        $this->assertSame([], $this->check('{"id":"a","role":"admin","at":"2026-10-26T14:05:00Z","parts":[{"key":"alto"}],"link":"https://x.test/a","count":3,"other":1.5}'));
        $this->assertSame([], $this->check('{"id":"a","role":"editor","at":null,"parts":[]}'));
    }

    public function test_a_missing_field_fails(): void
    {
        $this->assertSame(['$: missing the required field "role"'], $this->check('{"id":"a","at":null,"parts":[]}'));
    }

    public function test_an_extra_field_fails(): void
    {
        $this->assertSame(['$: the field "secret" isn\'t in the schema'], $this->check('{"id":"a","role":"admin","at":null,"parts":[],"secret":1}'));
    }

    public function test_a_wrong_type_fails(): void
    {
        $this->assertSame(['$.id: expected string, got integer'], $this->check('{"id":7,"role":"admin","at":null,"parts":[]}'));
        $this->assertSame(['$.count: expected integer, got number'], $this->check('{"id":"a","role":"admin","at":null,"parts":[],"count":1.5}'));
        $this->assertSame(['$.parts: expected array, got object'], $this->check('{"id":"a","role":"admin","at":null,"parts":{}}'));
    }

    public function test_an_enum_and_formats_are_checked(): void
    {
        $this->assertCount(1, $this->check('{"id":"a","role":"owner","at":null,"parts":[]}'));
        $this->assertCount(1, $this->check('{"id":"a","role":"admin","at":"2026-10-26 14:05","parts":[]}'));
        $this->assertCount(1, $this->check('{"id":"a","role":"admin","at":null,"parts":[],"link":"not a link"}'));
    }

    public function test_refs_inside_the_file_and_to_other_files_are_followed(): void
    {
        $this->assertSame(['$.parts[0]: missing the required field "key"'], $this->check('{"id":"a","role":"admin","at":null,"parts":[{}]}'));
        $this->assertSame(['$.other: expected number, got string'], $this->check('{"id":"a","role":"admin","at":null,"parts":[],"other":"x"}'));
    }

    public function test_a_keyword_outside_the_subset_is_a_lint_problem(): void
    {
        File::put($this->directory.'/bad.json', (string) json_encode(['type' => 'string', 'minLength' => 3]));

        $this->assertSame(['bad.json #: "minLength" isn\'t in the contract\'s JSON Schema subset'], (new SchemaChecker($this->directory))->lint('bad.json'));
    }
}

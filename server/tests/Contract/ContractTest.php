<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Support\Contract\SchemaChecker;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * The contract checked from the backend's side (docs/parallel-agents-plan.md, "Testing the
 * contract from both sides"): the schemas use only the subset, every example passes its schema,
 * the API's routes and the operations agree both ways, and every operation has a feature test.
 */
final class ContractTest extends TestCase
{
    private function checker(): SchemaChecker
    {
        return new SchemaChecker(Contract::directory().'/schemas');
    }

    public function test_every_schema_uses_only_the_subset(): void
    {
        foreach (File::files(Contract::directory().'/schemas') as $file) {
            $this->assertSame([], $this->checker()->lint($file->getFilename()), $file->getFilename());
        }
    }

    public function test_every_example_passes_its_schema_and_is_listed(): void
    {
        $listed = [];

        foreach (Contract::operations() as $name => $operation) {
            $this->assertNotEmpty($operation['cases'], "{$name} has no response cases in contract/api.md.");

            foreach ($operation['cases'] as $case) {
                if ($case['status'] === 204) {
                    $this->assertNull($case['example'], "{$name}: a 204 has no body, so no example.");

                    continue;
                }

                $this->assertNotNull($case['example'], "{$name} {$case['status']}: every case has an example.");
                $this->assertNotNull($case['schema'], "{$name} {$case['status']}: every case has a schema.");
                $expected = $name.'.'.($case['status'] < 400 ? $case['status'] : $case['code']).'.json';
                $this->assertSame($expected, $case['example'], "{$name}: examples are named <operation>.<status or code>.json.");

                $path = Contract::directory().'/examples/'.$case['example'];
                $this->assertFileExists($path);
                $errors = $this->checker()->check(json_decode((string) file_get_contents($path), false), (string) $case['schema']);
                $this->assertSame([], $errors, "{$case['example']} doesn't pass {$case['schema']}:\n".implode("\n", $errors));

                if ($case['status'] >= 400) {
                    $this->assertSame($case['code'], json_decode((string) file_get_contents($path), false)->code ?? null, "{$case['example']}: its code is the case's.");
                }

                $listed[] = $case['example'];
            }
        }

        foreach (File::files(Contract::directory().'/examples') as $file) {
            $this->assertContains($file->getFilename(), $listed, "{$file->getFilename()} isn't a case in contract/api.md.");
        }
    }

    public function test_every_api_route_is_an_operation_and_every_operation_has_a_route(): void
    {
        $operations = Contract::operations();
        $routes = [];

        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $name = (string) $route->getName();
            $this->assertArrayHasKey($name, $operations, "The route /{$route->uri()} is named [{$name}], which isn't an operation in contract/api.md.");

            $method = collect($route->methods())->reject(fn (string $m): bool => $m === 'HEAD')->first();
            $path = '/'.$route->uri();
            // Placeholders are named for people ({id}) in the contract and for binding ({block}) in routes.
            $placeholders = static fn (string $path): string => (string) preg_replace('/\{[^}]+\}/', '{}', $path);
            $this->assertSame($operations[$name]['method'].' '.$placeholders($operations[$name]['path']), $method.' '.$placeholders($path), "{$name}: the route and contract/api.md disagree.");
            $routes[] = $name;
        }

        foreach (array_keys($operations) as $name) {
            $this->assertContains($name, $routes, "The operation {$name} has no route in routes/api.php.");
        }
    }

    /**
     * Limited by what they do, not by every call, so the action counts: becoming unlisted
     * (`shares`, ShareGate), a new video (`share-videos`, StartShareVideo), a Plus song's notes a day
     * (`plus-downloads`, TemplatesController).
     */
    private const LIMITED_INSIDE = ['harmonies.update', 'videos.create', 'templates.show'];

    public function test_every_operation_the_contract_says_is_limited_has_a_limit_of_its_own(): void
    {
        $operations = Contract::operations();
        $unlimited = [];

        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $name = (string) $route->getName();
            $limited = array_filter($operations[$name]['cases'] ?? [], static fn (array $case): bool => $case['status'] === 429);

            if ($limited === [] || in_array($name, self::LIMITED_INSIDE, true)) {
                continue;
            }

            // The api group's backstop (throttle:api) is everyone's; a 429 in the contract means one of its own.
            $own = array_filter($route->gatherMiddleware(), static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'throttle:') && $m !== 'throttle:api');

            if ($own === []) {
                $unlimited[] = $name;
            }
        }

        $this->assertSame([], $unlimited, 'contract/api.md says these answer 429, but their routes have no limiter of their own.');
    }

    public function test_every_operation_has_a_feature_test_that_checks_the_contract(): void
    {
        $tests = collect(File::allFiles(base_path('tests/Feature')))->map(fn ($file): string => $file->getContents())->implode("\n");

        foreach (array_keys(Contract::operations()) as $name) {
            // The response may be a call with its own commas: (?1) matches balanced parentheses.
            $this->assertMatchesRegularExpression('/assertMatchesContract\((?:[^(),]|(\((?:[^()]|(?1))*\)))+,\s*\''.preg_quote($name, '/').'\'/', $tests, "No feature test calls assertMatchesContract(..., '{$name}', ...).");
        }
    }

    public function test_every_official_template_passes_the_template_schema(): void
    {
        $files = [
            ...glob(dirname(base_path()).'/web/templates/*.json') ?: [],
            ...glob(database_path('templates/*.json')) ?: [],
        ];
        $files = array_values(array_filter($files, static fn (string $path): bool => basename($path) !== 'index.json'));

        $this->assertNotEmpty($files);

        foreach ($files as $path) {
            $errors = $this->checker()->check(json_decode((string) file_get_contents($path), false), 'template.json');
            $this->assertSame([], $errors, basename($path)." doesn't pass template.json:\n".implode("\n", $errors));
        }
    }
}

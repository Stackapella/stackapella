<?php

declare(strict_types=1);

namespace Tests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;
use Throwable;

/**
 * Every request body the app builds as a plain object in web/js/api.js (`call('op', { body: { a, b: c } })`) carries
 * every field that operation's FormRequest requires, so a field the app drops (Checkout's `trial`, 2026-11-21) fails
 * here, not in production. Bodies the app builds elsewhere (`body: fields`) are the flows' and the contract's.
 */
final class AppRequestBodiesTest extends TestCase
{
    public function test_every_body_the_app_builds_carries_what_the_server_requires(): void
    {
        $source = (string) file_get_contents(base_path('../web/js/api.js'));
        preg_match_all("/call\\('([a-zA-Z.]+)',\\s*\\{[^\\n;]*?body:\\s*\\{([^{}\\n]*)\\}/", $source, $calls, PREG_SET_ORDER);
        $this->assertNotEmpty($calls, 'No bodies found in api.js: the pattern needs updating.');

        $missing = [];
        $checked = 0;

        foreach ($calls as [, $operation, $literal]) {
            if (str_contains($literal, '...')) {
                continue;
            }

            $sent = array_values(array_filter(array_map(static fn (string $field): string => trim((string) preg_replace('/[:\s].*$/s', '', trim($field)), "'\""), explode(',', $literal))));
            $rules = $this->requiredFields($operation);

            if ($rules === null) {
                continue;
            }

            $checked++;

            foreach (array_diff($rules, $sent) as $field) {
                $missing[] = "{$operation}: the server requires `{$field}`, the app sends ".json_encode($sent);
            }
        }

        $this->assertGreaterThan(20, $checked, 'Too few bodies checked: the pattern or the routes changed.');
        $this->assertSame([], $missing);
    }

    /**
     * The top-level fields an operation's FormRequest marks plainly `required`, or null when it has none to read.
     *
     * @return list<string>|null
     */
    private function requiredFields(string $operation): ?array
    {
        $route = RouteFacade::getRoutes()->getByName($operation);

        if (! $route instanceof Route || ! is_string($route->getActionName()) || ! str_contains($route->getActionName(), '@')) {
            return null;
        }

        [$controller, $method] = explode('@', $route->getActionName());
        $request = null;

        foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                $request = $type->getName();
            }
        }

        if ($request === null) {
            return null;
        }

        try {
            /** @var FormRequest $form */
            $form = $request::createFrom(Request::create('/', 'POST'));
            $form->setContainer($this->app)->setRedirector($this->app->make('redirect'));
            $rules = (array) $form->rules();
        } catch (Throwable) {
            // Its rules need a signed-in person or a route's model: the flows cover those.
            return null;
        }

        $required = [];

        foreach ($rules as $field => $rule) {
            $list = is_string($rule) ? explode('|', $rule) : (array) $rule;

            if (is_string($field) && ! str_contains($field, '.') && in_array('required', $list, true)) {
                $required[] = $field;
            }
        }

        return $required;
    }
}

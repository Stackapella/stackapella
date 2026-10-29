<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Enums\SubmissionStatus;
use App\Jobs\BuildExport;
use App\Models\BetaSubmission;
use App\Models\DataExport;
use App\Models\TemplateGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Hillel's rule: what a singer is paid (the hourly rate, what's owed, paid and payable, the dates)
 * is the admin's alone, never shown to the singer in the app.
 */
final class NoMoneyForSingersTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    // Money, and the agreed minutes (Hillel: the singer doesn't see those either).
    private const MONEY = '/hourly|owed|paid|payable|payment|minute/i';

    private function assertNoMoney(TestResponse $response, string $what): void
    {
        $keys = [];
        $walk = function (mixed $value) use (&$walk, &$keys): void {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if (is_string($key)) {
                        $keys[] = $key;
                    }
                    $walk($item);
                }
            }
        };
        $walk($response->json());

        $this->assertSame([], array_values(array_filter($keys, static fn (string $key): bool => preg_match(self::MONEY, $key) === 1)), "{$what} shows a singer money.");
    }

    public function test_no_singer_facing_answer_carries_money_even_for_a_paid_singer(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $singer->forceFill(['hourly_rate_cents' => 4500, 'currency' => 'USD'])->save();
        $submission = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted, 'payable_cents' => 1875, 'paid_at' => now()]);
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'beta_submission_id' => $submission->id]);

        $this->asSinger($singer);
        $this->assertNoMoney($this->getJson('/api/beta/me'), 'beta.me.show');
        $this->assertNoMoney($this->getJson('/api/beta/parts'), 'beta.parts.list');
        $this->assertNoMoney($this->putJson('/api/beta/credit', ['creditMode' => 'name']), 'beta.credit.update');
        $this->assertNoMoney($this->postJson('/api/beta/consent', ['version' => (int) config('beta.agreement_version'), 'adult' => true, 'creditMode' => 'name']), 'beta.consent.create');
        $this->assertNoMoney($this->postJson('/api/beta/feedback', ['text' => 'A note']), 'beta.feedback.create');
        $this->assertNoMoney($this->postJson('/api/beta/invites/'.Str::random(40).'/accept'), 'beta.invite.accept');
        $this->assertNoMoney($this->getJson('/api/account'), 'account.show');
        $this->assertNoMoney($this->getJson('/api/templates'), 'templates.catalogue');
        $this->assertNoMoney($this->getJson('/api/templates/amazing-grace'), 'templates.show');
    }

    public function test_singers_and_editors_get_no_admin_singer_route(): void
    {
        $this->catalogue();
        [$singer] = $this->singerWithAlto();
        $routes = collect(RouteFacade::getRoutes()->getRoutes())->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/admin/beta/singers'));
        $this->assertGreaterThanOrEqual(7, $routes->count());

        foreach ([User::query()->findOrFail($singer->user_id), User::factory()->create()] as $user) {
            $this->actingAs($user);

            foreach ($routes as $route) {
                $uri = '/'.str_replace('{singer}', $singer->sqid(), $route->uri());
                $this->json($route->methods()[0], $uri)->assertForbidden();
            }
        }

        $this->actingAsEditor();
        foreach ($routes as $route) {
            $this->json($route->methods()[0], '/'.str_replace('{singer}', $singer->sqid(), $route->uri()))->assertForbidden();
        }

        // The beta queue the editor works from says nothing of when a singer was paid.
        $template = $this->catalogue();
        [, $assignment] = $this->singerWithAlto();
        $paid = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted, 'paid_at' => now()]);
        $this->getJson('/api/admin/beta/submissions/'.$paid->sqid())->assertOk()->assertJsonPath('data.paidAt', null);
        $this->actingAsAdmin();
        $this->assertNotNull($this->getJson('/api/admin/beta/submissions/'.$paid->sqid())->assertOk()->json('data.paidAt'));
    }

    public function test_a_singers_own_payments_are_in_their_own_export_only(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted, 'payable_cents' => 1875, 'paid_at' => now()]);
        $export = new DataExport(['status' => 'preparing']);
        $export->forceFill(['user_id' => $singer->user_id])->save();

        (new BuildExport($export->id))->handle();

        $zip = new \ZipArchive;
        $path = (string) tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, (string) Storage::disk('private')->get((string) $export->refresh()->file_key));
        $zip->open($path);
        $beta = (array) json_decode((string) $zip->getFromName('beta-singer.json'), true);
        $zip->close();
        @unlink($path);

        $this->assertSame(1875, $beta['parts'][0]['takes'][0]['payableCents'] ?? null);
    }
}

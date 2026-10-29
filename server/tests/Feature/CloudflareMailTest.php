<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailSuppression;
use App\Models\User;
use App\Notifications\Admin\AdminSignedIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

final class CloudflareMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'cloudflare', 'services.cloudflare_email.account_id' => 'acc123', 'services.cloudflare_email.token' => 'tok']);
    }

    public function test_a_message_goes_to_cloudflare_as_json_with_both_bodies(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'messages' => [], 'result' => ['delivered' => ['admin@example.test'], 'permanent_bounces' => [], 'queued' => []]])]);
        $admin = User::factory()->admin()->create(['name' => 'Hillel']);

        Notification::sendNow($admin, new AdminSignedIn($admin, 'passkey', 'Safari'));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.cloudflare.com/client/v4/accounts/acc123/email/sending/send'
                && $request->hasHeader('Authorization', 'Bearer tok')
                && $request['to'] === ['admin@example.test']
                && $request['from'] === ['address' => config('mail.from.address'), 'name' => 'Stackapella']
                && $request['subject'] === __('admin.mail.signed_in.subject', ['name' => 'Hillel'])
                && str_contains((string) $request['html'], __('admin.mail.signed_in.title'))
                && str_contains((string) $request['text'], "didn't expect this")
                && ! str_contains((string) $request['text'], '&#039;');
        });
    }

    public function test_a_name_with_a_line_break_never_breaks_the_subject_onto_a_new_header(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'messages' => [], 'result' => ['delivered' => ['admin@example.test'], 'permanent_bounces' => [], 'queued' => []]])]);
        $admin = User::factory()->admin()->create(['name' => "Hillel\r\nBcc: someone@example.com"]);

        Notification::sendNow($admin, new AdminSignedIn($admin, 'passkey', 'Safari'));

        Http::assertSent(static fn (Request $request): bool => ! preg_match('/[\r\n]/', (string) $request['subject']) && str_contains((string) $request['subject'], 'Hillel Bcc: someone@example.com'));
    }

    public function test_a_permanent_bounce_is_recorded(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'messages' => [], 'result' => ['delivered' => [], 'permanent_bounces' => ['Admin@example.test'], 'queued' => []]])]);
        $admin = User::factory()->admin()->create();

        Notification::sendNow($admin, new AdminSignedIn($admin, 'passkey', 'Safari'));

        $this->assertTrue(EmailSuppression::isSuppressed('admin@example.test'));
    }

    public function test_a_refusal_is_an_error_so_the_job_retries_or_fails_over(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['code' => 10001, 'message' => 'email.sending.error.invalid_request_schema']], 'result' => null], 400)]);
        $admin = User::factory()->admin()->create();

        $this->expectException(TransportException::class);
        Notification::sendNow($admin, new AdminSignedIn($admin, 'passkey', 'Safari'));
    }
}

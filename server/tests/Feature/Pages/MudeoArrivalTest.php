<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Support\Pages\MudeoArrival;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Who came from mudeo: a ?ref=mudeo link, or the visit's cookie, which both hosts read.
 */
final class MudeoArrivalTest extends TestCase
{
    public function test_a_mudeo_link_or_the_visits_cookie_marks_a_mudeo_singer(): void
    {
        $this->assertTrue(MudeoArrival::is(Request::create('/'), 'mudeo'));
        $this->assertTrue(MudeoArrival::is(Request::create('/', cookies: [MudeoArrival::COOKIE => '1']), null));
        $this->assertFalse(MudeoArrival::is(Request::create('/'), 'hn'));
        $this->assertFalse(MudeoArrival::is(Request::create('/', cookies: [MudeoArrival::COOKIE => 'yes']), null));
    }

    public function test_the_cookie_lasts_the_visit_on_both_hosts_and_holds_no_one(): void
    {
        config(['hosts.pass_domain' => '.stackapella.test']);

        $cookie = MudeoArrival::cookie(Request::create('https://beta.stackapella.test/'));

        $this->assertSame('1', $cookie->getValue());
        $this->assertSame(0, $cookie->getExpiresTime());
        $this->assertSame('.stackapella.test', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        config(['hosts.pass_domain' => null]);
        $this->assertNull(MudeoArrival::cookie(Request::create('/'))->getDomain());
    }
}

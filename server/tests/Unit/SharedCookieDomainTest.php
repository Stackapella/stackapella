<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Http\Hosts;
use PHPUnit\Framework\TestCase;

/** The site and the app share the theme, the mic's name and the pass by one cookie domain. */
final class SharedCookieDomainTest extends TestCase
{
    public function test_the_app_and_the_site_share_their_parent_domain(): void
    {
        $this->assertSame('.stackapella.com', Hosts::sharedCookieDomain('https://app.stackapella.com', 'https://stackapella.com'));
        $this->assertSame('.stackapella.com', Hosts::sharedCookieDomain('https://app.stackapella.com', 'https://beta.stackapella.com'));
    }

    public function test_one_host_or_no_shared_parent_keeps_cookies_on_the_host(): void
    {
        $this->assertNull(Hosts::sharedCookieDomain('https://stackapella.com', 'https://stackapella.com'));
        $this->assertNull(Hosts::sharedCookieDomain('https://app.stackapella.com', null));
        $this->assertNull(Hosts::sharedCookieDomain('https://app.example.com', 'https://other.org'));
        $this->assertNull(Hosts::sharedCookieDomain('http://localhost:8000', 'http://localhost:8001'));
    }
}

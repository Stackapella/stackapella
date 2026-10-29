<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;

/**
 * Passkeys belong to the parent domain (docs/decisions.md, "passkeys on the parent domain"), so an
 * admin's passkey survives the app moving between hosts.
 */
final class PasskeyDomainTest extends TestCase
{
    /**
     * config/passkeys.php as it reads with these settings.
     *
     * @param  array<string, string|null>  $env
     * @return array<string, mixed>
     */
    private function configWith(array $env): array
    {
        $old = [];

        foreach ($env as $key => $value) {
            $old[$key] = $_ENV[$key] ?? null;
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        try {
            return require config_path('passkeys.php');
        } finally {
            foreach ($old as $key => $value) {
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }

    public function test_the_relying_party_is_the_parent_domain_and_every_host_may_use_it(): void
    {
        $live = $this->configWith(['APP_URL' => 'https://app.stackapella.com', 'PUBLIC_URL' => 'https://beta.stackapella.com', 'PASSKEYS_RP_ID' => null]);
        $this->assertSame('stackapella.com', $live['relying_party_id']);
        $this->assertSame(['https://app.stackapella.com', 'https://beta.stackapella.com', 'https://stackapella.com'], $live['allowed_origins']);

        $local = $this->configWith(['APP_URL' => 'https://stackapella.test', 'PUBLIC_URL' => null, 'PASSKEYS_RP_ID' => null]);
        $this->assertSame('stackapella.test', $local['relying_party_id']);
        $this->assertSame(['https://stackapella.test'], $local['allowed_origins']);

        // Set by hand, it wins.
        $this->assertSame('example.org', $this->configWith(['APP_URL' => 'https://app.stackapella.com', 'PASSKEYS_RP_ID' => 'example.org'])['relying_party_id']);
    }
}

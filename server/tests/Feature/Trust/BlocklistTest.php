<?php

declare(strict_types=1);

namespace Tests\Feature\Trust;

use App\Enums\BlockType;
use App\Models\Block;
use App\Support\Trust\Blocklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BlocklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_kind_of_block_matches(): void
    {
        Block::factory()->create(['type' => BlockType::Email, 'value' => 'spam@example.test']);
        Block::factory()->create(['type' => BlockType::EmailDomain, 'value' => 'junk.test']);
        Block::factory()->create(['type' => BlockType::Ip, 'value' => '203.0.113.5']);
        Block::factory()->create(['type' => BlockType::IpRange, 'value' => '198.51.100.0/24']);
        Block::factory()->create(['type' => BlockType::IpRange, 'value' => '2001:db8::/32']);
        Block::factory()->create(['type' => BlockType::CardFingerprint, 'value' => 'fp_123']);
        $blocklist = app(Blocklist::class);

        $this->assertNotNull($blocklist->find(email: 'SPAM@example.test'));
        $this->assertNotNull($blocklist->find(email: 'anyone@mail.junk.test'));
        $this->assertNotNull($blocklist->find(ip: '203.0.113.5'));
        $this->assertNotNull($blocklist->find(ip: '198.51.100.77'));
        $this->assertNotNull($blocklist->find(ip: '2001:db8::42'));
        $this->assertNotNull($blocklist->find(cardFingerprint: 'fp_123'));

        $this->assertNull($blocklist->find(email: 'singer@example.test', ip: '192.0.2.1', cardFingerprint: 'fp_999'));
        $this->assertNull($blocklist->find());
    }

    public function test_ip_spellings_and_ipv6_neighbours_match_and_card_fingerprints_keep_their_case(): void
    {
        Block::factory()->create(['type' => BlockType::Ip, 'value' => '2001:db8::1']);
        Block::factory()->create(['type' => BlockType::CardFingerprint, 'value' => 'AbCdEf123456']);
        $blocklist = app(Blocklist::class);

        $this->assertNotNull($blocklist->find(ip: '2001:0db8:0000::0001'));
        $this->assertNotNull($blocklist->find(ip: '2001:db8::beef'), 'An IPv6 block covers its /64.');
        $this->assertNull($blocklist->find(ip: '2001:db8:0:1::1'));
        $this->assertNull($blocklist->find(cardFingerprint: 'abcdef123456'));
        $this->assertNotNull($blocklist->find(cardFingerprint: 'AbCdEf123456'));
    }

    public function test_an_expired_block_no_longer_blocks(): void
    {
        Block::factory()->create(['type' => BlockType::Ip, 'value' => '203.0.113.5', 'expires_at' => now()->subMinute()]);

        $this->assertNull(app(Blocklist::class)->find(ip: '203.0.113.5'));
    }
}

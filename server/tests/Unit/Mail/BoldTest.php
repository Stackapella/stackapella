<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Support\Mail\Bold;
use Tests\TestCase;

/**
 * Only our own words mark bold: a "*" in a value put into them is printed as it is.
 */
final class BoldTest extends TestCase
{
    public function test_our_words_mark_bold(): void
    {
        $this->assertSame('Tap <strong class="sa-text" style="font-weight:700;color:#211a15;">Start</strong>, then sing.', (string) Bold::line('Tap *Start*, then sing.'));
        $this->assertSame('Tap Start, then sing.', Bold::plain('Tap *Start*, then sing.'));
    }

    public function test_a_star_in_someones_text_stays_a_star(): void
    {
        $reply = Bold::keep('Try *67# first, then 2 * 3 & <b>.');

        $this->assertSame('Try *67# first, then 2 * 3 &amp; &lt;b&gt;.', (string) Bold::line($reply));
        $this->assertSame('Try *67# first, then 2 * 3 & <b>.', Bold::plain($reply));
    }

    public function test_values_in_our_words_are_kept(): void
    {
        $line = Bold::words('account.mail.changed.body', ['email' => 'a*b*c@example.com']);

        $this->assertStringContainsString('a*b*c@example.com', (string) Bold::line($line));
        $this->assertStringNotContainsString('<strong class="sa-text" style="font-weight:700;color:#211a15;">b</strong>', (string) Bold::line($line));
        $this->assertStringContainsString('a*b*c@example.com', Bold::plain($line));
    }
}

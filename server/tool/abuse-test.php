<?php

declare(strict_types=1);

/*
 * The abuse test (docs/abuse-test.md): tries the scripted attacks from docs/trust-and-safety-plan.md
 * against a running Stackapella, from this one machine, and says where each one stopped.
 *
 *   php tool/abuse-test.php http://127.0.0.1:8000 [--email-domain=example.com]
 *
 * Never against production: stackapella.com and app.stackapella.com are refused. It signs no one
 * in and uses no real account: sign-ups use made-up addresses at --email-domain, the guesses go to
 * an address with no account. Afterwards this machine's network is limited on the target for up
 * to an hour (that's the point); locally, `php artisan cache:clear` lifts it.
 *
 * Plain PHP and curl, no dependencies.
 */

const REFUSED_HOSTS = ['stackapella.com', 'www.stackapella.com', 'app.stackapella.com'];

$base = rtrim((string) ($argv[1] ?? ''), '/');
$domain = 'example.com';

foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--email-domain=')) {
        $domain = substr($arg, strlen('--email-domain='));
    }
}

$host = (string) parse_url($base, PHP_URL_HOST);

if ($host === '') {
    fwrite(STDERR, "Usage: php tool/abuse-test.php <base url> [--email-domain=example.com]\n");
    exit(2);
}

if (in_array(strtolower($host), REFUSED_HOSTS, true)) {
    fwrite(STDERR, "Refused: {$host} is production. Run it against the beta domain or locally.\n");
    exit(2);
}

$jar = tempnam(sys_get_temp_dir(), 'abuse-test-');
$run = bin2hex(random_bytes(4));

/**
 * One request with the browser's cookies and XSRF header, as the web app sends it.
 *
 * @param  array<string, mixed>|null  $body
 * @param  list<string>  $headers
 * @return array{0: int, 1: array<string, mixed>}
 */
function call(string $method, string $url, ?array $body, string $jar, array $headers = [], bool $cookies = true): array
{
    $xsrf = $cookies ? xsrf($jar) : null;
    $curl = curl_init($url);
    // Unioned, not spread: spreading renumbers the integer CURLOPT_ keys.
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            ...($xsrf !== null ? ['X-XSRF-TOKEN: '.$xsrf] : []),
            ...$headers,
        ],
    ] + ($cookies ? [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar] : []) + ($body !== null ? [CURLOPT_POSTFIELDS => json_encode($body)] : []));
    $answer = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    $json = is_string($answer) ? json_decode($answer, true) : null;

    return [$status, is_array($json) ? $json : []];
}

function xsrf(string $jar): ?string
{
    foreach (is_file($jar) ? (array) file($jar) : [] as $line) {
        $fields = explode("\t", trim((string) $line));

        if (count($fields) === 7 && $fields[5] === 'XSRF-TOKEN') {
            return urldecode($fields[6]);
        }
    }

    return null;
}

/**
 * Repeats one attempt until it's refused with 429, or $times have gone through.
 *
 * @param  callable(int): array{0: int, 1: array<string, mixed>}  $attempt
 * @return array{stoppedAt: int|null, statuses: array<int, int>}
 */
function until_refused(int $times, callable $attempt): array
{
    $statuses = [];

    for ($n = 1; $n <= $times; $n++) {
        [$status, $json] = $attempt($n);
        $statuses[$status] = ($statuses[$status] ?? 0) + 1;

        if ($status === 429) {
            return ['stoppedAt' => $n, 'statuses' => $statuses, 'code' => $json['code'] ?? null];
        }
    }

    return ['stoppedAt' => null, 'statuses' => $statuses, 'code' => null];
}

// The web app's first call: it sets the XSRF cookie.
[$status] = call('GET', $base.'/api/auth/config', null, $jar);

if ($status !== 200) {
    fwrite(STDERR, "{$base}/api/auth/config answered {$status}: is it running?\n");
    exit(1);
}

$nobody = "nobody-{$run}@{$domain}";
$attacks = [
    // [what, the plan's line, the attempt number it should stop at (null: never refused), how]
    ['50 sign-ups from one network', 'Sign up: 5 an hour per IP', 6, fn () => until_refused(50, fn (int $n) => call('POST', $base.'/api/auth/register', ['name' => 'Abuse Test', 'email' => "signup-{$run}-{$n}@{$domain}", 'password' => 'a-fine-long-passphrase-42', 'ageConfirmed' => true, 'elapsedMs' => 30000], $jar))], // gitleaks:allow (a made-up password for throwaway accounts)
    ['20 resets for one address', 'Password reset: 3 an hour per address, whether or not it has an account', 4, fn () => until_refused(20, fn () => call('POST', $base.'/api/auth/password/forgot', ['email' => $nobody], $jar))],
    ['100 sign-in guesses', 'Sign in: 10 a minute per IP and address', 11, fn () => until_refused(100, fn (int $n) => call('POST', $base.'/api/auth/sign-in', ['email' => $nobody, 'password' => "guess-{$n}-not-it"], $jar))],
    ['40 made-up app tokens', 'Unknown bearer tokens: 30 a minute per network', 31, fn () => until_refused(40, fn (int $n) => call('GET', $base.'/api/account', null, $jar, ['Authorization: Bearer sat_guess'.$run.$n], cookies: false))],
    ['15 resends to different addresses', 'Verification resends: 10 an hour per network, silently (always 202)', null, fn () => until_refused(15, fn (int $n) => call('POST', $base.'/api/auth/verification', ['email' => "resend-{$run}-{$n}@{$domain}"], $jar))],
];

$failed = 0;
echo "The abuse test against {$base} (run {$run})\n\n";

foreach ($attacks as [$what, $line, $expected, $how]) {
    $result = $how();
    $ok = $result['stoppedAt'] === $expected && ($expected === null || $result['code'] === 'rate_limited');
    $failed += $ok ? 0 : 1;
    $seen = implode(', ', array_map(static fn (int $status, int $count): string => "{$count}x {$status}", array_keys($result['statuses']), $result['statuses']));
    $stopped = $result['stoppedAt'] === null ? 'never refused' : "refused at attempt {$result['stoppedAt']}";
    $want = $expected === null ? 'never refused' : "refused at attempt {$expected}";
    printf("%s %s\n    %s: %s (expected %s). Answers: %s.\n", $ok ? 'PASS' : 'FAIL', $what, $line, $stopped, $want, $seen);
}

@unlink($jar);
echo "\n".($failed === 0 ? 'Every attack stopped where the plan says.' : "{$failed} attacks didn't stop where the plan says.")."\n";
echo "Then check by hand (docs/abuse-test.md): the refusals in admin (Trust and safety), and that the mail log holds at most 10 resends.\n";
exit($failed === 0 ? 0 : 1);

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Beta\AcceptInvite;
use App\Http\Controllers\Public\InfoPagesController;
use App\Http\Middleware\SecurityHeaders;
use App\Models\EarlyAccessLink;
use App\Models\Group;
use App\Models\ProjectInvite;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\EarlyAccess;
use App\Support\Http\ApiError;
use App\Support\Http\Hosts;
use App\Support\Web\AppMeta;
use App\Support\Web\AppShell;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PWA on our one origin (docs/architecture.md, "The system"). In production nginx serves its
 * files from /web directly; what reaches Laravel is an app route (/, /p/<id>, /sing, /admin...),
 * answered with index.html. Locally (php artisan serve) this also serves the files themselves.
 */
final class WebAppController extends Controller
{
    /** The app's pages whose address carries a token: an invite, and the links in our emails. */
    private const TOKEN_PAGES = ['beta/*', 'early/*', 'invite/*', 'join/*', 'verify', 'reset', 'email/confirm', 'email/revert', 'notice'];

    /** The folders and files of /web the app is made of. Tests, tools and the spike are not served. */
    private const SERVED = ['js/', 'engine/', 'css/', 'icons/', 'sounds/', 'templates/', 'locales/', 'course/', 'sw.js', 'manifest.webmanifest', 'favicon.ico'];

    private const TYPES = [
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json',
        'webmanifest' => 'application/manifest+json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'wasm' => 'application/wasm',
        // web/sounds: the app's own short sounds.
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
    ];

    public function __construct(private readonly AppShell $shell, private readonly AppMeta $meta) {}

    public function __invoke(Request $request): Response
    {
        $path = ltrim(rawurldecode($request->getPathInfo()), '/');

        if ($request->is('api', 'api/*')) {
            return ApiError::response(404, 'not_found');
        }

        if (($file = $this->file($path)) !== null) {
            return $file;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort(404);
        }

        // A missing file (an old cached link to /js/x.js) is a 404, not the app.
        if (preg_match('#\.(js|css|json|png|svg|ico|webmanifest|wasm|map)$#', $path) === 1) {
            abort(404);
        }

        // The public site's root is always the landing (docs/app-subdomain-plan.md): before
        // launch, to anyone holding the beta pass, coming soon otherwise.
        // Off stackapella.com (an install of the app under the AAL) there's no landing: / is the app.
        if ($path === '' && Hosts::onPublicHost($request) && config('app.is_nexus') === true) {
            return config('app.coming_soon') && ! BetaPass::valid($request)
                ? response()->view('coming-soon', [], 200, ['Cache-Control' => 'no-cache', 'Vary' => 'Cookie'])
                : app(InfoPagesController::class)->landing($request);
        }

        if (config('app.coming_soon') && ! $this->mayEnterBeforeLaunch($request)) {
            return response()->view('coming-soon', [], 200, ['Cache-Control' => 'no-cache']);
        }

        // A first-time visitor at / gets the landing page; anyone who has used the app (its own
        // marker, a session, signing in) or asks for it (?app, the installed app's start) the app.
        // ?landing is the landing for anyone (the app's logo, on one host).
        if ($path === '' && config('app.is_nexus') === true && ! Hosts::split() && ($request->query->has('landing') || $this->firstVisit($request))) {
            return app(InfoPagesController::class)->landing($request);
        }

        $request->attributes->set(SecurityHeaders::APP_PAGE, true);

        $response = response($this->shell->html($this->meta->for($request)), 200, array_filter([
            'Content-Type' => 'text/html; charset=utf-8',
            // Always asked again, and never kept at Cloudflare's edge (it names this version's files).
            'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
            'Cloudflare-CDN-Cache-Control' => 'no-store',
            // A token in the address (an invite, the emails' links): never send it on to another site.
            'Referrer-Policy' => $request->is(...self::TOKEN_PAGES) ? 'no-referrer' : null,
            // The early-access and invite links are only ever handed out: never in a search engine.
            'X-Robots-Tag' => $request->is('early/*', 'invite/*') ? 'noindex, nofollow' : null,
        ]));

        // An open beta invite or early-access link lets its holder into the app from then on, and
        // so does being signed in (signing in from a page, the pass comes with it).
        if (config('app.coming_soon') && ! BetaPass::valid($request) && ($this->opensTheBeta($request) || $request->user() !== null)) {
            $response->headers->setCookie(BetaPass::cookie($request));
        }

        return $response;
    }

    /**
     * Before launch, on the beta domain: the admin, the links that carry a token (an invite, the
     * emails' links, the early-access link), signing in, anyone signed in, and anyone holding the
     * beta pass (given by those, or Hillel's preview link). A session cookie alone, which any API
     * call sets, doesn't open it: only a signed-in one. The shell holds nothing private, so this keeps the unfinished app from looking open
     * to passers-by.
     */
    private function mayEnterBeforeLaunch(Request $request): bool
    {
        // The emails' links, signing in, and anyone signed in: Google and Apple bring people back
        // to an app page before any API call could give the pass (Hillel had to sign in twice).
        if ($request->is('admin', 'admin/*', 'signin', 'signin/link', 'verify', 'reset', 'email/confirm', 'email/revert', 'notice') || BetaPass::valid($request) || $request->user() !== null) {
            return true;
        }

        // An invite's page only while its token still opens something, so a made-up one doesn't.
        $token = hash('sha256', (string) $request->segment(2));

        return $this->opensTheBeta($request)
            || ($request->is('join/*') && ProjectInvite::query()->where('token_hash', $token)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
            || ($request->is('choir/*') && Group::query()->where('link_hash', $token)->exists());
    }

    private function firstVisit(Request $request): bool
    {
        if ($request->query->has('app') || $request->cookies->has('sa_app') || $request->cookies->has((string) config('session.cookie')) || $request->user() !== null) {
            return false;
        }

        foreach (array_keys($request->cookies->all()) as $name) {
            if (str_starts_with((string) $name, 'remember_web_')) {
                return false;
            }
        }

        return true;
    }

    private function opensTheBeta(Request $request): bool
    {
        $token = (string) $request->segment(2);

        return match (true) {
            $request->is('beta/*') => AcceptInvite::opens($token),
            $request->is('early/*') => EarlyAccess::opens($token, EarlyAccessLink::MUDEO),
            $request->is('invite/*') => EarlyAccess::opens($token, EarlyAccessLink::INVITE),
            default => false,
        };
    }

    private function file(string $path): ?BinaryFileResponse
    {
        // No "." or ".." segments (raw or encoded, already decoded here): /js/../tests/ stays out.
        if (preg_match('#(^|/)\.{1,2}(/|$)#', $path) === 1 || str_contains($path, '\\') || str_contains($path, "\0")) {
            return null;
        }

        $prefix = null;
        foreach (self::SERVED as $served) {
            if ($path === $served || (str_ends_with($served, '/') && str_starts_with($path, $served))) {
                $prefix = $served;
            }
        }

        $root = realpath($this->shell->webPath());
        $file = $prefix !== null && $root !== false ? realpath($root.'/'.$path) : false;

        // The resolved file (after any symlink) must still be inside the folder it was asked from.
        $inside = $file !== false && (str_ends_with((string) $prefix, '/') ? str_starts_with($file, $root.'/'.$prefix) : $file === $root.'/'.$prefix);

        if ($file === false || ! $inside || ! is_file($file)) {
            return null;
        }

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => self::TYPES[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            // As .do/nginx.conf: everything revalidates (a cheap 304 by ETag), so a module imported
            // after load is never an older deploy's.
            'Cache-Control' => 'no-cache',
        ]);

        // Every app file (none is fingerprinted): asked again each time, and never kept at
        // Cloudflare's edge, so a page never runs one deploy's module beside another's.
        $response->headers->set('Cache-Control', 'public, no-cache, max-age=0, must-revalidate');
        $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');

        $response->setAutoEtag();

        return $response;
    }
}

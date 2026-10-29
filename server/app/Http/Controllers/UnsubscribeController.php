<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mudeo\MudeoList;
use App\Models\MudeoContact;
use App\Models\User;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The mudeo email's unsubscribe (docs/mudeo-transition-plan.md): its signed link shows one button,
 * and the POST (from it, or the mail provider's one click) suppresses the address for good. The
 * test email's link (`test`) does nothing.
 */
final class UnsubscribeController extends Controller
{
    private const HEADERS = ['X-Robots-Tag' => 'noindex', 'Referrer-Policy' => 'no-referrer'];

    public function show(Request $request, string $contact): Response
    {
        $found = $this->contact($contact);

        return response()->view('pages.unsubscribe', [
            'title' => __('mudeo.unsubscribe.title'),
            'email' => $found?->isForgotten() === false ? $found->email : null,
            'done' => $found?->opted_out_at !== null,
            'action' => $request->fullUrl(),
        ], 200, ['X-Robots-Tag' => 'noindex', 'Referrer-Policy' => 'no-referrer']);
    }

    public function store(Request $request, string $contact, MudeoList $list): Response
    {
        $found = $this->contact($contact);

        if ($found !== null) {
            $list->unsubscribe($found);
        }

        return response()->view('pages.unsubscribe', [
            'title' => __('mudeo.unsubscribe.done_title'),
            'email' => $found?->isForgotten() === false ? $found->email : null,
            'done' => $found !== null,
            'action' => null,
        ], 200, ['X-Robots-Tag' => 'noindex', 'Referrer-Policy' => 'no-referrer']);
    }

    /**
     * Ideas' emails to their writer: the button, and its POST turns them off.
     */
    public function showIdeas(Request $request, string $user): Response
    {
        $found = User::query()->find(Ids::decode($user) ?? 0);

        return response()->view('pages.idea-emails-off', ['title' => __('ideas.unsubscribe.title'), 'done' => $found === null || ! $found->idea_emails, 'action' => $request->fullUrl()], 200, self::HEADERS);
    }

    public function storeIdeas(string $user): Response
    {
        User::query()->whereKey(Ids::decode($user) ?? 0)->update(['idea_emails' => false]);

        return response()->view('pages.idea-emails-off', ['title' => __('ideas.unsubscribe.done_title'), 'done' => true, 'action' => null], 200, self::HEADERS);
    }

    private function contact(string $sqid): ?MudeoContact
    {
        $id = $sqid === 'test' ? null : Ids::decode($sqid);

        return $id !== null ? MudeoContact::query()->find($id) : null;
    }
}

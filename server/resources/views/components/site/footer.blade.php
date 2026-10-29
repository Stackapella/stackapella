{{-- The public site's foot: the mark and its line with our social links, the pages in four labelled
     columns (two on a phone), and the company at the bottom, with the licence's credit once it's
     open source or self-hosted (off the nexus, only the pages that are there). Links are plain words, underlined only
     on hover and focus (docs/ux-spec.md 4.2). --}}
{{-- Four columns of four (Hillel: "the number of options the same in all cols"). Write to us is the
     app's form and Email us is a plain address: two ways in, not one twice. --}}
@php($groups = [
    'product' => ['features' => '/features', 'how' => '/how-it-works', 'pricing' => '/pricing', 'whats_new' => '/whats-new'],
    'help' => ['help' => '/help', 'guides' => '/guides', 'contact_app' => \App\Support\Http\Hosts::app('/contact'), 'email_us' => 'mailto:'.config('site.contact_email')],
    'company' => ['about' => '/about', 'press' => '/press', ...(\App\Support\Site\OpenSource::on() ? ['open_source' => '/open-source', 'selfhost' => '/selfhost'] : [])],
    'legal' => ['terms' => '/legal/terms', 'privacy' => '/legal/privacy', 'guidelines' => '/legal/community-guidelines', 'accessibility' => '/legal/accessibility'],
])
{{-- Off the nexus (a self-hosted install) the marketing pages aren't there (docs/branding-matrix.md), so
     neither are their links: Help (without Guides) and Legal stay. --}}
@php($groups = config('app.is_nexus', true) ? $groups : [...array_diff_key($groups, array_flip(['product', 'company'])), 'help' => array_diff_key($groups['help'], ['guides' => true])])
<footer class="s-wrap s-footer">
    <div class="s-foot-grid">
        <div class="s-foot-brand">
            <a href="/" class="s-brand">{{ \App\Support\Site\Icons::mark(30) }}<span>{{ __('meta.brand') }}</span></a>
            <p>{{ __('pages.nav.tagline') }}</p>
            <div class="s-social">
                @foreach (config('site.social') as $network => $url)
                    @if ($url)
                        <a href="{{ $url }}" class="s-iconbtn" rel="me noopener" aria-label="{{ __('pages.nav.social.'.$network) }}"><x-site.icon :name="$network" size="18" /></a>
                    @endif
                @endforeach
            </div>
        </div>
        <nav class="s-foot-cols" aria-label="{{ __('pages.nav.footer_label') }}">
            @foreach ($groups as $group => $links)
                <div>
                    <h2 class="s-foot-h">{{ __('pages.nav.groups.'.$group) }}</h2>
                    <ul>
                        @foreach ($links as $key => $path)
                            <li><a href="{{ $path }}" class="s-flink">{{ __('pages.nav.'.$key) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </div>
    <p class="s-foot-legal">{{ __('pages.nav.rights', ['year' => now()->year]) }}</p>
    @if (\App\Support\Site\OpenSource::credited())
        {{-- The licence's credit (OpenSource::CREDIT_URL), kept on every install. --}}
        <p class="s-foot-legal" translate="no">{{ __('pages.nav.credit') }} · <a href="{{ \App\Support\Site\OpenSource::CREDIT_URL }}" class="s-flink">{{ \App\Support\Site\OpenSource::creditHost() }}</a></p>
    @endif
</footer>
<script type="module" src="/js/public/site.js"></script>

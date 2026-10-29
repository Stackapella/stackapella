{{-- Text blocks (LegalText: headings, paragraphs, lists, **bold**), escaped, in the site's type. Each
     main heading opens a section with its own id (a contents list links to it), and "The short
     version" stands out as a raised panel. --}}
@props(['blocks'])
<div class="s-prose">
    @php($open = false)
    @foreach ($blocks as $block)
        @if ($block['type'] === 'heading' && ($block['level'] ?? 2) <= 2)
            @if ($open)
                </section>
            @endif
            @php($open = true)
            <section id="{{ \Illuminate\Support\Str::slug(str_replace('**', '', (string) $block['text'])) }}" @class(['s-short' => str_replace('**', '', (string) $block['text']) === __('pages.legal.short')])>
            <h2>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</h2>
        @elseif ($block['type'] === 'heading')
            <h3>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</h3>
        @elseif ($block['type'] === 'list')
            <ul>
                @foreach ($block['items'] ?? [] as $item)
                    <li>{{ \App\Support\Site\Prose::inline((string) $item) }}</li>
                @endforeach
            </ul>
        @else
            <p>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</p>
        @endif
    @endforeach
    @if ($open)
        </section>
    @endif
</div>

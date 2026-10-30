{{-- A course's questions, open one at a time, and the same answers as structured data for search. --}}
<div class="cp-faq">
    @foreach ((array) $page['faq'] as [$question, $answer])
        <details @if ($loop->first) open @endif>
            <summary>{{ $question }}<span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            <p>{{ $answer }}</p>
        </details>
    @endforeach
</div>
@php($faqLd = [['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($qa) => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], (array) $page['faq'])]])
<x-site.json-ld :data="$faqLd" />

{{-- One integration's card ($slug, $item: its words), on the hub and under the other integration's page. --}}
<li>
    <a href="/integrations/{{ $slug }}" class="int-hubcard">
        <span class="int-hub-for">{{ $item['for'] }}</span>
        <b translate="no">{{ $item['name'] }}</b>
        <span class="int-hub-say">{{ $item['summary'] }}</span>
        <span class="int-hub-status"><span class="int-tag int-tag-today">{{ __('site.integrations.today') }}</span><span class="int-tag int-tag-coming">{{ __('site.integrations.coming') }}</span></span>
        <span class="int-hub-go">{{ __('site.integrations.index.open') }} <x-site.icon name="arrow" size="16" /></span>
    </a>
</li>

{{-- One row of /pricing's comparison (the short one and every detail): its label, then Free's and Plus's cells, a tick
     or a dash for yes or no (with the word for a screen reader), Plus's on the card's warm fill. --}}
<tr @if ($row['same']) data-same @endif>
    <th scope="row">{{ $row['label'] }}</th>
    @foreach ($row['cells'] as $i => $cell)
        <td @class(['s-col-plus' => $i === 1, 's-plusmore' => $i === 1 && ! $row['same'] && $cell !== __('pages.pricing.yes')])>
            @if ($cell === __('pages.pricing.yes'))
                <span class="s-yes"><x-site.icon name="check" size="18" /><span class="sr-only">{{ $cell }}</span></span>
            @elseif ($cell === __('pages.pricing.no'))
                <span class="s-no" aria-hidden="true"></span><span class="sr-only">{{ $cell }}</span>
            @else
                {{ $cell }}
            @endif
        </td>
    @endforeach
</tr>

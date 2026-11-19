{{-- Range and registers' section label (site/courses/range-and-registers): the section's note and its hertz (`$marker`,
     from RangeAndRegistersPage::markers), a square below the break and a circle above it, then the label's words (`$kick`)
     if it has any. With `$id` it's the section's heading itself. --}}
@if (isset($id))
    <h2 id="{{ $id }}" class="cp-rg-kick" data-side="{{ $marker['side'] }}"><span class="cp-rg-mark" aria-hidden="true"><i></i>{{ $marker['text'] }}<span class="cp-rg-mark-line"></span></span><span>{{ $kick }}</span></h2>
@else
    <p class="cp-rg-kick" data-side="{{ $marker['side'] }}"><span class="cp-rg-mark" aria-hidden="true"><i></i>{{ $marker['text'] }}@isset($kick)<span class="cp-rg-mark-line"></span>@endisset</span>@isset($kick)<span>{{ $kick }}</span>@endisset</p>
@endif

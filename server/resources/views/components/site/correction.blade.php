{{-- Suggest a correction (Hillel, 2026-11-12: "a way for readers to submit corrections and suggestions";
     docs/corrections-plan.md): a quiet link at the end of a song, part, course, lesson or tool page that opens a sheet
     (a dialog, never the browser's own) with what kind of correction it is, what's wrong, and a source if they have one.
     js/public/correction.js sends it to POST /api/corrections with the page's type, path, title and language, and
     nothing about the reader (no email, no account). The courses lead triages them in Inbox > Corrections. --}}
@props(['type', 'title'])
@php($id = 'correction-'.$type)
<p class="s-fix-open"><button type="button" class="s-lk" data-correction-open="{{ $id }}" aria-haspopup="dialog">{{ __('site.correction.open') }}</button></p>
<dialog id="{{ $id }}" class="s-fix fixed inset-x-0 bottom-0 top-auto m-0 mx-auto max-h-[88svh] w-full max-w-[30rem] overflow-y-auto rounded-t-[24px] bg-panel p-0 text-start text-cream shadow-2xl backdrop:bg-black/50 md:inset-0 md:m-auto md:h-fit md:max-h-[85svh] md:max-w-[34rem] md:rounded-[24px]" aria-labelledby="{{ $id }}-h" data-correction data-type="{{ $type }}" data-title="{{ $title }}" data-path="/{{ ltrim(request()->path(), '/') }}" data-lang="{{ app()->getLocale() }}">
    @php($fixWords = (array) \Illuminate\Support\Facades\Lang::get('site.correction.said'))
    <script type="application/json" data-correction-words>@json($fixWords)</script>
    <form method="dialog" class="s-fix-form" data-correction-form novalidate>
        <h2 id="{{ $id }}-h" class="s-fix-h">{{ __('site.correction.title') }}</h2>
        <p class="s-fix-lead">{{ __('site.correction.lead', ['page' => $title]) }}</p>
        <fieldset class="s-fix-kinds">
            <legend>{{ __('site.correction.kind') }}</legend>
            @foreach (['fact', 'words', 'notes', 'other'] as $kind)
                <label><input type="radio" name="kind" value="{{ $kind }}" @checked($loop->first)> <span>{{ __('site.correction.kinds.'.$kind) }}</span></label>
            @endforeach
        </fieldset>
        <label class="s-fix-field">
            <span>{{ __('site.correction.text') }}</span>
            <textarea name="text" rows="5" maxlength="2000" required aria-describedby="{{ $id }}-text-error"></textarea>
            <span class="s-fix-error" id="{{ $id }}-text-error" data-error="text" hidden></span>
        </label>
        <label class="s-fix-field">
            <span>{{ __('site.correction.source') }} <small>{{ __('site.correction.optional') }}</small></span>
            <input type="url" name="source" inputmode="url" placeholder="https://" aria-describedby="{{ $id }}-source-error">
            <span class="s-fix-error" id="{{ $id }}-source-error" data-error="source" hidden></span>
        </label>
        {{-- Left empty by people; a form filler fills it, and the server refuses it. --}}
        <label class="s-fix-trap" aria-hidden="true"><input type="text" name="nickname" tabindex="-1" autocomplete="off"></label>
        <p class="s-fix-note">{{ __('site.correction.private') }}</p>
        <p class="s-fix-status" role="status" data-correction-status></p>
        <div class="s-fix-go">
            <button type="button" class="btn-secondary" data-correction-cancel>{{ __('site.correction.cancel') }}</button>
            <button type="submit" class="btn-primary" data-correction-send>{{ __('site.correction.send') }}</button>
        </div>
    </form>
</dialog>
@once
    <script type="module" src="/js/public/correction.js"></script>
@endonce

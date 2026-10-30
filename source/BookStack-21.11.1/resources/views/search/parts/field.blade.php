<div class="vlex-search-field">
    <button @if(!empty($headerField)) id="header-search-box-button" @endif type="submit" aria-label="{{ trans('common.search') }}">@icon('search')</button>
    <input @if(!empty($headerField)) id="header-search-box-input" @endif
           @if(!empty($inputRef)) refs="{{ $inputRef }}" @endif
           type="text" name="{{ $inputName ?? 'term' }}" value="{{ $inputValue ?? '' }}"
           aria-label="{{ $inputLabel ?? trans('common.search') }}" placeholder="{{ $inputLabel ?? trans('common.search') }}">
    @include('search.parts.scope-select')
</div>

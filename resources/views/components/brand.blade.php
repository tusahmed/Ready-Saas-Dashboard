@props(['compact' => false, 'name' => null, 'logo' => null])
@php($customBrand = $logo || ($name && strtolower($name) !== 'orbit'))
<span {{ $attributes->class(['brand', 'brand-custom' => $customBrand]) }}>
    @if($logo)<img class="brand-image" src="{{ $logo }}" alt="" width="42" height="42">@else<span class="brand-mark"><x-icon name="orbit" /></span>@endif
    @unless($compact)
        @if($customBrand)<span class="brand-name" title="{{ $name }}">{{ $name }}</span>@else<span class="brand-word">orbit<span class="brand-dot">.</span></span>@endif
    @endunless
</span>

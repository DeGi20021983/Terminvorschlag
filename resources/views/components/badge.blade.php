@props(['variant' => 'accent'])
<span {{ $attributes->class(['badge', 'badge--'.$variant]) }}>{{ $slot }}</span>

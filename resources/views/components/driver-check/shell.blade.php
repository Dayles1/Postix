{{--
    The page frame of the console: a wide, centred column. Every page is one
    <x-driver-check.shell>; the gap between its blocks is set here and nowhere
    else, so no two pages breathe differently.
--}}

<div {{ $attributes->merge(['class' => 'dc-wrap']) }}>
    <div class="flex flex-col gap-5">
        {{ $slot }}
    </div>
</div>

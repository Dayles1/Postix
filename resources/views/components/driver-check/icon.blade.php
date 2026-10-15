@props(['name', 'stroke' => '1.8'])

{{--
    One stroke-based icon set for the whole driver-check panel.

    Everything is drawn on the same 24x24 grid with the same stroke weight,
    which is what keeps a row of buttons looking like a row of buttons.
--}}

@php
    $paths = [
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2M19.419 15H15"/>',

        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>',

        'search' => '<circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/>',

        'close' => '<path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>',

        'filter' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12M10 19h4"/>',

        'sliders' => '<path stroke-linecap="round" d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',

        'chevron-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>',

        'chevron-left' => '<path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/>',

        'chevron-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/>',

        'arrow-up' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0-6 6m6-6 6 6"/>',

        'arrow-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0 6-6m-6 6-6-6"/>',

        'arrow-left' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0 6-6m-6 6 6 6"/>',

        'download' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',

        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',

        'user' => '<circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/>',

        'operator' => '<circle cx="9" cy="8" r="3.25"/><path stroke-linecap="round" d="M3.5 19.5C3.5 16.74 5.96 14.5 9 14.5c1.2 0 2.31.35 3.22.94"/><path stroke-linejoin="round" d="M15.5 21 14 22v-6.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1h-4.5Z"/>',

        'truck' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M7 17V9l3-4h4l3 4v8M9 17v2m6-2v2M8 9h8"/>',

        'shield' => '<path stroke-linecap="round" d="m9 12 2 2 4-4"/><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 7 4v5c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V7l7-4z"/>',

        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>',

        'check-circle' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12.5 2.5 2.5 4.5-5"/>',

        'x-circle' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="m9.5 9.5 5 5m0-5-5 5"/>',

        'clock' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 1.8"/>',

        'alert' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4.5m0 3.25v.25M10.3 4.2 2.9 17.1A2 2 0 0 0 4.6 20h14.8a2 2 0 0 0 1.7-2.9L13.7 4.2a2 2 0 0 0-3.4 0Z"/>',

        'send' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 3 3 10.5l7 2.5m11-10-6 18-3-8m9-10-9 10"/>',

        'link' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 13.5a4 4 0 0 0 5.66 0l2.83-2.83a4 4 0 1 0-5.66-5.66L11.6 6.24"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 10.5a4 4 0 0 0-5.66 0L5.5 13.34a4 4 0 0 0 5.66 5.66l1.2-1.2"/>',

        'unlink' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 15 5.5 18.5M15 9l3.5-3.5M4 4l16 16"/><path stroke-linecap="round" d="M13.5 6.5 15 5a4 4 0 0 1 5.66 5.66l-1.5 1.5M10.5 17.5 9 19a4 4 0 0 1-5.66-5.66l1.5-1.5"/>',

        'phone' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2Z"/>',

        'telegram' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 5 3 11.2l5 1.6L18 7l-7.6 7.5.3 5 2.9-3.4 4.6 3.3L21 5Z"/>',

        'external' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5m0-5-8 8M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',

        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2.5"/><path stroke-linecap="round" d="M5.5 15H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v.5"/>',

        'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 13h4l1.5 3h5L16 13h4M4 13l2.2-7A2 2 0 0 1 8.1 4.5h7.8A2 2 0 0 1 17.8 6L20 13v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4Z"/>',

        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path stroke-linecap="round" d="M3.5 10h17M8 3.5V6m8-2.5V6"/>',

        'hash' => '<path stroke-linecap="round" d="M5 9h14M5 15h14M10 4 8.5 20M15.5 4 14 20"/>',

        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m5 10V4m5 16v-7m5 7V8"/>',

        'trending' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5 9 10l4 4 8-8m0 0h-5m5 0v5"/>',

        'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path stroke-linecap="round" d="m13.5 6.5 4 4"/>',

        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/>',

        'play' => '<path stroke-linejoin="round" d="M7 5.5v13l11-6.5L7 5.5Z"/>',

        'stop' => '<rect x="6" y="6" width="12" height="12" rx="2.5"/>',

        'spark' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18.2l-1.8-5.6L4.5 10.8 10.2 9 12 3.5Z"/>',

        'menu' => '<path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10"/>',

        'sun' => '<circle cx="12" cy="12" r="3.6"/><path stroke-linecap="round" d="M12 3v1.8M12 19.2V21M3 12h1.8M19.2 12H21M5.6 5.6l1.3 1.3M17.1 17.1l1.3 1.3M18.4 5.6l-1.3 1.3M6.9 17.1l-1.3 1.3"/>',

        'moon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 14.2A8 8 0 1 1 9.8 4a6.4 6.4 0 0 0 10.2 10.2Z"/>',

        'globe' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3c2.6 2.4 3.9 5.4 3.9 9s-1.3 6.6-3.9 9c-2.6-2.4-3.9-5.4-3.9-9S9.4 5.4 12 3Z"/>',

        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10"/>',

        'command' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6Z"/>',

        'flag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 1h11l-2 3.5 2 3.5H5"/>',

        'sliders-h' => '<path stroke-linecap="round" d="M4 6h9m4 0h3M4 12h3m4 0h9M4 18h11m4 0h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',

        'bot' => '<rect x="4" y="8" width="16" height="11" rx="3"/><path stroke-linecap="round" d="M12 8V4.5M9 13v1.5m6-1.5v1.5M2 13v2m20-2v2"/><circle cx="12" cy="3.5" r="1"/>',

        'message' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5v-9Z"/>',

        'route' => '<circle cx="6" cy="18" r="2.2"/><circle cx="18" cy="6" r="2.2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.2 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.8"/>',

        'key' => '<circle cx="8" cy="15" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="m11 12 8.5-8.5M16 7l2.5 2.5M14 9l2 2"/>',

        'pulse' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2.5-6 4 12 2.5-6H21"/>',

        'layers' => '<path stroke-linecap="round" stroke-linejoin="round" d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5m-18 4 9 5 9-5"/>',

        'sync' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 8a8 8 0 0 0-14.3-2M4 4v4h4M4 16a8 8 0 0 0 14.3 2M20 20v-4h-4"/>',

        'book' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 5.5A1.5 1.5 0 0 1 6.5 4H19v14H6.5A1.5 1.5 0 0 0 5 19.5v-14Zm0 14A1.5 1.5 0 0 0 6.5 21H19v-3"/>',

        'lock' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path stroke-linecap="round" d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',

        'mail' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/>',

        'upload' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',

        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 1.8h6A2 2 0 0 0 17 19l1-12M9 7V4.5h6V7"/>',

        'more' => '<circle cx="5.5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="18.5" cy="12" r="1.3"/>',
    ];
@endphp

<svg
    {{ $attributes->merge(['class' => 'h-5 w-5', 'aria-hidden' => 'true']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
>
    {!! $paths[$name] ?? $paths['hash'] !!}
</svg>

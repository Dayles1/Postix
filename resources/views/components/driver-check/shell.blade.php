{{--
    Page shell.

    16px gutters on a phone, room to breathe from sm up, and a max width so
    the tables stop stretching on an ultrawide monitor.
--}}

<div {{ $attributes->merge(['class' => 'px-4 pb-10 pt-4 sm:px-6 sm:pb-12 sm:pt-6 lg:px-8']) }}>
    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-4 sm:gap-5">
        {{ $slot }}
    </div>
</div>

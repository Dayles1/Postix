<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.errors.403_title') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-100 to-gray-200 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white/80 backdrop-blur-xl border border-gray-200 rounded-2xl shadow-xl p-8 max-w-md w-full text-center">

        <!-- Icon -->
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-red-100">
                <svg xmlns="http://www.w3.org/2000/svg" 
                     class="w-8 h-8 text-red-600" 
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01M5.93 19h12.14c1.54 0 2.5-1.67 
                             1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 
                             0L4.2 16c-.77 1.33.19 3 1.73 3z"/>
                </svg>
            </div>
        </div>

        <!-- Code -->
        <h1 class="text-5xl font-extrabold text-red-600 mb-2 tracking-tight">
            403
        </h1>

        <!-- Title -->
        <h2 class="text-xl font-semibold text-gray-800 mb-2">
            {{ __('messages.errors.forbidden') }}
        </h2>

        <!-- Description -->
        <p class="text-gray-500 mb-6 text-sm leading-relaxed">
            {{ __('messages.errors.forbidden_sub') }}
        </p>

        <!-- Buttons -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center">

            <a href="{{ url()->previous() }}" 
               class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100 transition duration-200">
                ← {{ __('messages.errors.back') }}
            </a>

            <a href="{{ route('departments.index') }}" 
               class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 shadow-md hover:shadow-lg transition duration-200">
                {{ __('messages.errors.home') }}
            </a>

        </div>

    </div>

</body>
</html>
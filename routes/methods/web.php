<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});
Route::get('/lang/{locale}', function ($locale) {
    if (!in_array($locale, ['uz', 'ru', 'en', 'ko', 'zh', 'ja', 'ar', 'de', 'fr', 'es', 'it','ja','hi','tr' ])) {
        abort(400);
    }

    session(['locale' => $locale]);

    return redirect()->back();
})->name('lang.switch');

Route::get('/documantation', function () {
    return view('documentation.index');
})->name('documentation.index');
    

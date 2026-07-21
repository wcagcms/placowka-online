<?php

use App\Http\Controllers\LegalPageController;
use Illuminate\Support\Facades\Route;

Route::get('/polityka-prywatnosci', [LegalPageController::class, 'privacy'])
    ->name('legal.privacy');

Route::get('/rodo', [LegalPageController::class, 'rodo'])
    ->name('legal.rodo');

Route::get('/regulamin', [LegalPageController::class, 'terms'])
    ->name('legal.terms');

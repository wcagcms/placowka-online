<?php

use App\Http\Controllers\PlatinumPreviewController;
use App\Http\Middleware\EnsurePanelAuthenticated;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsurePanelAuthenticated::class)->group(function (): void {
    Route::get('/admin/saas-platinum', PlatinumPreviewController::class)
        ->name('platinum.preview');
});

<?php

use App\Http\Controllers\Api\AgentEnrollmentController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Middleware\EnsureAgentEnrollmentRequest;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::post('/v1/heartbeat/{uuid}', HeartbeatController::class)
    ->whereUuid('uuid')
    ->middleware(['throttle:heartbeat', SecurityHeaders::class])
    ->name('api.heartbeat');

Route::prefix('v1/agent/enroll')->group(function (): void {
    Route::post('/start', [AgentEnrollmentController::class, 'start'])
        ->middleware([EnsureAgentEnrollmentRequest::class, 'throttle:agent-enrollment-start', SecurityHeaders::class])
        ->name('api.agent-enrollment.start');

    Route::post('/complete', [AgentEnrollmentController::class, 'complete'])
        ->middleware([EnsureAgentEnrollmentRequest::class, 'throttle:agent-enrollment-complete', SecurityHeaders::class])
        ->name('api.agent-enrollment.complete');
});

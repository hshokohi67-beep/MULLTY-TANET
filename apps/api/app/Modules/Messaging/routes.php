<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Messaging\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

// The café's own SMS centre.
Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'can:'.P::SMS_MANAGE])->prefix('sms')->name('sms.')->group(function () {
    Route::get('/', [SmsController::class, 'show'])->name('show');
    Route::put('account', [SmsController::class, 'saveAccount'])->name('account');
    Route::post('account/test', [SmsController::class, 'test'])->middleware('throttle:sms-test')->name('account.test');
    Route::put('templates', [SmsController::class, 'saveTemplates'])->name('templates');
    Route::get('logs', [SmsController::class, 'logs'])->name('logs');
    Route::post('audience', [SmsController::class, 'audience'])->name('audience');
    Route::post('campaigns', [SmsController::class, 'storeCampaign'])->name('campaigns.store');
    Route::put('campaigns/{smsCampaign}', [SmsController::class, 'updateCampaign'])->name('campaigns.update');
    Route::post('campaigns/{smsCampaign}/schedule', [SmsController::class, 'scheduleCampaign'])->name('campaigns.schedule');
    Route::post('campaigns/{smsCampaign}/cancel', [SmsController::class, 'cancelCampaign'])->name('campaigns.cancel');
});

<?php

namespace App\Modules\Occasions\Providers;

use App\Modules\Occasions\Console\OccasionsCommand;
use App\Modules\Occasions\Models\Occasion;
use App\Modules\Occasions\Models\OccasionDate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class OccasionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([OccasionsCommand::class]);
        }

        Route::model('occasion', Occasion::class);
        Route::model('occasionDate', OccasionDate::class);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}

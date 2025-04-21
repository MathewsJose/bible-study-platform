<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Infrastructure\Bible\Persistence\Mongo\Repositories\MongoVerseRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VerseRepositoryInterface::class, MongoVerseRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

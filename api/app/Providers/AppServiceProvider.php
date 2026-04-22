<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;
use App\Infrastructure\Bible\Persistence\Mongo\Repositories\MongoVerseRepository;
use App\Infrastructure\History\Persistence\Mongo\Repositories\MongoHistoricalContextRepository;
use App\Infrastructure\Teachings\Persistence\Mongo\Repositories\MongoChurchTeachingRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VerseRepositoryInterface::class, MongoVerseRepository::class);
        $this->app->bind(HistoricalContextRepositoryInterface::class, MongoHistoricalContextRepository::class);
        $this->app->bind(ChurchTeachingRepositoryInterface::class, MongoChurchTeachingRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

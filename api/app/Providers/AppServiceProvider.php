<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Knowledge\Contracts\KnowledgeAgentContract;
use App\Application\Knowledge\Contracts\KnowledgeAnswerContract;
use App\Application\Knowledge\Contracts\KnowledgeRetrievalContract;
use App\Application\Knowledge\Contracts\KnowledgeSearchContract;
use App\Application\Knowledge\Contracts\KnowledgeServiceClientInterface;
use App\Application\Knowledge\Contracts\ReferenceResolutionContract;
use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;
use App\Infrastructure\Bible\Persistence\Mongo\Repositories\MongoVerseRepository;
use App\Infrastructure\History\Persistence\Mongo\Repositories\MongoHistoricalContextRepository;
use App\Infrastructure\Knowledge\Http\HttpKnowledgeServiceClient;
use App\Infrastructure\Teachings\Persistence\Mongo\Repositories\MongoChurchTeachingRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->app->singleton(KnowledgeServiceClientInterface::class, HttpKnowledgeServiceClient::class);
        $this->app->bind(KnowledgeSearchContract::class, fn (): KnowledgeServiceClientInterface => $this->app->make(KnowledgeServiceClientInterface::class));
        $this->app->bind(ReferenceResolutionContract::class, fn (): KnowledgeServiceClientInterface => $this->app->make(KnowledgeServiceClientInterface::class));
        $this->app->bind(KnowledgeRetrievalContract::class, fn (): KnowledgeServiceClientInterface => $this->app->make(KnowledgeServiceClientInterface::class));
        $this->app->bind(KnowledgeAnswerContract::class, fn (): KnowledgeServiceClientInterface => $this->app->make(KnowledgeServiceClientInterface::class));
        $this->app->bind(KnowledgeAgentContract::class, fn (): KnowledgeServiceClientInterface => $this->app->make(KnowledgeServiceClientInterface::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('knowledge-ai', function (Request $request) {
            return Limit::perMinute((int) config('knowledge_service.ai_rate_limit_per_minute', 10))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('knowledge-feedback', function (Request $request) {
            return Limit::perMinute((int) config('knowledge_service.feedback_rate_limit_per_minute', 30))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}

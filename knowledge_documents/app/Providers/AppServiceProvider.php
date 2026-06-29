<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Infrastructure\Knowledge\Embedding\DummyEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\OpenAIEmbeddingProvider;
use App\Infrastructure\Knowledge\Persistence\EloquentKnowledgeDocumentRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KnowledgeDocumentRepositoryInterface::class, EloquentKnowledgeDocumentRepository::class);
        $this->app->bind(EmbeddingProviderInterface::class, config('services.openai.embedding_provider') === 'dummy'
            ? DummyEmbeddingProvider::class
            : OpenAIEmbeddingProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

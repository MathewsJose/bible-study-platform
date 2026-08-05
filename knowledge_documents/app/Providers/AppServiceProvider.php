<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\Contracts\ResultFusionStrategyInterface;
use App\Application\Knowledge\Services\WeightedScoreFusionStrategy;
use App\Infrastructure\Knowledge\Embedding\DummyEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\LocalEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\NullEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\OpenAIEmbeddingProvider;
use App\Infrastructure\Knowledge\Persistence\EloquentEmbeddingRepository;
use App\Infrastructure\Knowledge\Persistence\EloquentKnowledgeDocumentRepository;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use App\Infrastructure\Knowledge\Importers\CatechismImporter;
use App\Infrastructure\Knowledge\Importers\ChurchFatherImporter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KnowledgeDocumentRepositoryInterface::class, EloquentKnowledgeDocumentRepository::class);
        $this->app->bind(EmbeddingRepositoryInterface::class, EloquentEmbeddingRepository::class);
        $this->app->bind(ResultFusionStrategyInterface::class, WeightedScoreFusionStrategy::class);
        $this->app->bind(EmbeddingProviderInterface::class, fn (): EmbeddingProviderInterface => $this->app->make($this->embeddingProviderClass()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(BibleImporter::class);
        $this->app->singleton(CatechismImporter::class);
        $this->app->singleton(ChurchFatherImporter::class);
    }

    private function embeddingProviderClass(): string
    {
        return match (config('embeddings.provider', 'null')) {
            'openai' => OpenAIEmbeddingProvider::class,
            'local' => LocalEmbeddingProvider::class,
            'dummy' => DummyEmbeddingProvider::class,
            default => NullEmbeddingProvider::class,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\Contracts\ResultFusionStrategyInterface;
use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\Graph\Resolvers\CatechismReferenceResolver;
use App\Application\Knowledge\Graph\Resolvers\ChurchFatherReferenceResolver;
use App\Application\Knowledge\Graph\Resolvers\ScriptureReferenceResolver;
use App\Application\Knowledge\Graph\Services\KnowledgeGraphBuilder;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Application\Knowledge\Services\WeightedScoreFusionStrategy;
use App\Infrastructure\Knowledge\Embedding\DummyEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\LocalEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\NullEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\OpenAIEmbeddingProvider;
use App\Infrastructure\Knowledge\AI\ClaudeProvider;
use App\Infrastructure\Knowledge\AI\GeminiProvider;
use App\Infrastructure\Knowledge\AI\NullProvider;
use App\Infrastructure\Knowledge\AI\OllamaProvider;
use App\Infrastructure\Knowledge\AI\OpenAIProvider as OpenAIAnswerProvider;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use App\Infrastructure\Knowledge\Importers\CatechismImporter;
use App\Infrastructure\Knowledge\Importers\ChurchFatherImporter;
use App\Infrastructure\Knowledge\Importing\BibleKnowledgeImporter;
use App\Infrastructure\Knowledge\Importing\CatechismKnowledgeImporter;
use App\Infrastructure\Knowledge\Importing\ChurchFathersKnowledgeImporter;
use App\Infrastructure\Knowledge\Graph\EloquentKnowledgeGraphRepository;
use App\Infrastructure\Knowledge\Persistence\EloquentEmbeddingRepository;
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
        $this->app->bind(EmbeddingRepositoryInterface::class, EloquentEmbeddingRepository::class);
        $this->app->bind(ResultFusionStrategyInterface::class, WeightedScoreFusionStrategy::class);
        $this->app->bind(KnowledgeGraphRepositoryInterface::class, EloquentKnowledgeGraphRepository::class);
        $this->app->bind(EmbeddingProviderInterface::class, fn (): EmbeddingProviderInterface => $this->app->make($this->embeddingProviderClass()));
        $this->app->bind(LLMProviderInterface::class, fn (): LLMProviderInterface => $this->app->make($this->llmProviderClass()));
        $this->app->bind(KnowledgeGraphBuilder::class, fn (): KnowledgeGraphBuilder => new KnowledgeGraphBuilder(
            $this->app->make(KnowledgeGraphRepositoryInterface::class),
            [
                $this->app->make(ScriptureReferenceResolver::class),
                $this->app->make(CatechismReferenceResolver::class),
                $this->app->make(ChurchFatherReferenceResolver::class),
            ],
        ));
        $this->app->singleton(KnowledgeSourceRegistry::class, function (): KnowledgeSourceRegistry {
            $registry = new KnowledgeSourceRegistry();

            foreach ((array) config('knowledge.import.sources', []) as $importerClass) {
                $registry->register($this->app->make($importerClass));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(BibleImporter::class);
        $this->app->singleton(CatechismImporter::class);
        $this->app->singleton(ChurchFatherImporter::class);
        $this->app->singleton(BibleKnowledgeImporter::class);
        $this->app->singleton(CatechismKnowledgeImporter::class);
        $this->app->singleton(ChurchFathersKnowledgeImporter::class);
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

    private function llmProviderClass(): string
    {
        return match (config('ai.provider', 'null')) {
            'openai' => OpenAIAnswerProvider::class,
            'ollama' => OllamaProvider::class,
            'gemini' => GeminiProvider::class,
            'claude' => ClaudeProvider::class,
            default => NullProvider::class,
        };
    }
}

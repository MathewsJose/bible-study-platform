<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\Contracts\ResultFusionStrategyInterface;
use App\Application\Knowledge\Agents\Contracts\AgentInterface;
use App\Application\Knowledge\Agents\Contracts\AgentPlannerInterface;
use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use App\Application\Knowledge\Agents\Services\DeterministicAgentPlanner;
use App\Application\Knowledge\Agents\Services\KnowledgeAgent;
use App\Application\Knowledge\Agents\Services\LLMAgentPlanner;
use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Evaluation\Contracts\AnswerEvaluatorInterface;
use App\Application\Knowledge\Evaluation\Services\DeterministicAnswerEvaluator;
use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\Graph\Resolvers\CatechismReferenceResolver;
use App\Application\Knowledge\Graph\Resolvers\ChurchFatherReferenceResolver;
use App\Application\Knowledge\Graph\Resolvers\ScriptureReferenceResolver;
use App\Application\Knowledge\Graph\Services\KnowledgeGraphBuilder;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use App\Application\Knowledge\Security\Contracts\PersonalDataDeletionInterface;
use App\Application\Knowledge\Security\Contracts\PersonalDataLocatorInterface;
use App\Application\Knowledge\Security\Services\AISecurityPolicy;
use App\Application\Knowledge\Security\Services\TracePersonalDataService;
use App\Application\Knowledge\Services\WeightedScoreFusionStrategy;
use App\Infrastructure\Knowledge\Embedding\DummyEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\LocalEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\NullEmbeddingProvider;
use App\Infrastructure\Knowledge\Embedding\OpenAIEmbeddingProvider;
use App\Infrastructure\Knowledge\AI\AnthropicProvider;
use App\Infrastructure\Knowledge\AI\GoogleProvider;
use App\Infrastructure\Knowledge\AI\LocalProvider;
use App\Infrastructure\Knowledge\AI\NullProvider;
use App\Infrastructure\Knowledge\AI\OllamaProvider;
use App\Infrastructure\Knowledge\AI\OpenAIProvider as OpenAIAnswerProvider;
use App\Infrastructure\Knowledge\Agents\Persistence\EloquentAgentTraceRepository;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        $this->app->bind(AgentTraceRepositoryInterface::class, EloquentAgentTraceRepository::class);
        $this->app->bind(AgentInterface::class, KnowledgeAgent::class);
        $this->app->bind(AISecurityPolicyInterface::class, AISecurityPolicy::class);
        $this->app->bind(AnswerEvaluatorInterface::class, DeterministicAnswerEvaluator::class);
        $this->app->bind(PersonalDataLocatorInterface::class, TracePersonalDataService::class);
        $this->app->bind(PersonalDataDeletionInterface::class, TracePersonalDataService::class);
        $this->app->bind(AgentPlannerInterface::class, fn (): AgentPlannerInterface => match (config('agents.planner', 'deterministic')) {
            'llm' => $this->app->make(LLMAgentPlanner::class),
            default => $this->app->make(DeterministicAgentPlanner::class),
        });
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
        $this->app->singleton(AgentToolRegistry::class, function (): AgentToolRegistry {
            $registry = new AgentToolRegistry();

            foreach ((array) config('agents.tools', []) as $toolClass) {
                $tool = $this->app->make($toolClass);

                if ($tool instanceof ToolInterface) {
                    $registry->register($tool);
                }
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request): Limit {
            return Limit::perMinute((int) config('mcp_knowledge.rate_limit_per_minute', 30))
                ->by((string) ($request->bearerToken() ?: $request->ip()));
        });

        RateLimiter::for('knowledge-ai-answer', function (Request $request): Limit {
            return Limit::perMinute((int) config('ai_security.rate_limits.answer_per_minute', 20))
                ->by($this->rateLimitKey($request));
        });

        RateLimiter::for('knowledge-ai-agent', function (Request $request): Limit {
            return Limit::perMinute((int) config('ai_security.rate_limits.agent_per_minute', 10))
                ->by($this->rateLimitKey($request));
        });

        RateLimiter::for('knowledge-ai-retrieval', function (Request $request): Limit {
            return Limit::perMinute((int) config('ai_security.rate_limits.retrieval_per_minute', 60))
                ->by($this->rateLimitKey($request));
        });

        RateLimiter::for('knowledge-ai-replay', function (Request $request): Limit {
            return Limit::perMinute((int) config('ai_security.rate_limits.replay_per_minute', 10))
                ->by($this->rateLimitKey($request));
        });

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
        return match (config('llm.default_provider', config('ai.provider', 'null'))) {
            'openai' => OpenAIAnswerProvider::class,
            'local' => LocalProvider::class,
            'ollama' => OllamaProvider::class,
            'gemini', 'google' => GoogleProvider::class,
            'claude', 'anthropic' => AnthropicProvider::class,
            default => NullProvider::class,
        };
    }

    private function rateLimitKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?: $request->bearerToken() ?: $request->ip());
    }
}

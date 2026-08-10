<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use App\Application\Knowledge\Agents\Contracts\AgentPlannerInterface;
use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentPlan;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use Illuminate\Support\Str;

final readonly class DeterministicAgentPlanner implements AgentPlannerInterface
{
    public function plan(AgentState $state): AgentPlan
    {
        if ($state->currentStep > 0) {
            return new AgentPlan([], complete: true, finalAnswer: null, decision: 'single_pass_complete');
        }

        $input = $state->request->input;
        $lower = Str::lower($input);
        $actions = [];

        if (str_contains($lower, 'father') || str_contains($lower, 'augustine') || str_contains($lower, 'athanasius')) {
            $actions[] = new AgentAction('church_father_search', ['query' => $input, 'limit' => 5], 'Search patristic writings.');
        } elseif ($this->looksLikeScriptureReference($input)) {
            $actions[] = new AgentAction('scripture_reference', ['reference' => $input], 'Resolve an explicit Scripture reference.');
        } elseif ($this->looksLikeCatechismLookup($lower)) {
            $actions[] = new AgentAction('catechism_search', ['query' => $input, 'limit' => 5], 'Search Catechism paragraphs.');
        } elseif (str_contains($lower, 'bible') || str_contains($lower, 'scripture') || str_contains($lower, 'verse')) {
            $actions[] = new AgentAction('bible_search', ['query' => $input, 'limit' => 5], 'Search Bible documents.');
        }

        if ($this->needsResearch($lower)) {
            $actions[] = new AgentAction('advanced_retrieval', [
                'query' => $input,
                'profile' => $state->profile->retrievalProfile,
                'filters' => $state->request->filters,
                'top_k' => 10,
            ], 'Gather multi-source retrieval context.');

            if (is_string($state->request->metadata['document_id'] ?? null)) {
                $actions[] = new AgentAction('knowledge_graph', [
                    'document_id' => $state->request->metadata['document_id'],
                    'depth' => 1,
                    'limit' => 25,
                ], 'Traverse explicit knowledge graph relationships.');
            }
        }

        if ($actions === [] || $this->needsAnswer($lower)) {
            $actions[] = new AgentAction('answer_generation', [
                'question' => $input,
                'profile' => $state->profile->answerProfile,
                'filters' => $state->request->filters,
            ], 'Generate a grounded answer from the AI Answer Service.');
        }

        return new AgentPlan($this->deduplicate($actions), decision: 'deterministic_rules');
    }

    private function looksLikeScriptureReference(string $input): bool
    {
        return preg_match('/\b(?:[1-3]\s*)?[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?\s+\d+:\d+(?:-\d+)?\b/', $input) === 1;
    }

    private function looksLikeCatechismLookup(string $lower): bool
    {
        return preg_match('/\bccc\s*\d+\b/i', $lower) === 1 || str_contains($lower, 'catechism');
    }

    private function needsResearch(string $lower): bool
    {
        return str_contains($lower, 'according to')
            || str_contains($lower, 'compare')
            || str_contains($lower, 'bible and catechism')
            || str_contains($lower, 'scripture and catechism')
            || str_contains($lower, 'church fathers')
            || str_contains($lower, 'explain why');
    }

    private function needsAnswer(string $lower): bool
    {
        return str_contains($lower, 'why')
            || str_contains($lower, 'what')
            || str_contains($lower, 'how')
            || str_contains($lower, 'explain')
            || str_contains($lower, '?');
    }

    /**
     * @param  list<AgentAction>  $actions
     * @return list<AgentAction>
     */
    private function deduplicate(array $actions): array
    {
        $seen = [];
        $unique = [];

        foreach ($actions as $action) {
            if (isset($seen[$action->signature()])) {
                continue;
            }

            $seen[$action->signature()] = true;
            $unique[] = $action;
        }

        return $unique;
    }
}

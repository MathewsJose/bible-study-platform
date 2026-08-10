<?php

declare(strict_types=1);

use App\Application\Knowledge\Agents\Tools\AdvancedRetrievalTool;
use App\Application\Knowledge\Agents\Tools\AnswerGenerationTool;
use App\Application\Knowledge\Agents\Tools\BibleSearchTool;
use App\Application\Knowledge\Agents\Tools\CatechismSearchTool;
use App\Application\Knowledge\Agents\Tools\ChurchFatherSearchTool;
use App\Application\Knowledge\Agents\Tools\KnowledgeGraphTool;
use App\Application\Knowledge\Agents\Tools\ScriptureReferenceTool;

return [
    'default_profile' => env('AGENT_DEFAULT_PROFILE', 'catholic_research'),
    'planner' => env('AGENT_PLANNER', 'deterministic'),

    'defaults' => [
        'max_steps' => (int) env('AGENT_MAX_STEPS', 8),
        'max_tool_calls' => (int) env('AGENT_MAX_TOOL_CALLS', 8),
        'timeout_seconds' => (int) env('AGENT_TIMEOUT', 45),
        'tool_timeout_seconds' => (int) env('AGENT_TOOL_TIMEOUT', 15),
    ],

    'tools' => [
        BibleSearchTool::class,
        ScriptureReferenceTool::class,
        CatechismSearchTool::class,
        ChurchFatherSearchTool::class,
        KnowledgeGraphTool::class,
        AdvancedRetrievalTool::class,
        AnswerGenerationTool::class,
    ],

    'profiles' => [
        'bible_study' => [
            'display_name' => 'Bible Study Agent',
            'allowed_tools' => ['bible_search', 'scripture_reference', 'advanced_retrieval', 'answer_generation'],
            'max_steps' => 5,
            'max_tool_calls' => 5,
            'timeout_seconds' => 30,
            'retrieval_profile' => 'study_mode',
            'answer_profile' => 'ai_answer',
            'system_instructions' => 'Prioritize Scripture, then use supporting Catholic knowledge when needed.',
        ],
        'scripture_research' => [
            'display_name' => 'Scripture Research Agent',
            'allowed_tools' => ['bible_search', 'scripture_reference', 'advanced_retrieval'],
            'max_steps' => 5,
            'max_tool_calls' => 5,
            'timeout_seconds' => 30,
            'retrieval_profile' => 'search',
            'answer_profile' => 'ai_answer',
            'system_instructions' => 'Resolve Scripture references and retrieve related biblical context.',
        ],
        'catholic_research' => [
            'display_name' => 'Catholic Research Agent',
            'allowed_tools' => ['bible_search', 'scripture_reference', 'catechism_search', 'church_father_search', 'knowledge_graph', 'advanced_retrieval', 'answer_generation'],
            'max_steps' => 8,
            'max_tool_calls' => 8,
            'timeout_seconds' => 45,
            'retrieval_profile' => 'research',
            'answer_profile' => 'ai_answer',
            'system_instructions' => 'Use Scripture, Catechism, Church Fathers, and explicit graph links before finalizing a sourced answer.',
        ],
        'theological_research' => [
            'display_name' => 'Theological Research Agent',
            'allowed_tools' => ['catechism_search', 'church_father_search', 'knowledge_graph', 'advanced_retrieval', 'answer_generation'],
            'max_steps' => 8,
            'max_tool_calls' => 8,
            'timeout_seconds' => 45,
            'retrieval_profile' => 'research',
            'answer_profile' => 'ai_answer',
            'system_instructions' => 'Prefer doctrinal and patristic sources, preserving citations and provenance.',
        ],
    ],

    'evaluation' => [
        'scenarios' => [
            ['name' => 'Scripture lookup', 'input' => 'John 1:14', 'expected_tools' => ['scripture_reference']],
            ['name' => 'CCC lookup', 'input' => 'What does CCC 456 say?', 'expected_tools' => ['catechism_search', 'answer_generation']],
            ['name' => 'Bible and Catechism comparison', 'input' => 'What does the Bible and Catechism teach about the Incarnation?', 'expected_tools' => ['advanced_retrieval', 'answer_generation']],
            ['name' => 'Church Father research', 'input' => 'What do the Church Fathers say about John 1:14?', 'expected_tools' => ['church_father_search', 'answer_generation']],
            ['name' => 'Multi-source theological question', 'input' => 'Explain why Jesus became man according to Scripture, Catechism, and the Fathers.', 'expected_tools' => ['advanced_retrieval', 'answer_generation']],
        ],
    ],
];

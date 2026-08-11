<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

use App\Application\Knowledge\Agents\Observability\Services\TracePayloadSanitizer;
use Illuminate\Support\Facades\Log;

final readonly class SecurityEventLogger
{
    public function __construct(private TracePayloadSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $event, array $metadata = []): void
    {
        Log::info('AI security event', [
            'event' => $event,
            'metadata' => $this->sanitizer->sanitize($metadata),
        ]);
    }
}

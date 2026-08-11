<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

use App\Application\Knowledge\Security\DTOs\PiiDetection;
use App\Application\Knowledge\Security\DTOs\PiiScanResult;
use App\Application\Knowledge\Security\Enums\DataClassification;

final readonly class PiiDetector
{
    public function scan(string $input): PiiScanResult
    {
        $patterns = $this->patterns();
        $detections = [];
        $redacted = $input;

        foreach ($patterns as $type => $pattern) {
            $matches = [];
            $count = preg_match_all($pattern, $input, $matches);

            if ($count === false || $count === 0) {
                continue;
            }

            $detections[] = new PiiDetection((string) $type, $count);
            $redacted = (string) preg_replace($pattern, '[REDACTED]', $redacted);
        }

        return new PiiScanResult(
            redactedText: $redacted,
            detections: $detections,
            classification: $detections === [] ? DataClassification::Public : DataClassification::Personal,
        );
    }

    /** @return array<string, string> */
    private function patterns(): array
    {
        return [
            'api_key' => '/\b(?:sk|pk|rk|xoxb|ghp)_[A-Za-z0-9_\-]{12,}\b/',
            'bearer_token' => '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i',
            'email' => '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',
            'ip_address' => '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/',
            'phone' => '/(?<!\d)(?:\+?\d{1,3}[\s.-]?)?(?:\(?\d{3}\)?[\s.-]?)\d{3}[\s.-]?\d{4}(?!\d)/',
            'personal_url' => '/https?:\/\/[^\s?]+[^\s]*(?:email|user_id|userid|phone|name)=([^\s&#]+)/i',
            'address' => '/\b\d{1,6}\s+[A-Z][A-Za-z0-9.\-]*(?:\s+[A-Z][A-Za-z0-9.\-]*){0,4}\s+(?:Street|St|Avenue|Ave|Road|Rd|Lane|Ln|Drive|Dr|Boulevard|Blvd)\b/i',
            'contextual_name' => '/\b(?:my name is|i am|i\'m|this is)\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2}\b/',
        ];
    }
}

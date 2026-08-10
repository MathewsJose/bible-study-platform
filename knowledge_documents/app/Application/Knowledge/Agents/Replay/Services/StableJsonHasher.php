<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\Services;

final class StableJsonHasher
{
    /** @param  mixed  $value */
    public function hash(mixed $value): string
    {
        return hash('sha256', $this->encode($this->normalize($value)));
    }

    /** @param  mixed  $value */
    public function encode(mixed $value): string
    {
        return json_encode($this->normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param  mixed  $value */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->normalize($child);
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Application\Knowledge\Importing\DTOs\SourceInventoryItem;
use InvalidArgumentException;

final class SourceInventory
{
    /**
     * @return array<string, SourceInventoryItem>
     */
    public function all(): array
    {
        $items = [];

        foreach ((array) config('knowledge_sources.sources', []) as $entry) {
            if (! is_array($entry)) {
                throw new InvalidArgumentException('Source inventory entries must be arrays.');
            }

            $item = SourceInventoryItem::fromArray($entry);
            if (isset($items[$item->id])) {
                throw new InvalidArgumentException("Duplicate source inventory entry [{$item->id}].");
            }

            $items[$item->id] = $item;
        }

        return $items;
    }

    public function find(string $id): ?SourceInventoryItem
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * @return list<SourceInventoryItem>
     */
    public function forType(string $sourceType): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (SourceInventoryItem $item): bool => $item->type === $sourceType,
        ));
    }

    public function resolve(?string $sourceId, string $sourceType): ?SourceInventoryItem
    {
        if ($sourceId !== null && trim($sourceId) !== '') {
            return $this->find($sourceId);
        }

        $matches = $this->forType($sourceType);

        return count($matches) === 1 ? $matches[0] : null;
    }
}

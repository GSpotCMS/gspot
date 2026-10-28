<?php

declare(strict_types=1);

namespace GSpot\Plugin;

/**
 * PSR-14 compatible event dispatcher for GSpot CMS.
 */
class EventDispatcher
{
    private array $listeners = [];
    private array $history = [];

    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        $this->listeners[$event][] = [
            'handler' => $listener,
            'priority' => $priority,
        ];

        usort($this->listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    public function dispatch(string $event, mixed &$payload = null): void
    {
        $this->history[] = $event;

        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            $result = ($listener['handler'])($payload, $event);

            if ($result === false) {
                break;
            }
        }
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    public function getListeners(string $event = null): array
    {
        if ($event === null) {
            return $this->listeners;
        }
        return $this->listeners[$event] ?? [];
    }

    public function removeListeners(string $event): void
    {
        unset($this->listeners[$event]);
    }

    public function history(): array
    {
        return $this->history;
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }
}

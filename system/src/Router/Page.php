<?php

declare(strict_types=1);

namespace GSpot\Router;

class Page
{
    public function __construct(
        private string $slug,
        private string $title,
        private string $content,
        private string $template = 'default',
        private array $meta = [],
        private string $filePath = '',
    ) {}

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function getTemplate(): string
    {
        return 'layouts.' . $this->template;
    }

    public function meta(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->meta;
        }
        return $this->meta[$key] ?? $default;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function date(): ?string
    {
        return $this->meta['date'] ?? null;
    }

    public function published(): bool
    {
        return ($this->meta['published'] ?? true) !== false;
    }
}

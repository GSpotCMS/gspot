<?php

declare(strict_types=1);

namespace GSpot\Router;

class Route
{
    public function __construct(
        private string $uri,
        private Page $page,
    ) {}

    public function uri(): string
    {
        return $this->uri;
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}

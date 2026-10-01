<?php

namespace Arzcode\Sisifo\Llm\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class TaskAgent implements Agent
{
    use Promptable;

    public function __construct(
        private readonly string $instructions,
        private readonly int $maxTokens,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function maxTokens(): int
    {
        return $this->maxTokens;
    }
}

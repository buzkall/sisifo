<?php

namespace Arzcode\Sisifo\Contracts;

interface LlmProvider
{
    public function text(string $instructions, string $input, ?int $maxTokens = null): string;
}

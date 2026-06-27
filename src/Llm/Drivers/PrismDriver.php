<?php

namespace Arzcode\Sisifo\Llm\Drivers;

use Arzcode\Sisifo\Contracts\LlmProvider;
use Prism\Prism\Facades\Prism;

class PrismDriver implements LlmProvider
{
    public function text(string $instructions, string $input, ?int $maxTokens = null): string
    {
        $response = Prism::text()
            ->using(config('sisifo.llm.provider'), config('sisifo.llm.model'))
            ->withSystemPrompt($instructions)
            ->withPrompt($input)
            ->withMaxTokens($maxTokens ?? (int)config('sisifo.llm.max_tokens', 2048))
            ->asText();

        return $response->text;
    }
}

<?php

namespace Arzcode\Sisifo\Llm\Drivers;

use Arzcode\Sisifo\Contracts\LlmProvider;
use LogicException;

/**
 * Stub driver. Kept as a seam for a future migration back to laravel/ai.
 * Not currently implemented — selecting this driver will throw at runtime.
 */
class LaravelAiDriver implements LlmProvider
{
    public function text(string $instructions, string $input, ?int $maxTokens = null): string
    {
        throw new LogicException(
            'LaravelAiDriver is a stub. Bind your own implementation or switch sisifo.llm.driver to "prism".'
        );
    }
}

<?php

namespace Arzcode\Sisifo\Llm\Drivers;

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Llm\Agents\TaskAgent;
use Arzcode\Sisifo\Support\ConfigValue;

class LaravelAiDriver implements LlmProvider
{
    public function text(string $instructions, string $input, ?int $maxTokens = null): string
    {
        $provider = config('sisifo.llm.provider');
        $model = config('sisifo.llm.model');

        $response = new TaskAgent($instructions, $maxTokens ?? ConfigValue::int('sisifo.llm.max_tokens', 2048))
            ->prompt(
                $input,
                provider: is_string($provider) || is_array($provider) ? $provider : null,
                model: is_string($model) ? $model : null,
            );

        return $response->text;
    }
}

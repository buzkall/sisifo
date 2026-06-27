<?php

namespace Arzcode\Sisifo\Tests\Support;

use Arzcode\Sisifo\Contracts\LlmProvider;
use Closure;
use PHPUnit\Framework\Assert;

class FakeLlmProvider implements LlmProvider
{
    /** @var array<int, string|Closure> */
    private array $responses = [];

    /** @var array<int, array{instructions: string, input: string, max_tokens: ?int}> */
    public array $calls = [];

    private int $index = 0;

    /**
     * @param  array<int, string|Closure>|string|Closure  $responses
     */
    public function __construct(array|string|Closure $responses = [])
    {
        $this->responses = is_array($responses) ? $responses : [$responses];
    }

    public function text(string $instructions, string $input, ?int $maxTokens = null): string
    {
        $this->calls[] = [
            'instructions' => $instructions,
            'input'        => $input,
            'max_tokens'   => $maxTokens,
        ];

        $response = $this->responses[$this->index] ?? end($this->responses) ?: '';
        $this->index++;

        if ($response instanceof Closure) {
            return (string)$response($instructions, $input, $maxTokens);
        }

        return (string)$response;
    }

    public function assertPrompted(Closure $predicate, ?string $message = null): self
    {
        $matched = collect($this->calls)->contains(fn(array $call) => (bool)$predicate((object)[
            'instructions' => $call['instructions'],
            'input'        => $call['input'],
        ]));

        Assert::assertTrue($matched, $message ?? 'No prompt matched the given predicate.');

        return $this;
    }

    public function assertNeverPrompted(?string $message = null): self
    {
        Assert::assertEmpty($this->calls, $message ?? 'Expected LlmProvider to never be prompted.');

        return $this;
    }

    public function assertPromptCount(int $count): self
    {
        Assert::assertCount($count, $this->calls);

        return $this;
    }
}

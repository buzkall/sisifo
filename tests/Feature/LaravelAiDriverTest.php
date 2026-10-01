<?php

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Llm\Agents\TaskAgent;
use Arzcode\Sisifo\Llm\Drivers\LaravelAiDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;

it('binds the laravel-ai driver by default', function() {
    expect(app(LlmProvider::class))->toBeInstanceOf(LaravelAiDriver::class);
});

it('tells the host app the prism driver was removed', function() {
    config()->set('sisifo.llm.driver', 'prism');

    app(LlmProvider::class);
})->throws(LogicException::class, 'removed in 0.2.0');

it('rejects an unknown driver', function() {
    config()->set('sisifo.llm.driver', 'nope');

    app(LlmProvider::class);
})->throws(LogicException::class, 'Unknown sisifo.llm.driver: nope');

it('returns the text the agent responds with', function() {
    TaskAgent::fake(['Resumen: 2 correos.']);

    $text = app(LlmProvider::class)->text('Eres un asistente.', 'De: a@b.c', 4096);

    expect($text)->toBe('Resumen: 2 correos.');

    TaskAgent::assertPromptedTimes(1);
    TaskAgent::assertPrompted(fn(AgentPrompt $prompt) => $prompt->prompt === 'De: a@b.c'
        && $prompt->agent->instructions() === 'Eres un asistente.'
        && $prompt->agent->maxTokens() === 4096);
});

it('prompts the configured provider and model', function() {
    config()->set('sisifo.llm.provider', 'openai');
    config()->set('sisifo.llm.model', 'gpt-test');

    TaskAgent::fake(['ok']);

    app(LlmProvider::class)->text('sys', 'input');

    TaskAgent::assertPrompted(fn(AgentPrompt $prompt) => $prompt->model === 'gpt-test'
        && $prompt->provider()->name() === 'openai');
});

it('sends instructions, model and max tokens to the provider', function() {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id'          => 'msg_1',
            'type'        => 'message',
            'role'        => 'assistant',
            'model'       => 'claude-haiku-4-5',
            'content'     => [['type' => 'text', 'text' => 'Resumen.']],
            'stop_reason' => 'end_turn',
            'usage'       => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $text = app(LlmProvider::class)->text('Eres un asistente.', 'De: a@b.c', 1234);

    expect($text)->toBe('Resumen.');

    Http::assertSent(fn(Request $request) => $request['max_tokens'] === 1234
        && $request['model'] === 'claude-haiku-4-5'
        && str_contains(json_encode($request['system']), 'Eres un asistente.')
        && str_contains(json_encode($request['messages']), 'De: a@b.c'));
});

it('falls back to the configured max tokens', function() {
    config()->set('sisifo.llm.max_tokens', 777);

    TaskAgent::fake(['ok']);

    app(LlmProvider::class)->text('sys', 'input');

    TaskAgent::assertPrompted(fn(AgentPrompt $prompt) => $prompt->agent->maxTokens() === 777);
});

<?php

use Arzcode\Sisifo\Contracts\SummarizableItem;
use Arzcode\Sisifo\Models\InboundEmail;
use Carbon\CarbonInterface;

it('implements the SummarizableItem contract', function() {
    expect(new InboundEmail)->toBeInstanceOf(SummarizableItem::class);
});

it('maps its columns onto the contract accessors', function() {
    $email = InboundEmail::factory()->create([
        'subject'      => 'Quarterly report',
        'from_name'    => 'Ada Lovelace',
        'from_address' => 'ada@example.com',
        'text_body'    => 'The body of the message.',
        'received_at'  => now()->subHour(),
    ]);

    expect($email->sisifoTitle())->toBe('Quarterly report')
        ->and($email->sisifoOrigin())->toBe('Ada Lovelace <ada@example.com>')
        ->and($email->sisifoOriginId())->toBe('ada@example.com')
        ->and($email->sisifoBody())->toBe('The body of the message.')
        ->and($email->sisifoKey())->toBe($email->id);

    expect($email->sisifoOccurredAt())->toBeInstanceOf(CarbonInterface::class);
    expect($email->sisifoOccurredAt()->equalTo($email->received_at))->toBeTrue();
});

it('falls back to now() for the timestamp when received_at is missing', function() {
    $email = new InboundEmail;

    expect($email->sisifoOccurredAt())->toBeInstanceOf(CarbonInterface::class)
        ->and($email->sisifoOccurredAt()->equalTo(now()))->toBeTrue();
});

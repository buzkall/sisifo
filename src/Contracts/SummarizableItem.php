<?php

namespace Arzcode\Sisifo\Contracts;

use Carbon\CarbonInterface;

/**
 * A source-neutral item the task engine can render and summarize.
 *
 * Any source of items the engine draws from (inbound emails today, other
 * sources later) exposes itself to the engine through these methods instead
 * of leaking its own column names. The engine renders and tracks items purely
 * in terms of this contract.
 */
interface SummarizableItem
{
    /**
     * The primary display title of the item (an email subject, a release name…).
     */
    public function sisifoTitle(): string;

    /**
     * A human-readable sender/origin label (an email "Name <address>", a repo…).
     */
    public function sisifoOrigin(): string;

    /**
     * A stable identifier or URL locating where the item came from.
     */
    public function sisifoOriginId(): string;

    /**
     * The item's textual body, already normalized to plain text.
     */
    public function sisifoBody(): string;

    /**
     * When the item occurred (an email's received-at, a release's published-at…).
     */
    public function sisifoOccurredAt(): CarbonInterface;

    /**
     * The item's primary key within its own table, used to mark it processed.
     */
    public function sisifoKey(): int;
}

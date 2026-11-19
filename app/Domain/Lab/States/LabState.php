<?php

declare(strict_types=1);

namespace App\Domain\Lab\States;

use App\Domain\Lab\Exceptions\InvalidStateTransitionException;

enum LabState: string
{
    case ORDERED = 'ordered';
    case COLLECTED = 'collected';
    case RECEIVED = 'received';
    case IN_PROCESS = 'in_process';
    case RESULTED = 'resulted';
    case VERIFIED = 'verified';
    case RELEASED = 'released';
    case CANCELLED = 'cancelled';

    /**
     * Determine if a transition from current state to the target state is allowed.
     */
    public function canTransitionTo(LabState $to): bool
    {
        return match ($this) {
            self::ORDERED => in_array($to, [self::COLLECTED, self::CANCELLED], true),
            self::COLLECTED => in_array($to, [self::RECEIVED, self::ORDERED, self::CANCELLED], true),
            self::RECEIVED => in_array($to, [self::IN_PROCESS, self::ORDERED, self::CANCELLED], true),
            self::IN_PROCESS => in_array($to, [self::RESULTED, self::VERIFIED, self::ORDERED, self::CANCELLED], true),
            self::RESULTED => in_array($to, [self::IN_PROCESS, self::VERIFIED], true),
            self::VERIFIED => in_array($to, [self::IN_PROCESS, self::RELEASED], true),
            self::RELEASED => false, // released reports can only be amended via new report version
            self::CANCELLED => false,
        };
    }

    /**
     * Transition to target state or throw exception.
     */
    public function transitionTo(LabState $to, string $reason = ''): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidStateTransitionException($this->value, $to->value, $reason);
        }
    }

    /**
     * Human-readable label for UI badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::ORDERED => 'Ordered',
            self::COLLECTED => 'Sample Collected',
            self::RECEIVED => 'Sample Received',
            self::IN_PROCESS => 'In Process',
            self::RESULTED => 'Resulted',
            self::VERIFIED => 'Verified',
            self::RELEASED => 'Released',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Color token class for compact badge rendering.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ORDERED => 'badge-ordered',
            self::COLLECTED => 'badge-collected',
            self::RECEIVED => 'badge-received',
            self::IN_PROCESS => 'badge-inprocess',
            self::RESULTED => 'badge-resulted',
            self::VERIFIED => 'badge-verified',
            self::RELEASED => 'badge-released',
            self::CANCELLED => 'badge-cancelled',
        };
    }
}

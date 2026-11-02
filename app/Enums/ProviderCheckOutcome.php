<?php

namespace App\Enums;

/**
 * What an admin's Check with provider found (spec 5.4).
 */
enum ProviderCheckOutcome
{
    /** The provider has it paid, and the order is placed or the top-up credited. */
    case Paid;

    /** It had settled before the check, so the provider wasn't asked. */
    case AlreadySettled;

    /** The provider still has it pending; nothing changed. */
    case StillPending;

    /** The provider has it paid for another amount or currency; it's marked failed. */
    case AmountMismatch;

    /** The provider no longer has it (or never did); it's closed. */
    case Closed;
}

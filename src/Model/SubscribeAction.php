<?php

declare(strict_types=1);

namespace PayKit\Bundle\Model;

enum SubscribeAction: string
{
    /** Already on the target plan, nothing pending. */
    case Noop = 'noop';
    /** Pending downgrade/cancellation cleared. */
    case Resumed = 'resumed';
    /** New subscription active immediately. */
    case Activated = 'activated';
    /** Customer must complete a hosted checkout (see paymentUrl). */
    case Checkout = 'checkout';
    /** Paid upgrade applied immediately with proration. */
    case Upgraded = 'upgraded';
    /** Downgrade (or cancel-to-free) scheduled for the end of the period. */
    case Scheduled = 'scheduled';
}

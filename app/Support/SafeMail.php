<?php

namespace App\Support;

use Throwable;

/**
 * Runs a mail/notification send so that a mail-server outage can never break the user-facing action that triggered it
 * (placing an order, registering, approving a wholesale account...). The failure is reported to the log, not shown.
 */
class SafeMail
{
    public static function enabled(): bool
    {
        return (bool) config('store.mail_enabled');
    }

    public static function run(callable $send): bool
    {
        if (! self::enabled()) {
            return false; // no email service configured: do not even try
        }

        try {
            $send();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}

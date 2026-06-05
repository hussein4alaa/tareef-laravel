<?php

namespace Tareef\Laravel\Exceptions;

/**
 * Thrown for HTTP 429 — this month's plan quota is used up.
 *
 * Catch this to surface a friendly upgrade-prompt in your UI. The exception
 * carries the upstream message, which usually names the limit that was hit
 * (verifications, people, etc).
 */
class QuotaExceededException extends TareefException
{
}

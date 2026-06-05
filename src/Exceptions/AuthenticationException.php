<?php

namespace Tareef\Laravel\Exceptions;

/**
 * Thrown for HTTP 401 — the API key is missing, malformed, or revoked.
 *
 * Make sure TAREEF_API_KEY is set in your .env and the value matches an
 * active key in your Tareef dashboard → API keys.
 */
class AuthenticationException extends TareefException
{
}

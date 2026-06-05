<?php

namespace Tareef\Laravel\Exceptions;

/**
 * Thrown when the SDK couldn't reach Tareef at all (DNS, connect-timeout,
 * read-timeout) or got a 5xx response. Catch this and back off / retry later;
 * the underlying error is preserved as `->getPrevious()`.
 */
class ServiceUnavailableException extends TareefException
{
}

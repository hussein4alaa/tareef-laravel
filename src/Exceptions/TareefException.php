<?php

namespace Tareef\Laravel\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every exception thrown by the Tareef SDK.
 *
 * Catch this if you want a single try/catch that covers any SDK failure.
 * Catch the more specific subclasses (AuthenticationException, QuotaExceededException,
 * FaceAlreadyExistsException, etc.) when you want to react to specific outcomes.
 */
class TareefException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?int $httpStatus = null,
        public readonly ?string $apiStatus = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * The raw Tareef status string from the response payload, if any
     * (e.g., "ok", "no_face", "face_exists", "not_found", "upstream_error").
     */
    public function apiStatus(): ?string
    {
        return $this->apiStatus;
    }

    /**
     * Extra context the SDK attached when throwing — UUIDs, image counts,
     * upstream message strings, etc.
     */
    public function context(): array
    {
        return $this->context;
    }
}

<?php

namespace Tareef\Laravel\Exceptions;

/**
 * The Tareef API rejected the request because a required field was missing,
 * the wrong shape, or violated a server-side rule (HTTP 422 / 400).
 *
 * Common triggers:
 *   - the `file` field is missing or empty (often because PHP
 *     upload_max_filesize / post_max_size silently dropped a large image
 *     on the SENDING side — check those before assuming the SDK is broken)
 *   - the image isn't a supported MIME type
 *   - the image is larger than the API's per-image limit
 *
 * Inspect `errors()` for the field → messages map (Laravel-style payload).
 */
class ValidationException extends TareefException
{
    public function __construct(
        string $message,
        public readonly array $errors = [],
        ?int $httpStatus = 422,
        ?string $apiStatus = null,
        array $context = [],
    ) {
        parent::__construct(
            message: $message,
            httpStatus: $httpStatus,
            apiStatus: $apiStatus,
            context: $context + ['errors' => $errors],
        );
    }

    /**
     * Field → array of messages, exactly as Laravel's validator returns.
     *
     *     [
     *         'file' => ['The file field is required.'],
     *     ]
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

<?php

namespace Tareef\Laravel\Exceptions;

/**
 * Thrown by Tareef::findOrFail($uuid) when no person with that UUID exists
 * in this tenant's library. `Tareef::find()` returns null instead of throwing.
 */
class PersonNotFoundException extends TareefException
{
    public function __construct(public readonly string $uuid)
    {
        parent::__construct(
            message: "No person with UUID [{$uuid}] in your Tareef library.",
            httpStatus: 404,
            apiStatus: 'not_found',
            context: ['uuid' => $uuid],
        );
    }
}

<?php

namespace Tareef\Laravel\Exceptions;

/**
 * Thrown by Tareef::register() or Tareef::verify() when none of the supplied
 * images contained a detectable face.
 *
 * Surface this to the user with a "try a clearer photo" message.
 */
class NoFaceDetectedException extends TareefException
{
    public function __construct(string $message = 'No face could be detected in the supplied image(s).')
    {
        parent::__construct(
            message: $message,
            httpStatus: 422,
            apiStatus: 'no_face',
        );
    }
}

<?php

namespace Tareef\Laravel\Exceptions;

/**
 * Thrown by Tareef::register() when the incoming face already belongs to
 * someone in this library.
 *
 * The exception carries the existing person's UUID so you can fetch their
 * record and decide what to do:
 *
 *     try {
 *         $person = Tareef::register(name: $name, images: [$file]);
 *     } catch (FaceAlreadyExistsException $e) {
 *         $existing = Tareef::find($e->existingUuid);
 *         return view('people.duplicate', compact('existing'));
 *     }
 */
class FaceAlreadyExistsException extends TareefException
{
    public function __construct(
        public readonly string $existingUuid,
        public readonly ?float $score = null,
        public readonly ?int $samples = null,
        string $message = '',
    ) {
        parent::__construct(
            message: $message ?: "This face is already enrolled as person [{$existingUuid}].",
            httpStatus: 422,
            apiStatus: 'face_exists',
            context: [
                'existing_uuid' => $existingUuid,
                'score'         => $score,
                'samples'       => $samples,
            ],
        );
    }
}

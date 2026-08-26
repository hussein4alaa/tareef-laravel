<?php

namespace Tareef\Laravel\Resources;

/**
 * Result of a Tareef::verify() call.
 *
 * `matched` is the property you almost always want to branch on:
 *
 *     $result = Tareef::verify($file);
 *     if ($result->matched) {
 *         return "Welcome back, {$result->name}!";
 *     }
 *
 * Score semantics: lower = closer. The upstream rejection threshold sits
 * around 0.50; anything <0.35 is a confident match. The `score` is null
 * when there was no match.
 *
 * When the call named a person (1:1), `personUuid` echoes who was checked
 * and `status` narrows the miss:
 *
 *     not_identical → a face was read, but it isn't them
 *     no_images     → that person has no reference photos yet
 */
class VerifyResult
{
    public function __construct(
        public readonly bool $matched,
        public readonly ?string $uuid = null,
        public readonly ?string $name = null,
        public readonly ?float $score = null,
        public readonly ?int $samples = null,
        public readonly string $status = 'not_found',
        public readonly ?string $message = null,
        public readonly ?string $personUuid = null,
        public readonly bool $lowQualityImage = false,
    ) {}

    /** Was this a 1:1 check against a named person, rather than a library search? */
    public function wasScopedToPerson(): bool
    {
        return $this->personUuid !== null;
    }

    public static function fromArray(array $data): self
    {
        $success = (bool) ($data['success'] ?? false);

        return new self(
            matched: $success,
            uuid: $data['uuid'] ?? null,
            name: $data['name'] ?? null,
            score: isset($data['score']) ? (float) $data['score'] : null,
            samples: isset($data['samples']) ? (int) $data['samples'] : null,
            status: (string) ($data['status'] ?? ($success ? 'ok' : 'not_found')),
            message: $data['message'] ?? null,
            personUuid: $data['person_uuid'] ?? null,
            lowQualityImage: (bool) ($data['low_quality_image'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'matched' => $this->matched,
            'uuid'    => $this->uuid,
            'name'    => $this->name,
            'score'   => $this->score,
            'samples' => $this->samples,
            'status'  => $this->status,
            'message' => $this->message,
            'person_uuid' => $this->personUuid,
            'low_quality_image' => $this->lowQualityImage,
        ];
    }
}

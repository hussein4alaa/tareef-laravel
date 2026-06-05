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
    ) {}

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
        ];
    }
}

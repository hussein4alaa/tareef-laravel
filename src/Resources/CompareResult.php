<?php

namespace Tareef\Laravel\Resources;

/**
 * Result of a Tareef::compare() call (1:1 face comparison).
 *
 *     $result = Tareef::compare($selfie, $idPhoto);
 *     if ($result->match) {
 *         return "Same person — " . round($result->similarity * 100) . "% similar";
 *     }
 *
 * Score semantics: `distance` is cosine distance (lower = closer) and
 * `similarity` is 1 − distance (1.0 = identical). `match` applies the
 * application's configured strictness.
 */
class CompareResult
{
    public function __construct(
        public readonly bool $match,
        public readonly ?float $distance = null,
        public readonly ?float $similarity = null,
        public readonly ?float $threshold = null,
        public readonly string $status = 'ok',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            match: (bool) ($data['match'] ?? false),
            distance: isset($data['distance']) ? (float) $data['distance'] : null,
            similarity: isset($data['similarity']) ? (float) $data['similarity'] : null,
            threshold: isset($data['threshold']) ? (float) $data['threshold'] : null,
            status: (string) ($data['status'] ?? 'ok'),
        );
    }

    public function toArray(): array
    {
        return [
            'match'      => $this->match,
            'distance'   => $this->distance,
            'similarity' => $this->similarity,
            'threshold'  => $this->threshold,
            'status'     => $this->status,
        ];
    }
}

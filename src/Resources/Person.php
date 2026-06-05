<?php

namespace Tareef\Laravel\Resources;

use Carbon\Carbon;
use Tareef\Laravel\TareefClient;

/**
 * A person enrolled in your Tareef library.
 *
 * Returned by:
 *   - Tareef::register(...)
 *   - Tareef::find($uuid)
 *   - Tareef::list()
 *
 * Fluent helpers (->addImages(), ->delete()) hit the API through the
 * client reference set at construction time. If you serialize a Person
 * across requests (cache, queue payload), reload it via Tareef::find($uuid)
 * on the other side — fluent methods on a deserialized instance are inert
 * because the client reference is transient.
 */
class Person
{
    /**
     * @param  string[]  $images  URLs / paths returned by the API for reference photos
     */
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly int $imageCount = 0,
        public readonly array $images = [],
        public readonly ?Carbon $createdAt = null,
        protected ?TareefClient $client = null,
    ) {}

    /**
     * Build a Person from a Tareef API response payload (`/people` or `/people/{uuid}`).
     */
    public static function fromArray(array $data, ?TareefClient $client = null): self
    {
        return new self(
            uuid: (string) ($data['uuid'] ?? ''),
            name: $data['name'] ?? null,
            phone: $data['phone'] ?? null,
            imageCount: (int) ($data['image_count'] ?? 0),
            images: array_values((array) ($data['images'] ?? [])),
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
            client: $client,
        );
    }

    /**
     * Attach one or more additional reference photos to this person.
     * Returns a refreshed Person with the new image_count.
     *
     * @param  array<\Illuminate\Http\UploadedFile|\SplFileInfo|string>  $images
     */
    public function addImages(array $images): self
    {
        $this->requireClient()->addImagesTo($this->uuid, $images);

        // Re-fetch to get the new image_count + images[] from upstream.
        return $this->requireClient()->findOrFail($this->uuid);
    }

    /**
     * Remove this person from the library. Returns true on success.
     */
    public function delete(): bool
    {
        return $this->requireClient()->delete($this->uuid);
    }

    /**
     * Serialize to an array (matches the API shape).
     */
    public function toArray(): array
    {
        return [
            'uuid'        => $this->uuid,
            'name'        => $this->name,
            'phone'       => $this->phone,
            'image_count' => $this->imageCount,
            'images'      => $this->images,
            'created_at'  => $this->createdAt?->toIso8601String(),
        ];
    }

    private function requireClient(): TareefClient
    {
        if (! $this->client) {
            throw new \LogicException(
                'This Person was hydrated without a client reference (probably '.
                'from cache or a queued job payload). Reload it via '.
                "Tareef::find('{$this->uuid}') before calling mutating methods."
            );
        }

        return $this->client;
    }
}

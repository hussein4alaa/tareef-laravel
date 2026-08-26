<?php

namespace Tareef\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use SplFileInfo;
use Tareef\Laravel\Exceptions\AuthenticationException;
use Tareef\Laravel\Exceptions\FaceAlreadyExistsException;
use Tareef\Laravel\Exceptions\NoFaceDetectedException;
use Tareef\Laravel\Exceptions\PersonNotFoundException;
use Tareef\Laravel\Exceptions\QuotaExceededException;
use Tareef\Laravel\Exceptions\ServiceUnavailableException;
use Tareef\Laravel\Exceptions\TareefException;
use Tareef\Laravel\Exceptions\ValidationException;
use Tareef\Laravel\Resources\CompareResult;
use Tareef\Laravel\Resources\Person;
use Tareef\Laravel\Resources\VerifyResult;

/**
 * Tareef HTTP client.
 *
 * Resolve through the container (`app(TareefClient::class)`) or use the
 * `Tareef` facade — both return the same singleton instance.
 *
 * Every public method either returns a typed resource or throws a
 * Tareef\Laravel\Exceptions\TareefException subclass. There is no
 * "string-status" return path — once you have a result, it's valid.
 */
class TareefClient
{
    public function __construct(
        protected HttpFactory $http,
        protected string $apiKey,
        protected string $baseUrl,
        protected int $timeout = 30,
        protected int $retries = 2,
        protected bool $throwOnQuota = true,
    ) {}

    // ── Health ──────────────────────────────────────────────────────────────

    /**
     * Is the Tareef API reachable and our API key accepted?
     * Returns true on a 200, false on anything else (no exception).
     */
    public function health(): bool
    {
        try {
            return $this->send('GET', '/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // ── People ──────────────────────────────────────────────────────────────

    /**
     * Enroll a new person with one or more reference photos.
     *
     * @param  array<UploadedFile|SplFileInfo|string>  $images  files or absolute paths
     *
     * @throws FaceAlreadyExistsException  if the face is already in the library
     * @throws NoFaceDetectedException     if none of the images contain a face
     * @throws AuthenticationException     401
     * @throws QuotaExceededException      429
     * @throws ServiceUnavailableException 5xx / connect failure
     * @throws TareefException             any other API error
     */
    public function register(string $name, array $images, ?string $phone = null): Person
    {
        $body = $this->buildMultipart(['name' => $name, 'phone' => $phone ?? ''], $images);

        $res = $this->send('POST', '/api/v1/people', $body);
        $data = $this->decode($res);

        if (! ($data['success'] ?? false)) {
            $this->throwForStatus($res, $data);
        }

        return Person::fromArray($data + ['name' => $name, 'phone' => $phone], $this);
    }

    /**
     * Add more reference photos to an existing person.
     */
    public function addImagesTo(string $uuid, array $images): Person
    {
        $body = $this->buildMultipart([], $images);

        $res = $this->send('POST', "/api/v1/people/{$uuid}/images", $body);
        $data = $this->decode($res);

        if (! ($data['success'] ?? false)) {
            $this->throwForStatus($res, $data);
        }

        return $this->findOrFail($uuid);
    }

    /**
     * Look up a person by UUID. Returns null if not found.
     */
    public function find(string $uuid): ?Person
    {
        try {
            return $this->findOrFail($uuid);
        } catch (PersonNotFoundException) {
            return null;
        }
    }

    /**
     * Look up a person by UUID. Throws PersonNotFoundException if not found.
     */
    public function findOrFail(string $uuid): Person
    {
        $res = $this->send('GET', "/api/v1/people/{$uuid}");

        if ($res->status() === 404) {
            throw new PersonNotFoundException($uuid);
        }

        $data = $this->decode($res);
        if (! ($data['success'] ?? false)) {
            $this->throwForStatus($res, $data);
        }

        return Person::fromArray($data, $this);
    }

    /**
     * List enrolled people (most recent first). Default limit is 100;
     * the API maxes out at whatever the dashboard exposes.
     *
     * @return Collection<int, Person>
     */
    public function list(int $limit = 100): Collection
    {
        $res = $this->send('GET', '/api/v1/people', query: ['limit' => $limit]);
        $data = $this->decode($res);

        if (! ($data['success'] ?? false)) {
            $this->throwForStatus($res, $data);
        }

        return collect($data['people'] ?? [])
            ->map(fn (array $row) => Person::fromArray($row, $this));
    }

    /**
     * Delete a person from the library. Returns true on success.
     */
    public function delete(string $uuid): bool
    {
        $res = $this->send('DELETE', "/api/v1/people/{$uuid}");

        if ($res->status() === 404) {
            throw new PersonNotFoundException($uuid);
        }

        $data = $this->decode($res);
        if (! ($data['success'] ?? false)) {
            $this->throwForStatus($res, $data);
        }

        return true;
    }

    // ── Verify ──────────────────────────────────────────────────────────────

    /**
     * Verify a face — against the whole library (1:N), or against ONE
     * known person (1:1) when you pass their UUID.
     *
     * Pass `$personUuid` when you already know who the photo is meant to
     * be ("is this really user #42?"). The image is then scored only
     * against that person's own reference photos, which is both faster
     * and more forgiving: a query that resembles ONE of their photos is
     * enough, so glasses on/off and lighting changes stop mattering as
     * much as they do in a library-wide search.
     *
     * Returns a VerifyResult either way. `matched` is true on a hit and
     * false on a no-match — neither is an exception. Real errors (auth,
     * quota, no face in the image, unknown person) do throw.
     *
     * @param  UploadedFile|SplFileInfo|string  $image  file or absolute path
     * @param  string|null  $personUuid  verify against this person only
     *
     * @throws PersonNotFoundException     $personUuid isn't in your library
     * @throws NoFaceDetectedException     no face in the supplied image
     * @throws AuthenticationException     401
     * @throws QuotaExceededException      429
     * @throws ServiceUnavailableException 5xx / connect failure
     */
    public function verify(
        UploadedFile|SplFileInfo|string $image,
        ?string $personUuid = null,
    ): VerifyResult {
        $personUuid = ($personUuid !== null && $personUuid !== '') ? $personUuid : null;

        // buildMultipart drops null fields, so this is a no-op for 1:N.
        $body = $this->buildMultipart(['person_uuid' => $personUuid], [$image]);

        $res = $this->send('POST', '/api/v1/verify', $body);
        $data = $this->decode($res);

        $status = $data['status'] ?? null;
        $http = $res->status();

        // The only 404 verify can produce is "you named a person who isn't
        // in this application" — a caller mistake, not a no-match.
        if ($http === 404 && $status === 'person_not_found') {
            throw new PersonNotFoundException((string) $personUuid);
        }

        // Real errors → exceptions. 422 / 400 mean the request itself was
        // malformed (most commonly: the `file` field didn't reach the API
        // because PHP upload_max_filesize / post_max_size dropped it on
        // the way out). 401 / 403 / 429 / 5xx are handled in throwForStatus.
        if ($http >= 400 && $http !== 404) {
            $this->throwForStatus($res, $data);
        }

        // no_face is a real error, not a "no match" result — surface it.
        if ($status === 'no_face') {
            throw new NoFaceDetectedException($data['message'] ?? 'No face could be detected in the supplied image.');
        }

        return VerifyResult::fromArray($data);
    }

    // ── Compare (1:1) ─────────────────────────────────────────────────────────

    /**
     * Compare two faces directly and report how similar they are. Unlike
     * verify() this touches no library — ideal for KYC ("does this selfie
     * match this ID photo?"). A no-match is NOT an exception; `match` is just
     * false. Real errors (auth, quota, no face) do throw.
     *
     * @param  UploadedFile|SplFileInfo|string  $imageA
     * @param  UploadedFile|SplFileInfo|string  $imageB
     */
    public function compare(
        UploadedFile|SplFileInfo|string $imageA,
        UploadedFile|SplFileInfo|string $imageB,
    ): CompareResult {
        [$streamA, $nameA] = $this->normaliseImage($imageA);
        [$streamB, $nameB] = $this->normaliseImage($imageB);

        $body = [
            ['name' => 'type', 'contents' => 'file'],
            ['name' => 'file1', 'contents' => $streamA, 'filename' => $nameA],
            ['name' => 'file2', 'contents' => $streamB, 'filename' => $nameB],
        ];

        $res = $this->send('POST', '/api/v1/compare', $body);
        $data = $this->decode($res);
        $http = $res->status();

        if ($http >= 400 && $http !== 404) {
            $this->throwForStatus($res, $data);
        }

        if (($data['status'] ?? null) === 'no_face') {
            throw new NoFaceDetectedException($data['message'] ?? 'No face could be detected in one or both images.');
        }

        return CompareResult::fromArray($data);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    protected function send(string $method, string $path, array $multipart = [], array $query = []): Response
    {
        $req = $this->request();
        $options = [];

        if ($query) {
            // Guzzle option rather than withQueryParameters(), which only
            // exists on Laravel >= 10.20.
            $options['query'] = $query;
        }

        if ($multipart) {
            // Pass parts through send() options rather than ->attach() +
            // bare ->send(): PendingRequest::parseHttpOptions only merges
            // $pendingFiles when $options[$bodyFormat] is set, so calling
            // send() with no options silently drops every attachment and
            // the API sees an empty body ("The file field is required.").
            $req = $req->asMultipart();
            $options['multipart'] = $multipart;
        }

        // Retry transient transport failures only — 4xx/5xx HTTP responses
        // are deterministic and shouldn't be retried. Hand-rolled instead of
        // PendingRequest::retry(): opting out of its post-retry
        // Response::throw() (which would bypass our exception mapping in
        // throwForStatus()) needs the `throw:` parameter, which early
        // Laravel 9 releases don't have.
        $attempt = 0;

        while (true) {
            try {
                return $req->send($method, $this->url($path), $options);
            } catch (ConnectionException $e) {
                if (++$attempt > $this->retries) {
                    throw new ServiceUnavailableException(
                        message: 'Could not reach Tareef at '.$this->baseUrl.'.',
                        context: ['path' => $path],
                        previous: $e,
                    );
                }

                usleep(250_000);
            }
        }
    }

    protected function request(): PendingRequest
    {
        return $this->http
            ->withToken($this->apiKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'tareef-laravel/'.self::version(),
            ])
            ->timeout($this->timeout);
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    protected function decode(Response $res): array
    {
        $body = (array) $res->json();

        return $body;
    }

    /**
     * Build the multipart parts array that Http::send() expects.
     *
     * - `file` for the first image (legacy single-file mode the API accepts)
     * - `files[]` repeated for each additional image
     */
    protected function buildMultipart(array $fields, array $images): array
    {
        $parts = [];

        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = ['name' => $name, 'contents' => (string) $value];
        }

        $images = array_values(array_filter($images));

        foreach ($images as $i => $image) {
            [$stream, $filename] = $this->normaliseImage($image);
            $parts[] = [
                'name' => $i === 0 ? 'file' : 'files[]',
                'contents' => $stream,
                'filename' => $filename,
            ];
        }

        return $parts;
    }

    /**
     * Turn anything file-like (UploadedFile, SplFileInfo, string path) into
     * [resource, filename] for the multipart builder.
     *
     * @return array{0: resource, 1: string}
     */
    protected function normaliseImage(UploadedFile|SplFileInfo|string $image): array
    {
        if (is_string($image)) {
            if (! is_file($image)) {
                throw new TareefException("Image path [{$image}] does not exist.");
            }
            return [fopen($image, 'rb'), basename($image)];
        }

        if ($image instanceof UploadedFile) {
            return [fopen($image->getRealPath(), 'rb'), $image->getClientOriginalName()];
        }

        // SplFileInfo (and subclasses like Illuminate\Http\File)
        return [fopen($image->getRealPath() ?: $image->getPathname(), 'rb'), $image->getFilename()];
    }

    /**
     * Translate an HTTP status + API payload into the most specific
     * exception subclass we have.
     */
    protected function throwForStatus(Response $res, array $data): never
    {
        $status = $data['status'] ?? null;
        $message = $data['message'] ?? null;
        $http = $res->status();

        // Tareef-specific signals first (they often arrive on 422, not 4xx).
        if ($status === 'face_exists' && ! empty($data['uuid'])) {
            throw new FaceAlreadyExistsException(
                existingUuid: (string) $data['uuid'],
                score: isset($data['score']) ? (float) $data['score'] : null,
                samples: isset($data['samples']) ? (int) $data['samples'] : null,
                message: $message ?: '',
            );
        }

        if ($status === 'no_face') {
            throw new NoFaceDetectedException($message ?: 'No face could be detected.');
        }

        if ($status === 'not_found') {
            throw new PersonNotFoundException($data['uuid'] ?? '');
        }

        // Laravel validation errors: 422 with {"message": "...", "errors": {...}}.
        // The most common cause is the `file` field never reaching the API,
        // which usually means PHP's upload_max_filesize / post_max_size on
        // the SENDING side silently dropped a large image. Surface it as a
        // typed exception so the caller doesn't have to inspect status codes.
        if (in_array($http, [400, 422], true)) {
            throw new ValidationException(
                message: $message ?: 'Tareef rejected the request as invalid (HTTP '.$http.').',
                errors: (array) ($data['errors'] ?? []),
                httpStatus: $http,
                apiStatus: $status,
                context: $data,
            );
        }

        // Then HTTP status codes.
        match (true) {
            $http === 401 => throw new AuthenticationException(
                message: $message ?: 'Tareef rejected the API key (401). Check TAREEF_API_KEY.',
                httpStatus: 401,
                apiStatus: $status,
            ),
            $http === 429 && $this->throwOnQuota => throw new QuotaExceededException(
                message: $message ?: 'Your Tareef plan quota is exhausted.',
                httpStatus: 429,
                apiStatus: $status,
            ),
            $http >= 500 => throw new ServiceUnavailableException(
                message: $message ?: "Tareef returned HTTP {$http}.",
                httpStatus: $http,
                apiStatus: $status,
            ),
            default => throw new TareefException(
                message: $message ?: "Tareef request failed with HTTP {$http}.",
                httpStatus: $http,
                apiStatus: $status,
                context: $data,
            ),
        };
    }

    public static function version(): string
    {
        return '1.1.0';
    }
}

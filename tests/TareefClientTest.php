<?php

namespace Tareef\Laravel\Tests;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Tareef\Laravel\Exceptions\AuthenticationException;
use Tareef\Laravel\Exceptions\FaceAlreadyExistsException;
use Tareef\Laravel\Exceptions\NoFaceDetectedException;
use Tareef\Laravel\Exceptions\PersonNotFoundException;
use Tareef\Laravel\Exceptions\QuotaExceededException;
use Tareef\Laravel\Facades\Tareef;
use Tareef\Laravel\Resources\Person;
use Tareef\Laravel\Resources\VerifyResult;
use Tareef\Laravel\TareefServiceProvider;

class TareefClientTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [TareefServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('tareef.api_key', 'frs_live_test');
        $app['config']->set('tareef.base_url', 'https://tareef.test');
    }

    public function test_register_returns_person(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people' => Http::response([
                'success' => true, 'status' => 'ok',
                'uuid' => 'uuid-1', 'images_registered' => 1, 'images_skipped' => 0,
            ], 200),
        ]);

        $person = Tareef::register(
            name: 'Jane Doe',
            images: [UploadedFile::fake()->image('selfie.jpg')],
            phone: '+9647712345678',
        );

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame('uuid-1', $person->uuid);
        $this->assertSame('Jane Doe', $person->name);
        $this->assertSame('+9647712345678', $person->phone);
    }

    public function test_register_throws_face_already_exists(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people' => Http::response([
                'success' => false, 'status' => 'face_exists',
                'uuid' => 'existing-uuid', 'score' => 0.18, 'samples' => 3,
            ], 422),
        ]);

        try {
            Tareef::register(name: 'X', images: [UploadedFile::fake()->image('x.jpg')]);
            $this->fail('Expected FaceAlreadyExistsException');
        } catch (FaceAlreadyExistsException $e) {
            $this->assertSame('existing-uuid', $e->existingUuid);
            $this->assertSame(0.18, $e->score);
            $this->assertSame(3, $e->samples);
        }
    }

    public function test_register_throws_no_face(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people' => Http::response([
                'success' => false, 'status' => 'no_face',
                'message' => 'No face could be extracted from any image.',
            ], 422),
        ]);

        $this->expectException(NoFaceDetectedException::class);
        Tareef::register(name: 'X', images: [UploadedFile::fake()->image('blank.jpg')]);
    }

    public function test_register_throws_auth_on_401(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people' => Http::response([
                'message' => 'Missing or invalid API key.',
            ], 401),
        ]);

        $this->expectException(AuthenticationException::class);
        Tareef::register(name: 'X', images: [UploadedFile::fake()->image('x.jpg')]);
    }

    public function test_register_throws_quota_on_429(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people' => Http::response([
                'message' => 'You have reached your plan limit for enrolled people.',
            ], 429),
        ]);

        $this->expectException(QuotaExceededException::class);
        Tareef::register(name: 'X', images: [UploadedFile::fake()->image('x.jpg')]);
    }

    public function test_verify_matched(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/verify' => Http::response([
                'success' => true, 'uuid' => 'uuid-1', 'name' => 'Jane',
                'score' => 0.12, 'samples' => 2,
            ], 200),
        ]);

        $result = Tareef::verify(UploadedFile::fake()->image('selfie.jpg'));

        $this->assertInstanceOf(VerifyResult::class, $result);
        $this->assertTrue($result->matched);
        $this->assertSame('uuid-1', $result->uuid);
        $this->assertSame('Jane', $result->name);
        $this->assertSame(0.12, $result->score);
    }

    public function test_verify_not_matched_does_not_throw(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/verify' => Http::response([
                'success' => false, 'status' => 'not_found',
                'message' => 'No matching person in your library.',
            ], 200),
        ]);

        $result = Tareef::verify(UploadedFile::fake()->image('stranger.jpg'));

        $this->assertFalse($result->matched);
        $this->assertSame('not_found', $result->status);
        $this->assertNull($result->uuid);
    }

    public function test_find_returns_null_on_404(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people/missing' => Http::response(null, 404),
        ]);

        $this->assertNull(Tareef::find('missing'));
    }

    public function test_find_or_fail_throws_404(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people/missing' => Http::response(null, 404),
        ]);

        $this->expectException(PersonNotFoundException::class);
        Tareef::findOrFail('missing');
    }

    public function test_list_returns_collection(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people*' => Http::response([
                'success' => true, 'count' => 2,
                'people' => [
                    ['uuid' => 'a', 'name' => 'Alice', 'phone' => null, 'image_count' => 1, 'created_at' => '2026-01-01T00:00:00Z'],
                    ['uuid' => 'b', 'name' => 'Bob',   'phone' => '+1', 'image_count' => 3, 'created_at' => '2026-01-02T00:00:00Z'],
                ],
            ], 200),
        ]);

        $people = Tareef::list();

        $this->assertCount(2, $people);
        $this->assertSame('a', $people->first()->uuid);
        $this->assertSame('Bob', $people->last()->name);
    }

    public function test_delete_returns_true(): void
    {
        Http::fake([
            'https://tareef.test/api/v1/people/x' => Http::response([
                'success' => true, 'status' => 'ok', 'uuid' => 'x',
            ], 200),
        ]);

        $this->assertTrue(Tareef::delete('x'));
    }

    public function test_health_true_on_200(): void
    {
        Http::fake([
            'https://tareef.test/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->assertTrue(Tareef::health());
    }

    public function test_health_false_on_500(): void
    {
        Http::fake([
            'https://tareef.test/health' => Http::response(null, 500),
        ]);

        $this->assertFalse(Tareef::health());
    }
}

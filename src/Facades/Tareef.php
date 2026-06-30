<?php

namespace Tareef\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Tareef\Laravel\Resources\CompareResult;
use Tareef\Laravel\Resources\Person;
use Tareef\Laravel\Resources\VerifyResult;

/**
 * @method static bool          health()
 * @method static Person        register(string $name, array $images, ?string $phone = null)
 * @method static Person        addImagesTo(string $uuid, array $images)
 * @method static ?Person       find(string $uuid)
 * @method static Person        findOrFail(string $uuid)
 * @method static \Illuminate\Support\Collection list(int $limit = 100)
 * @method static bool          delete(string $uuid)
 * @method static VerifyResult  verify(\Illuminate\Http\UploadedFile|\SplFileInfo|string $image)
 * @method static CompareResult compare(\Illuminate\Http\UploadedFile|\SplFileInfo|string $imageA, \Illuminate\Http\UploadedFile|\SplFileInfo|string $imageB)
 *
 * @see \Tareef\Laravel\TareefClient
 */
class Tareef extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tareef';
    }
}

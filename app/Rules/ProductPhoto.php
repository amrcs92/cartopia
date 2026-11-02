<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * The shop's photo rules, checked in the order an admin would fix them: type, size, shape, resolution.
 */
class ProductPhoto implements ValidationRule
{
    public const int MAX_BYTES = 1048576;

    public const int MIN_SIDE = 1200;

    /** @var list<string> */
    private const array MIME_TYPES = ['image/jpeg', 'image/webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        if (! in_array($value->getMimeType(), self::MIME_TYPES, true)) {
            $fail('Photos need to be JPG or WebP.');

            return;
        }

        if (($value->getSize() ?: 0) >= self::MAX_BYTES) {
            $fail('Photos need to be under 1 MB.');

            return;
        }

        $dimensions = @getimagesize($value->getRealPath());

        if ($dimensions === false) {
            $fail('Photos need to be JPG or WebP.');

            return;
        }

        [$width, $height] = $dimensions;

        if ($width !== $height) {
            $fail('Photos need to be square.');

            return;
        }

        if ($width < self::MIN_SIDE) {
            $fail("This photo is {$width} × {$height}px. Photos need to be at least ".self::MIN_SIDE.' × '.self::MIN_SIDE.'px.');
        }
    }
}

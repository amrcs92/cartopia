<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A review as the product page shows it. Expects `user` loaded.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => self::author($this->user->name),
            'stars' => $this->stars,
            'body' => $this->body,
            'date' => $this->created_at?->format('j M Y'),
            'edited' => $this->isEdited(),
        ];
    }

    /**
     * "Amr Saleh" → "Amr S.": the first name and the last name's initial, or
     * just the first name when there's one word.
     */
    public static function author(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [''];
        $first = Str::ucfirst($words[0]);

        if (count($words) === 1) {
            return $first;
        }

        return $first.' '.Str::upper(mb_substr((string) end($words), 0, 1)).'.';
    }
}

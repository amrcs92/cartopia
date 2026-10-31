<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of products whose pagination links keep array filters in bracket form
 * (`aisle[]=fruit`), as the shop writes them, instead of `aisle[0]=fruit`.
 */
class ProductCollection extends ResourceCollection
{
    public $collects = ProductResource::class;

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $default['links'] = array_map($this->bracketed(...), $default['links']);
        $default['meta']['links'] = array_map(
            fn (array $link): array => ['url' => $this->bracketed($link['url'])] + $link,
            $default['meta']['links'],
        );

        return $default;
    }

    private function bracketed(?string $url): ?string
    {
        return $url === null ? null : preg_replace('/%5B\d+%5D=/i', '%5B%5D=', $url);
    }
}

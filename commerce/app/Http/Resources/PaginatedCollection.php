<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Standard paginated list (same format as Identity):
 * { "data": [...], "meta": { current_page, per_page, total, last_page } }
 */
class PaginatedCollection extends ResourceCollection
{
    public function __construct(LengthAwarePaginator $resource, string $collects)
    {
        $this->collects = $collects;
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return ['data' => $this->collection];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'per_page' => $paginated['per_page'],
                'total' => $paginated['total'],
                'last_page' => $paginated['last_page'],
            ],
        ];
    }
}

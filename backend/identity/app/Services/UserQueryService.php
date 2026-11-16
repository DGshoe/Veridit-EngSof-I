<?php

namespace App\Services;

use App\Http\Requests\ListUsersRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-side queries over users (Consulta 2).
 */
class UserQueryService
{
    /**
     * @param  array{type?: string, status?: string, search?: ?string, per_page?: int|string, page?: int|string}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return User::query()
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', "%{$search}%")               // case-insensitive (ILIKE on Postgres)
                ->orWhereLike('email', '%'.mb_strtolower($search).'%')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')                                  // stable order for equal timestamps
            ->paginate(
                perPage: (int) ($filters['per_page'] ?? ListUsersRequest::DEFAULT_PER_PAGE),
                page: (int) ($filters['page'] ?? 1),
            );
    }
}

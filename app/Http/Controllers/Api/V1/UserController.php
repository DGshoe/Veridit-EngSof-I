<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserQueryService $users) {}

    /** Consulta 1 — the authenticated user. */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Consulta 2 — paginated, filterable list (admin). */
    public function index(ListUsersRequest $request): UserCollection
    {
        Gate::authorize('viewAny', User::class);

        return new UserCollection($this->users->paginate($request->validated()));
    }

    /** Detail — admin, or the user themself. */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }
}

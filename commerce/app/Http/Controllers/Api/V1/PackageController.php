<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    /** D2 catalog (REQ05). Public. */
    public function index(): JsonResponse
    {
        $packages = Package::query()->active()->orderBy('credits')->get();

        return response()->json(['data' => PackageResource::collection($packages)]);
    }

    /** Credits consumed by each capture type. Public. */
    public function captureCosts(): JsonResponse
    {
        return response()->json(['data' => config('commerce.capture_costs')]);
    }
}

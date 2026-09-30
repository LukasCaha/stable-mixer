<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Stable;
use Illuminate\Http\JsonResponse;

class StableController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $code = strtoupper($code);

        $stable = preg_match('/^[A-Z0-9]{8}$/', $code) === 1
            ? Stable::query()->where('tenant_code', $code)->where('is_active', true)->first()
            : null;

        if ($stable === null) {
            return $this->missing();
        }

        return response()->json([
            'name' => $stable->name,
            'tenant_code' => $stable->tenant_code,
        ]);
    }

    private function missing(): JsonResponse
    {
        return response()->json([
            'message' => 'Stable not found.',
        ], 404);
    }
}

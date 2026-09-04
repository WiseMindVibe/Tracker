<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClickStatusRequest;
use App\Models\ClicksRedirections;
use Illuminate\Http\JsonResponse;

class ClickRedirectionController extends Controller
{
    public function updateStatus(UpdateClickStatusRequest $request, string $click_id): JsonResponse
    {
        $click = ClicksRedirections::where('click_id', $click_id)->first();

        if (! $click) {
            return response()->json([
                'message' => 'Click not found.',
            ], 404);
        }

        $click->update([
            'status' => $request->validated('status'),
        ]);

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $click,
        ]);
    }
}

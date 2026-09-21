<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Postbacks\PostbackException;
use App\Services\PostbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostbackController extends Controller
{
    public function store(Request $request, string $affiliate, PostbackService $postbacks): JsonResponse
    {
        try {
            $result = $postbacks->process($affiliate, $request->all());
        } catch (PostbackException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json([
            'message' => $result->created ? 'Postback accepted.' : 'Postback already processed.',
            'duplicate' => ! $result->created,
        ]);
    }
}

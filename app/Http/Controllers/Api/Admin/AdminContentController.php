<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContentController extends Controller
{
    public function index(): JsonResponse
    {
        $blocks = ContentBlock::orderBy('key')->get();
        return response()->json($blocks);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $block = ContentBlock::where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'value' => 'required|string',
        ]);

        $block->update(['value' => $validated['value']]);

        return response()->json([
            'message' => 'Content block updated.',
            'data' => $block->fresh(),
        ]);
    }
}
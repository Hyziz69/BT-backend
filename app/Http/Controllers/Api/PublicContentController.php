<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;

class PublicContentController extends Controller
{
    public function index(): JsonResponse
    {
        $blocks = ContentBlock::all()->mapWithKeys(fn ($block) => [$block->key => $block->value]);
        return response()->json($blocks);
    }
}
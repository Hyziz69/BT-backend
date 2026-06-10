<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AdminContentController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->loadBlocks());
        } catch (Throwable $e) {
            return response()->json($this->defaultBlocks());
        }
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:10000'],
        ]);

        $blocks = $this->loadBlocks();
        $found = false;

        foreach ($blocks as &$block) {
            if (($block['key'] ?? '') === $key) {
                $block['value'] = $validated['value'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json([
                'message' => 'Content block not found.',
            ], 404);
        }

        $this->saveBlocks($blocks);

        return response()->json([
            'message' => 'Content block updated.',
            'blocks' => $blocks,
        ]);
    }

    private function loadBlocks(): array
    {
        $path = storage_path('app/content_blocks.json');

        if (!file_exists($path)) {
            $blocks = $this->defaultBlocks();
            $this->saveBlocks($blocks);

            return $blocks;
        }

        $content = file_get_contents($path);
        $blocks = json_decode($content ?: '', true);

        if (!is_array($blocks)) {
            return $this->defaultBlocks();
        }

        return $blocks;
    }

    private function saveBlocks(array $blocks): void
    {
        $directory = storage_path('app');

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            storage_path('app/content_blocks.json'),
            json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function defaultBlocks(): array
    {
        return [
            [
                'id' => '1',
                'key' => 'hero_title',
                'label' => 'Hero title',
                'type' => 'text',
                'value' => 'Nitra Tech Innovation',
            ],
            [
                'id' => '2',
                'key' => 'hero_subtitle',
                'label' => 'Hero subtitle',
                'type' => 'textarea',
                'value' => 'Innovation platform for students, mentors and companies.',
            ],
            [
                'id' => '3',
                'key' => 'program_a_title',
                'label' => 'Program A title',
                'type' => 'text',
                'value' => 'Program A',
            ],
            [
                'id' => '4',
                'key' => 'program_a_description',
                'label' => 'Program A description',
                'type' => 'textarea',
                'value' => 'Create your own innovation project and apply with your team.',
            ],
            [
                'id' => '5',
                'key' => 'program_b_title',
                'label' => 'Program B title',
                'type' => 'text',
                'value' => 'Program B',
            ],
            [
                'id' => '6',
                'key' => 'program_b_description',
                'label' => 'Program B description',
                'type' => 'textarea',
                'value' => 'Solve real company challenges with your team.',
            ],
        ];
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Throwable;

class PublicContentController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $blocks = $this->loadBlocks();
        } catch (Throwable $e) {
            $blocks = $this->defaultBlocks();
        }

        $public = [];

        foreach ($blocks as $block) {
            $public[$block['key']] = $block['value'];
        }

        return response()->json($public);
    }

    private function loadBlocks(): array
    {
        $path = storage_path('app/content_blocks.json');

        if (!file_exists($path)) {
            return $this->defaultBlocks();
        }

        $content = file_get_contents($path);
        $blocks = json_decode($content ?: '', true);

        if (!is_array($blocks)) {
            return $this->defaultBlocks();
        }

        return $blocks;
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
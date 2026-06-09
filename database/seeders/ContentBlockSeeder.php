<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            ['key' => 'hero_subtitle',        'label' => 'Hero subtitle',           'type' => 'text', 'value' => 'Nitriansky technologický inkubátor'],
            ['key' => 'hero_title',            'label' => 'Hero title',              'type' => 'text', 'value' => 'Portál účastníka'],
            ['key' => 'hero_desc',             'label' => 'Hero description',        'type' => 'text', 'value' => 'Spravuj tímy, podávaj prihlášky a sleduj pokrok na jednom mieste'],
            ['key' => 'pillars_title',         'label' => 'Pillars section title',   'type' => 'text', 'value' => 'Štyri strategické piliere'],
            ['key' => 'pillars_desc',          'label' => 'Pillars section description', 'type' => 'text', 'value' => 'NTI vznikol ako odpoveď na odliv technologických talentov a potrebu systematickej podpory inovatívnych projektov v regióne Nitry.'],
            ['key' => 'pillar_1_title',        'label' => 'Pillar 1 title',          'type' => 'text', 'value' => 'Inkubácia'],
            ['key' => 'pillar_1_desc',         'label' => 'Pillar 1 description',    'type' => 'text', 'value' => 'Podporujeme vznik a akceleráciu projektov — od nápadu cez prototyp až po produkt s medzinárodným potenciálom.'],
            ['key' => 'pillar_2_title',        'label' => 'Pillar 2 title',          'type' => 'text', 'value' => 'Partnerstvá'],
            ['key' => 'pillar_2_desc',         'label' => 'Pillar 2 description',    'type' => 'text', 'value' => 'Prepájame firmy, organizácie a inštitúcie z regiónu — spoločne vytvárame reálne príležitosti pre študentov.'],
            ['key' => 'pillar_3_title',        'label' => 'Pillar 3 title',          'type' => 'text', 'value' => 'Mentoring'],
            ['key' => 'pillar_3_desc',         'label' => 'Pillar 3 description',    'type' => 'text', 'value' => 'Každý tím dostane mentora z praxe. Pravidelné konzultácie, spätná väzba a sledovanie míľnikov projektu.'],
            ['key' => 'pillar_4_title',        'label' => 'Pillar 4 title',          'type' => 'text', 'value' => 'Retencia'],
            ['key' => 'pillar_4_desc',         'label' => 'Pillar 4 description',    'type' => 'text', 'value' => 'Budujeme komunitu absolventov, zbierame úspešné príbehy a udržiavame dlhodobú väzbu talentov na región.'],
            ['key' => 'program_a_title',       'label' => 'Program A card title',    'type' => 'text', 'value' => 'Grantový inkubačný program'],
            ['key' => 'program_a_desc',        'label' => 'Program A card description', 'type' => 'text', 'value' => 'Vlastný inovatívny nápad → financovanie + mentoring → startup alebo produkt'],
            ['key' => 'program_b_title',       'label' => 'Program B card title',    'type' => 'text', 'value' => 'Program živej praxe'],
            ['key' => 'program_b_desc',        'label' => 'Program B card description', 'type' => 'text', 'value' => 'Reálne zadania od firiem → prax + odmena + Product Owner → zákazkový softvér'],
            ['key' => 'footer_copyright',      'label' => 'Footer copyright text',   'type' => 'text', 'value' => '© 2026 Nitriansky technologický inkubátor'],
        ];

        foreach ($blocks as $block) {
            ContentBlock::firstOrCreate(['key' => $block['key']], $block);
        }
    }
}
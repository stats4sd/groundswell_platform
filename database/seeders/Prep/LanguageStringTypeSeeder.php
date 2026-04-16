<?php

namespace Database\Seeders\Prep;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageStringTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languageStringTypes = [
            ['name' => 'label'],
            ['name' => 'hint'],
            ['name' => 'relevant_message'],
            ['name' => 'required_message'],
            ['name' => 'constraint_message'],
            ['name' => 'guidance_hint'],
            ['name' => 'mediaimage'],
            ['name' => 'mediaaudio'],
            ['name' => 'mediavideo'],
            ['name' => 'image'],
            ['name' => 'audio'],
            ['name' => 'video'],

        ];

        DB::table('language_string_types')->insert($languageStringTypes);
    }
}

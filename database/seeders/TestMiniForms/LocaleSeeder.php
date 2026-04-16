<?php

namespace Database\Seeders\TestMiniForms;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;

class LocaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // retreive en locale, which was creted when creating a team
        $localeEn = Locale::where('language_id', 41)->first();

        // create es locale
        $localeEs = Language::firstWhere('iso_alpha2', 'es')
            ->locales()
            ->create([
                'is_default' => true,
            ]);

        Team::all()->each(function (Team $team) use ($localeEs, $localeEn) {
            $team->languages()->updateExistingPivot($localeEn->language_id, ['locale_id' => $localeEn->id]);

            $team->languages()->updateExistingPivot($localeEs->language->id, ['locale_id' => $localeEs->id]);
        });
    }
}

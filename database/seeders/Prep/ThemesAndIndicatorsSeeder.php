<?php

namespace Database\Seeders\Prep;

use App\Models\Holpa\Domain;
use App\Models\Holpa\Theme;
use DB;
use Illuminate\Database\Seeder;

class ThemesAndIndicatorsSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {

        $agDomain = Domain::create(['id' => 1, 'name' => 'Agricultural']);
        $envDomain = Domain::create(['id' => 2, 'name' => 'Environmental']);
        $ecDomain = Domain::create(['id' => 3, 'name' => 'Economic']);
        $socDomain = Domain::create(['id' => 4, 'name' => 'Social']);

        $locationContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Location']);
        $respondentCharacteristicsContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Respondent characteristics']);
        $householdCharacteristicsContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Household characteristics']);
        $farmCharacteristicsContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Farm characteristics']);
        $productionSystemsContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Production systems']);
        $climateChangeContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Climate change']);
        $motivationToTransitionContextTheme = Theme::create(['module' => 'Context', 'domain_id' => NULL, 'name' => 'Motivation to transition']);

        $recyclingAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Recycling']);
        $inputReductionAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Input reduction']);
        $soilHealthAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Soil health']);
        $animalHealthAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Animal health']);
        $biodiversityAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Biodiversity']);
        $synergiesAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Synergies']);
        $economicDiversificationAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Economic diversification']);
        $knowledgeCoCreationAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Knowledge co-creation']);
        $governanceAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Governance']);
        $socialValuesAndDietsAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Social values and diets']);
        $fairnessAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Fairness']);
        $connectivityAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Connectivity']);
        $participationAeTheme = Theme::create(['module' => 'Agroecology', 'domain_id' => NULL, 'name' => 'Participation']);

        $cropHealthTheme = $agDomain->themes()->create(['module' => 'Performance', 'name' => 'Crop health']);
        $animalHealthTheme = $agDomain->themes()->create(['module' => 'Performance', 'name' => 'Animal health']);
        $soilHealthTheme = $agDomain->themes()->create(['module' => 'Performance', 'name' => 'Soil health']);
        $nutrientUseTheme = $agDomain->themes()->create(['module' => 'Performance', 'name' => 'Nutrient use']);

        $biodiversityTheme = $envDomain->themes()->create(['module' => 'Performance', 'name' => 'Biodiversity']);
        $agroBiodiversityTheme = $envDomain->themes()->create(['module' => 'Performance', 'name' => 'Agrobiodiversity']);
        $landscapeComplexityTheme = $envDomain->themes()->create(['module' => 'Performance', 'name' => 'Landscape complexity']);
        $climateMitigationTheme = $envDomain->themes()->create(['module' => 'Performance', 'name' => 'Climate mitigation']);
        $waterTheme =$envDomain->themes()->create(['module' => 'Performance', 'name' => 'Water']);
        $energyUseTheme = $envDomain->themes()->create(['module' => 'Performance', 'name' => 'Energy use']);

        $incomeTheme = $ecDomain->themes()->create(['module' => 'Performance', 'name' => 'Income']);
        $agriculturalProductivityTheme = $ecDomain->themes()->create(['module' => 'Performance', 'name' => 'Agricultural productivity']);
        $labourProductivityTheme = $ecDomain->themes()->create(['module' => 'Performance', 'name' => 'Labour productivity']);
        $climateResilienceTheme = $ecDomain->themes()->create(['module' => 'Performance', 'name' => 'Climate resilience']);

        $dietQualityTheme = $socDomain->themes()->create(['module' => 'Performance', 'name' => 'Diet quality']);
        $farmerAgencyTheme = $socDomain->themes()->create(['module' => 'Performance', 'name' => 'Farmer agency']);
        $humanWellBeingTheme = $socDomain->themes()->create(['module' => 'Performance', 'name' => 'Human well-being']);
        $landTenureTheme = $socDomain->themes()->create(['module' => 'Performance', 'name' => 'Land tenure']);


        // add global indicators
        // TODO: Update these with the full / updated list from the HOLPA team
        $agroBiodiversityTheme->globalIndicators()->create(['name' => 'Crop, livestock and fish diversity']);
        $agroBiodiversityTheme->globalIndicators()->create(['name' => 'Insect, mammal and tree diversity']);
        $agroBiodiversityTheme->globalIndicators()->create(['name' => 'Landscape complexity']);

        $waterTheme->globalIndicators()->create(['name' => 'Water conservation']);
        $energyUseTheme->globalIndicators()->create(['name' => 'Sustainable energy use']);
        $climateMitigationTheme->globalIndicators()->create(['name' => 'Net greenhouse gas emissions']);

        $cropHealthTheme->globalIndicators()->create(['name' => 'Crop health']);
        $animalHealthTheme->globalIndicators()->create(['name' => 'Animal health']);
        $soilHealthTheme->globalIndicators()->create(['name' => 'Soil organic carbon (alternative: qualitative measure of soil health)']);
        $nutrientUseTheme->globalIndicators()->create(['name' => 'Nutrient use']);

        $dietQualityTheme->globalIndicators()->create(['name' => 'Diet quality']);
        $landTenureTheme->globalIndicators()->create(['name' => 'Land tenure security']);
        $farmerAgencyTheme->globalIndicators()->create(['name' => 'Farmer agency']);
        $humanWellBeingTheme->globalIndicators()->create(['name' => 'Personal Wellbeing Index']);

        $agriculturalProductivityTheme->globalIndicators()->create(['name' => 'Crop and livestock productivity']);
        $labourProductivityTheme->globalIndicators()->create(['name' => 'Labour inputs per unit agricultural land area']);
        $incomeTheme->globalIndicators()->create(['name' => 'Total income; Income stability']);
        $climateResilienceTheme->globalIndicators()->create(['name' => 'Resilience score']);

        $biodiversityTheme->globalIndicators()->create(['name' => 'Bat, bird and insect diversity  ']);
        $biodiversityTheme->globalIndicators()->create(['name' => 'Insect pollinator diversity']);
        $soilHealthTheme->globalIndicators()->create(['name' => 'Soil health']);
        $nutrientUseTheme->globalIndicators()->create(['name' => 'Nutrient use efficiency']);
        $nutrientUseTheme->globalIndicators()->create(['name' => 'Nutrient Balance']);
        $climateMitigationTheme->globalIndicators()->create(['name' => 'Greenhouse gas emissions']);
        $climateMitigationTheme->globalIndicators()->create(['name' => 'Enteric methane emission intensity (emission per unit of product and emission per unit of fodder dry matter intake)']);
    }
}

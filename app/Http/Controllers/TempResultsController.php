<?php

namespace App\Http\Controllers;

use App\Models\PreviousResult;
use HiFolks\Statistics\Stat;
use Illuminate\Http\Request;

class TempResultsController extends Controller
{
    // index
    public function index()
    {
        return PreviousResult::with('country')
            ->select(
                'country_id',
                'country_code',
                'longitude',
                'latitude',
                'sector',
                'gender',
                'farm_size',
                'overall_recycling_score',
                'overall_input_reduction_score',
                'overall_soil_health_score',
                'overall_animal_health_score',
                'overall_biodiversity_score',
                'overall_synergy_score',
                'overall_economic_diversification_score',
                'overall_co_creation_knowledge_score',
                'overall_governance_score',
                'overall_social_values_diet_score',
                'overall_fairness_score',
                'overall_connectivity_score',
                'overall_participation_score',
            )
            ->get()
            ->map(function ($result) {

                $result->overall_ae_score = Stat::median([
                    $result->overall_recycling_score,
                    $result->overall_input_reduction_score,
                    $result->overall_soil_health_score,
                    $result->overall_animal_health_score,
                    $result->overall_biodiversity_score,
                    $result->overall_synergy_score,
                    //$result->overall_economic_diversification_score,
                    $result->overall_co_creation_knowledge_score,
                    $result->overall_governance_score,
                    $result->overall_social_values_diet_score,
                    $result->overall_fairness_score,
                    //  $result->overall_connectivity_score,
                    $result->overall_participation_score,
                ]);


                return $result;
            });
    }
}

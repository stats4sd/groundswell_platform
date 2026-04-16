<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TempResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Function to generate normally distributed random numbers
        // using Box-Muller transform
        // Replacement for old PECL stats extension function stats_rand_gen_normal
        // source: https://www.php.net/manual/en/function.stats-rand-gen-normal.php
        if (!function_exists('stats_rand_gen_normal')) {
            function stats_rand_gen_normal($av, $sd): float
            {
                $x = mt_rand() / mt_getrandmax();
                $y = mt_rand() / mt_getrandmax();

                $value = sqrt(-2 * log($x)) * cos(2 * pi() * $y) * $sd + $av;

                return $value > 0 ? $value : 0.2;
            }
        }

        // delete all existing temp results
        \DB::table('temp_results')->delete();

        // Create temp results per country:

        // India = 300
        // All in the Madhya Pradesh region
        // within the bounds of lat 21.97, 23.63, and long 77.75, 80.89


        for ($i = 0; $i < 300; $i++) {

            $age = stats_rand_gen_normal(av: 49.5, sd: 14.7);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 3.6, sd: 2.5);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 20) {
                $farmSize = 20;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            \DB::table('temp_results')->insert([
                'country_id' => '356',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => rand(2197000, 2363000) / 100000,
                'longitude' => rand(7775000, 8089000) / 100000,
            ]);

        }

        // Kenya = 479

        // For Kiambu: Lat between -1.12 and -0.887; Long between 36.69 and 36.91
        // For Makueni: Lat between -2.26 and -2.38; Long between 37.7 and 38.11
        // 50% roughly in each area

        for ($i = 0; $i < 479; $i++) {
            $age = stats_rand_gen_normal(av: 45.3, sd: 12.5);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 2.4, sd: 1.8);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 15) {
                $farmSize = 15;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            $kiambu = (rand(0, 1) == 1);

            \DB::table('temp_results')->insert([
                'country_id' => '404',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => $kiambu ? (rand(88700, 112300) / -100000) : (rand(226000, 238000) / -100000),
                'longitude' => $kiambu ? (rand(3669000, 3691000) / 100000) : (rand(3770000, 3811000) / 100000),
            ]);
        }


        // Laos = 220
        // 14.57 to 14.91 lat; 106.87 to 107.31 long

        for ($i = 0; $i < 220; $i++) {
            $age = stats_rand_gen_normal(av: 42.7, sd: 11.3);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 1.8, sd: 1.2);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 10) {
                $farmSize = 10;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            \DB::table('temp_results')->insert([
                'country_id' => '418',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => rand(1457000, 1491000) / 100000,
                'longitude' => rand(10687000, 10731000) / 100000,
            ]);
        }


        // Peru == 200
        // -14.69 to -15.52 lat; -69.89 to -75.32 long

        for ($i = 0; $i < 200; $i++) {
            $age = stats_rand_gen_normal(av: 48.2, sd: 13.4);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 2.1, sd: 1.5);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 12) {
                $farmSize = 12;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            \DB::table('temp_results')->insert([
                'country_id' => '604',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => rand(-1552000, -1469000) / 100000,
                'longitude' => rand(-7532000, -6989000) / 100000,
            ]);
        }

        // Senegal = 200
        // 14.13 to 14.5 lat; -16.36 to -16.73 long
        for ($i = 0; $i < 200; $i++) {
            $age = stats_rand_gen_normal(av: 46.8, sd: 12.1);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 2.7, sd: 1.9);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 15) {
                $farmSize = 15;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            \DB::table('temp_results')->insert([
                'country_id' => '686',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => rand(1413000, 1450000) / 100000,
                'longitude' => rand(-1673000, -1636000) / 100000,
            ]);
        }

        // Tunisia = 176
        // 35.83 to 36.10 lat; 9.22 to 9.6 long;

        for ($i = 0; $i < 176; $i++) {
            $age = stats_rand_gen_normal(av: 44.6, sd: 11.8);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }
            $farmSize = stats_rand_gen_normal(av: 3.1, sd: 2.0);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 18) {
                $farmSize = 18;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            \DB::table('temp_results')->insert([
                'country_id' => '788',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => rand(3583000, 3610000) / 100000,
                'longitude' => rand(922000, 960000) / 100000,
            ]);
        }

        // Zimbabwe = 200
        // 100 in each of the following regions:
        // Mbire: -16.025 to -16.35 lat; 30.1 to 30.79 long
        // Murehwa: -17.5 to -17.96 lat; 31.5 to 32.1 long

        for ($i = 0; $i < 200; $i++) {
            $age = stats_rand_gen_normal(av: 47.9, sd: 13.2);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 2.9, sd: 2.1);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 15) {
                $farmSize = 15;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            $mbire = ($i < 100);

            \DB::table('temp_results')->insert([
                'country_id' => '716',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => $mbire ? (rand(-1635000, -1602500) / 100000) : (rand(-1796000, -1750000) / 100000),
                'longitude' => $mbire ? (rand(3010000, 3079000) / 100000) : (rand(3150000, 3210000) / 100000),
            ]);

        }
        // burkina faso = 204
        // Hauts-Bassins region: 11.56 to 12.17 lat; -3.142 to -4.416 long

        for ($i = 0; $i < 204; $i++) {
            $age = stats_rand_gen_normal(av: 43.5, sd: 12.0);
            if ($age < 18) {
                $age = 18;
            } elseif ($age > 90) {
                $age = 90;
            }

            $farmSize = stats_rand_gen_normal(av: 2.5, sd: 1.7);

            if ($farmSize < 0.1) {
                $farmSize = 0.1;
            } elseif ($farmSize > 12) {
                $farmSize = 12;
            }

            // round farm size and age
            $farmSize = round($farmSize, 2);
            $age = round($age);

            \DB::table('temp_results')->insert([
                'country_id' => '854',
                'gender' => (rand(0, 1) == 1) ? 'Male' : 'Female',
                'education_level' => ['None', 'Primary', 'Secondary', 'Tertiary'][array_rand(['None', 'Primary', 'Secondary', 'Tertiary'])],
                'age' => $age,
                'farm_size' => $farmSize,
                'latitude' => rand(1156000, 1217000) / 100000,
                'longitude' => rand(-441600, -314200) / 100000,
            ]);
        }


        // for every temp results score, add random AE scores between 1 and 5
        $tempResults = \DB::table('temp_results')->get()->groupBy('country_id');


        // modify the distribution to get different averages per country
        foreach ($tempResults as $countryId => $countryResults) {

            $mean = rand(24, 41) / 10; // mean between 2.4 and 4.1
            $stddev = rand(10, 20) / 10; // stddev between 1.0 and 2.0

            foreach ($countryResults as $tempResult) {
                \DB::table('temp_results')->where('id', $tempResult->id)
                    ->update([
                        'recycling_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'recycling_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'recycling_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'recycling_4_score' => stats_rand_gen_normal($mean, $stddev),
                        'recycling_5_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_recycling_score' => stats_rand_gen_normal($mean, $stddev),

                        'input_reduction_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'input_reduction_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'input_reduction_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'input_reduction_4_score' => stats_rand_gen_normal($mean, $stddev),
                        'input_reduction_5_score' => stats_rand_gen_normal($mean, $stddev),
                        'input_reduction_6_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_input_reduction_score' => stats_rand_gen_normal($mean, $stddev),

                        'soil_health_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_social_values_diet_score' => stats_rand_gen_normal($mean, $stddev),

                        'animal_health_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'animal_health_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'animal_health_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_animal_health_score' => stats_rand_gen_normal($mean, $stddev),

                        'overall_biodiversity_score' => stats_rand_gen_normal($mean, $stddev),

                        'synergy_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'synergy_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'synergy_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'synergy_4_score' => stats_rand_gen_normal($mean, $stddev),
                        'synergy_5_score' => stats_rand_gen_normal($mean, $stddev),
                        'synergy_6_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_synergy_score' => stats_rand_gen_normal($mean, $stddev),

                        'economic_diversification_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_economic_diversification_score' => stats_rand_gen_normal($mean, $stddev),

                        'co_creation_knowledge_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'co_creation_knowledge_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'co_creation_knowledge_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'co_creation_knowledge_4_score' => stats_rand_gen_normal($mean, $stddev),
                        'co_creation_knowledge_5_score' => stats_rand_gen_normal($mean, $stddev),
                        'co_creation_knowledge_6_score' => stats_rand_gen_normal($mean, $stddev),
                        'co_creation_knowledge_7_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_co_creation_knowledge_score' => stats_rand_gen_normal($mean, $stddev),


                        'governance_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'governance_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'governance_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_governance_score' => stats_rand_gen_normal($mean, $stddev),

                        'social_values_diet_1_score' => stats_rand_gen_normal($mean, $stddev),
                        'social_values_diet_2_score' => stats_rand_gen_normal($mean, $stddev),
                        'social_values_diet_3_score' => stats_rand_gen_normal($mean, $stddev),
                        'social_values_diet_4_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_social_values_diet_score' => stats_rand_gen_normal($mean, $stddev),

                        'overall_fairness_score' => stats_rand_gen_normal($mean, $stddev),

                        'overall_connectivity_score' => stats_rand_gen_normal($mean, $stddev),

                        'participation_score' => stats_rand_gen_normal($mean, $stddev),
                        'overall_participation_score' => stats_rand_gen_normal($mean, $stddev),
                    ]);
            }
        }
    }
}

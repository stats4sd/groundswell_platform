<?php

return [
    'farm_registration_form_title' => env('OPTIONAL_MODULES_FARM_REGISTRATION_FORM_TITLE', 'farm registration'),
    'global_indicators_form_title' => env('OPTIONAL_MODULES_GLOBAL_INDICATORS_FORM_TITLE', 'global indicators'),
    'womans_form_form_title'       => env('OPTIONAL_MODULES_WOMANS_FORM_FORM_TITLE', "women's form"),

    'global_indicators_modules' => array_map('trim', explode(',', env(
        'OPTIONAL_MODULES_GLOBAL_INDICATORS_FORM_AVAILABLE_MODULES',
        'characterization of the agroecological transition,caet,agroforestry,bushmeat,wild foods,detailed cattle,changes in farm environment,pests and diseases,cultivated forages,expenditures,forest products,livestock feeding,membership of groups,natural resource management,seed varieties,slash and burn,uptake of interventions'
    ))),

    'womans_form_modules' => array_map('trim', explode(',', env(
        'OPTIONAL_MODULES_WOMANS_FORM_FORM_AVAILABLE_MODULES',
        'coping strategies,disability,food environments,innovation,gender attitudes,on farm labour by gender,relative vulnerability,value orientations,wash'
    ))),
];

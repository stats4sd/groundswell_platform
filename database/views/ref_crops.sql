SELECT
    1 as team_id,
    choice_list_entries.id as choice_list_entry_id,
    choice_list_entries.name as name,
    JSON_UNQUOTE(choice_list_entries.properties->"$.expected_yield") as expected_yield,
    JSON_UNQUOTE(choice_list_entries.properties->"$.recommended_fert_use") as recommended_fert_use,
    language_strings.text as label_en
FROM choice_list_entries

    LEFT JOIN language_strings on language_strings.linked_entry_id = choice_list_entries.id and language_strings.linked_entry_type = 'Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\ChoiceListEntry' and language_strings.locale_id = 1

WHERE choice_list_entries.choice_list_id = 261;

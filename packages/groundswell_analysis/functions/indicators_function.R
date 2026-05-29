library(tidyverse)
library(RM.weights)

# global = data1$global$full_data
# women = data1$women$full_data
# crops = data1$global$repeats$survey_grp_section_crop_productivity_crop_repeat
# livestock = data1$global$repeats$survey_grp_section_livestock_livestock_repeat
# off_farm = data1$global$repeats$survey_grp_offfarm_income_grp_section_off_farm_income_offfarm_income_repeat
# global_form = data1$global$full_form
# women_form = data1$women$full_form
# extra_vars <- indicator_groupings$variable_name
# language <- input$language
# translations <- report_translations

derive_indicators <- function(global, women, crops, off_farm, livestock = NULL,
                              global_form, women_form, translations, language,
                              extra_vars){
  
  # merge IVCC labels
  women_form$choices<-women_form[[paste0("choices_",language)]]
  women_form$choices<-ifelse(women_form$choices%in%c("NA", "NULL"), NA, women_form$choices)
  
  women_form$type<-ifelse(!is.na(women_form$choices) & !is.null(women_form$choices) & 
                            women_form$type=="string","select", women_form$type)
  
  selects<-filter(women_form,type=="select" & is.na(selectMultiple) & ruodk_name%in%colnames(women))
  
  xx <- colnames(women%>%
                   select(survey_grp_womens_section_vcc_module_voice_group_voice_hh_food,
                          survey_grp_womens_section_vcc_module_voice_group_voice_hh_spending,
                          survey_grp_womens_section_vcc_module_voice_group_voice_hh_crops,
                          survey_grp_womens_section_vcc_module_choice_group_choice_hh_income_women,
                          survey_grp_womens_section_vcc_module_choice_group_choice_comm_market,
                          survey_grp_womens_section_vcc_module_choice_group_choice_comm_committee))
  
  
  # merge IVCC labels
  for(i in xx){
    women <- merge_labels(
      variable = i,
      form = women_form,
      data = women)
  }
  
  #merge education level labels
  global_form$choices<-global_form[[paste0("choices_",language)]]
  global_form$choices<-ifelse(global_form$choices%in%c("NA", "NULL"), NA, global_form$choices)
  
  global_form$type<-ifelse(!is.na(global_form$choices) & !is.null(global_form$choices) & 
                             global_form$type=="string","select", global_form$type)
  
  selects<-filter(global_form,type=="select" & is.na(selectMultiple) & ruodk_name%in%colnames(global))
  
  #merge education level labels
  global <- merge_labels(
    variable = "survey_grp_section_household_info_household_head_details_education_level",
    data = global,form = global_form
  )
  
  #fix column names for global data
  cols_global <- data.frame(ruodk_name = colnames(global))
  cols_global_ruodk <- data.frame(ruodk_name = global_form$ruodk_name,
                                  short_name = global_form$name)
  cols_global <- cols_global%>%
    left_join(cols_global_ruodk)%>%
    mutate(short_name = ifelse(is.na(short_name), ruodk_name, short_name))
  
  colnames(global) <- cols_global$short_name
  
  #fix column names for womens data
  cols_women <- data.frame(ruodk_name = colnames(women))
  cols_women_ruodk <- data.frame(ruodk_name = women_form$ruodk_name,
                                 short_name = women_form$name)
  cols_women <- cols_women%>%
    left_join(cols_women_ruodk)%>%
    mutate(short_name = ifelse(is.na(short_name), ruodk_name, short_name))
  
  colnames(women) <- cols_women$short_name
  
  #merge crop labels to calcualte stape area
  cols_crops <- data.frame(ruodk_name = colnames(crops))
  crop_form <- global_form%>%filter(str_detect(ruodk_name, "crop_repeat"))%>%filter(name!="crop_repeat")%>%filter(type!="structure")
  
  colnames(crops) <- c(crop_form$name, "id", "submissions_id", "odata_context")
  
  crops <- merge_crops_label(crops, global_form, "english_(en)")
  
  #correct age and sex if necessary
  if("participant_age_update"%in%colnames(global)){
    global$participant_age <- global$participant_age_update
  }
  
  if("participant_sex_update"%in%colnames(global)){
    global$participant_sex <- global$participant_sex_update
  }
  
  extra_vars_global <- extra_vars[extra_vars%in%colnames(global)]
  
  extra_vars_women <- extra_vars[extra_vars%in%colnames(women) & !extra_vars%in%extra_vars_global]
  
  #set up indicators dataset using household id, grouping variables and form IDs
  indicators <-
    full_join(global%>%select(household_id, all_of(extra_vars_global), "global_form_id" = id),
              women%>%select(household_id, all_of(extra_vars_women), "womens_form_id" = id))%>%
    group_by(household_id)%>%
    mutate(n = n())%>%
    filter(n == 1)%>%
    ungroup()%>%
    select(-n)
  
  
  #PO.1 Productivity
  
  #Number of crops
  
  crops$crop_area_ha <- as.numeric(crops$crop_area_ha)
  crops$crop_yield_kg <- as.numeric(crops$crop_yield_kg)
  
  factors<-data.frame(filter(global_form,name=="crop_list")$`choices_english_(en)`) %>% distinct()
  crop_lists<-data.frame(global_form_id=global$id,str_split_fixed(global[["crop_list"]]," ",nrow(factors)))
  
  crop_count <- crop_lists%>%
    mutate_at(vars(-global_form_id),  function(x) ifelse(is.na(x) | x=="", 0, 1))%>%
    mutate(I_P_total_crops = rowSums(select(.,starts_with("X")),na.rm=TRUE))%>%
    left_join(global%>%select("global_form_id" = id, grow_crops))%>%
    mutate(I_P_total_crops = ifelse(grow_crops=="N",0, I_P_total_crops))
  
  crops_important <- crops%>%
    filter(!is.na(crop_id))%>%
    group_by("global_form_id" = submissions_id)%>%
    summarise(I_P_total_important_crops = n())
  
  crop_count <- crop_count%>%left_join(crops_important)%>%
    mutate(I_P_total_important_crops = ifelse(grow_crops=="N",0, I_P_total_important_crops))
  
  #%>%
  # mutate(I_P_total_important_crops = replace_na(I_P_total_important_crops,0))  
  
  indicators <- indicators%>%
    left_join(crop_count%>%select(global_form_id, I_P_total_crops, I_P_total_important_crops))
  
  # Staple/Other Crop Areas
  staple <- crops%>%
    ungroup()%>%
    filter(!is.na(crop_id))%>%
    mutate(staple = ifelse(label_lang%in%c("Millet", "Maize", "Sorghum", "Wheat"),"Staple", "Other"))%>%
    group_by(submissions_id)%>%
    mutate(area = sum(as.numeric(crop_area_ha),na.rm = TRUE))%>%
    group_by(submissions_id, staple)%>%
    summarise(staple_area = sum(as.numeric(crop_area_ha),na.rm=TRUE),
              total_area = mean(area))%>%
    mutate(perc_area = staple_area/total_area)%>%
    filter(staple == "Staple")
  
  indicators<-indicators%>%
    left_join(staple%>%
                select("global_form_id" = submissions_id, "I_P_staple_area" = perc_area))
  
  #Productivity
  
  crop_area_prod <- crops%>%
    filter(!is.na(crop_id))%>%
    group_by("global_form_id" = submissions_id)%>%
    summarise(I_P_total_crop_area = sum(crop_area_ha, na.rm = TRUE),
              I_P_total_crop_yield = sum(crop_yield_kg, na.rm = TRUE))%>%
    mutate(I_P_total_crop_productivity = I_P_total_crop_yield/I_P_total_crop_area)
  
  indicators<-indicators%>%
    left_join(crop_area_prod)
  
  # Shannon
  
  # shannon <- crops%>%
  #   ungroup()%>%
  #   filter(!is.na(crop_id) & !is.na(crop_yield_kg) & crop_yield_kg>0)%>%
  #   group_by(household_id)%>%
  #   mutate(n = n(),
  #          total_yield = sum(crop_yield_kg,na.rm = TRUE),
  #          perc = crop_yield_kg/total_yield)%>%
  #   mutate(log_perc = log(perc),
  #          perc_log = perc*log_perc)%>%
  #   summarise(sum_log = sum(perc_log))%>%
  #   mutate(I_P_shannon = sum_log*-1)
  # 
  # indicators<-indicators%>%
  #   left_join(shannon%>%select(household_id, I_P_shannon))
  
  # Simpson
  
  simpson <- crops%>%
    ungroup()%>%
    filter(!is.na(crop_id) & !is.na(crop_yield_kg) & crop_yield_kg>0)%>%
    group_by("global_form_id" = submissions_id)%>%
    mutate(n = n(),
           total_yield = sum(crop_yield_kg,na.rm = TRUE),
           denom = total_yield*(total_yield-1),
           num = crop_yield_kg*(crop_yield_kg-1))%>%
    summarise(num = sum(num),
              denom = mean(denom))%>%
    mutate(D = num/denom)%>%
    mutate(I_P_simpson = 1-D)
  
  indicators<-indicators%>%
    left_join(simpson%>%select(global_form_id, I_P_simpson))
  
  #Livestock
  
  if("livestock_owners" %in% colnames(global)){
    
    factors<-data.frame(filter(global_form,name=="livestock_all")$`choices_english_(en)`) %>% distinct()
    livestock_lists<-data.frame(global_form_id=global$id,str_split_fixed(global[["livestock_all"]]," ",nrow(factors)))
    
    livestock_count <- livestock_lists%>%
      mutate_at(vars(-global_form_id),  function(x) ifelse(is.na(x) | x=="", 0, 1))%>%
      mutate(I_P_total_livestock = rowSums(select(.,starts_with("X")),na.rm=TRUE))%>%
      left_join(global%>%select("global_form_id" = id, livestock_owners))%>%
      mutate(I_P_total_livestock = ifelse(livestock_owners=="N",0, I_P_total_livestock))
    
    livestocks_important <- livestock%>%
      filter(!is.na(livestock_id))%>%
      group_by("global_form_id" = submissions_id)%>%
      summarise(I_P_total_important_livestocks = n())
    
    livestock_count <- livestock_count%>%left_join(livestocks_important)%>%
      mutate(I_P_total_important_livestocks = ifelse(livestock_owners=="N",0, I_P_total_important_livestocks))
    
    indicators <- indicators%>%
      left_join(livestock_count%>%select(global_form_id,I_P_total_livestock, I_P_total_important_livestocks))
    
  }
  
  #PO.2 Women's Empowerment
  
  #Voice Choice Control
  vcc <- women%>%
    select("womens_form_id" = id,
           "I_VCC1" = voice_hh_food,"I_VCC1_bin" =voice_hh_food,
           "I_VCC2" = voice_hh_crops,"I_VCC2_bin" = voice_hh_crops,
           "I_VCC3" = voice_hh_spending,"I_VCC3_bin" = voice_hh_spending,
           "I_VCC4" = choice_hh_income_women,"I_VCC4_bin" = choice_hh_income_women,
           "I_VCC5" = choice_comm_market,"I_VCC5_bin" = choice_comm_market,
           "I_VCC6" = choice_comm_committee,"I_VCC6_bin" =choice_comm_committee)
  
  indicators <- indicators%>%
    left_join(vcc%>%select(womens_form_id, I_VCC1,I_VCC2,I_VCC3,I_VCC4,I_VCC5,I_VCC6))
  
  #AWEAI - Assets
  
  assets <- women%>%
    select("womens_form_id" = id, ends_with("_own"))%>%
    mutate(
      total_assets = rowSums(!is.na(select(.,ends_with("own")))),
      total_owned_assets = rowSums(select(.,ends_with("own"))<3,na.rm = TRUE),
      total_minor_assets = as.numeric(!is.na(poultry_own)) + as.numeric(!is.na(non_mech_eqp_own)) + as.numeric(!is.na(small_consumer_own)),
      total_major_assets = total_owned_assets - total_minor_assets
    )%>%
    mutate(I_AWEAI_asset = ifelse(total_major_assets>=1 | total_minor_assets >=2,1,0),
           I_AWEAI_asset_prop = (total_major_assets+total_minor_assets)/total_assets)
  
  indicators <- indicators%>%
    left_join(assets%>%select(womens_form_id, I_AWEAI_asset,I_AWEAI_asset_prop))
  
  #PO.3 Household Income
  
  #Markets
  #Presented at crop level
  
  # Income and diversification of livelihoods
  # Check off-farm income loop
  
  #add OPTION for LIVESTOCK AND CROP PRODUCTS
  
  global$crop_only <- ifelse(global$grow_crops=="Y" & !global$id%in%off_farm$submissions_id,1,0)
  global$crop_plus_offarm <- ifelse(global$grow_crops=="Y" & global$id%in%off_farm$submissions_id,1,0)
  global$income_unreported <- ifelse(global$grow_crops=="N" & !global$id%in%off_farm$submissions_id,1,0)
  global$off_farm_only <- ifelse(global$grow_crops=="N" & global$id%in%off_farm$submissions_id,1,0)
  
  offarm_ns <- off_farm%>%group_by(submissions_id)%>%summarise(n_sources = n())
  
  global <- left_join(global,offarm_ns, by = c("id" = "submissions_id"))
  
  global<-global%>%
    mutate(n_sources = case_when(
      crop_only == 1 ~ 1,
      crop_plus_offarm == 1 ~ n_sources+1,
      off_farm_only == 1 ~ n_sources,
      .default = 0
    ))
  
  global$I_HI_income_diversification <- case_when(
    global$crop_only == 1 ~ "Crop farming only",
    global$crop_plus_offarm == 1 ~ "Crop farming + Off-farm sources",
    global$off_farm_only == 1 ~ "Off-farm sources only",
    .default = "Income sources unreported"
  )
  
  indicators <- indicators%>%
    left_join(
      global%>%
        select(household_id,
               "global_form_id" = id,
               I_HI_n_income_sources = n_sources,
               I_HI_income_diversification)
    )
  
  # Ability to save and pay debts
  finance <- global%>%
    select(household_id,
           "global_form_id" = id,
           finance_1:finance_7,
           debts_have,
           debts_worry)%>%
    mutate(I_HI_self_perception = case_when(
      finance_1=="Y" ~ 7,
      finance_2=="Y" ~ 6,
      finance_3=="Y" ~ 5,
      finance_4=="Y" ~ 4,
      finance_5=="Y" ~ 3,
      finance_6=="Y" ~ 2,
      finance_7=="Y" ~ 1
    ))%>%
    mutate(I_HI_savings = ifelse(finance_1=="Y",1,0))%>%
    mutate(I_HI_have_debt = ifelse(debts_have=="Y",1,0))%>%
    mutate(I_HI_worry_debt = ifelse(debts_worry=="Y",1,0),"")
  
  perception_labels <- translations%>%
    filter(indicator=="I_HI_self_perception")
  
  finance <-  finance%>%
    mutate(I_HI_self_perception = factor(
      I_HI_self_perception,
      levels = c(7,6,5,4,3,2,1),
      labels = perception_labels[1:7,language]
    ))
  
  indicators <- indicators%>%
    left_join(finance%>%select(household_id,global_form_id, starts_with("I_")))
  
  #transportation
  
  women$transportation_hh <- ifelse(women$transportation_hh=="Y",1,0)
  
  indicators <- indicators%>%
    left_join(women%>%select(household_id,"womens_form_id" = id,"I_HI_transport" = transportation_hh))
  
  #PO.4 Household Dietary Diversity and Food Security
  
  #Dietary Diversity
  hdds<-women%>%
    select(household_id,"womens_form_id" = id, Grains_24hr,Roots_24hr,Pulses_24hr,Fruit_24hr,Vegetables_24hr,
           Meat_24hr,Eggs_24hr,Fish_24hr,Milk_Dairy_24hr,oil_fat_24hr,
           sweet_packaged_24hr, condiments_24hr)%>%
    mutate(missing = rowSums(is.na(select(.,Grains_24hr:condiments_24hr))))%>%
    mutate(I_HDDS = rowSums(select(.,Grains_24hr:condiments_24hr)=="Y",na.rm=TRUE))%>%
    mutate(I_HDDS_min = ifelse(I_HDDS>=5,1,0))
  
  indicators <- indicators%>%
    left_join(hdds%>%select(household_id,womens_form_id, starts_with("I_")))
  
  #FIES
  fies <- women%>%
    select(household_id,"womens_form_id" = id, starts_with("FIES"))%>%
    mutate_at(vars(starts_with("FIES")), function(x) case_when(
      x=="Y" ~ 1,
      x=="N" ~ 0,
      .default = NA
    ))
  
  fies_results <- RM.w(.data = as.matrix(fies%>%select(starts_with("FIES"))),
                       country = "Honduras_Vecinos")
  
  severity_parameters <- fies_results$b
  
  raw_score_parameters <- data.frame(
    score = 0:8,
    severity = fies_results$a,
    error = fies_results$se.a,
    w_cases = fies_results$wt.rs
  )
  
  item_parameters <- data.frame(
    item = colnames(fies)[2:9],
    before = c(-1.2230564,-0.847121,-1.1056616, 0.3509848,-0.3117999,0.5065051,0.7546138,1.8755353)
  )
  
  mean_country <- mean(severity_parameters)
  mean_standard <- mean(item_parameters$before)
  std.dev_country <- sd(severity_parameters)
  std.dev_standard <- sd(item_parameters$before)
  
  item_parameters$after <- (item_parameters$before - mean_standard)/std.dev_standard*std.dev_country+mean_country
  
  scores <- data.frame(
    score = 0:8
  )
  
  scores$percentage <- raw_score_parameters$w_cases/sum(raw_score_parameters$w_cases)
  mod_severe <- item_parameters$after[5]
  severe <- item_parameters$after[8]
  
  scores$probability_mod_sev <-ifelse(scores$score==0,0, 1-pnorm(mod_severe,raw_score_parameters$severity,raw_score_parameters$error))
  scores$probability_sev <-ifelse(scores$score==0,0, 1-pnorm(severe,raw_score_parameters$severity,raw_score_parameters$error))
  
  perc_mod_severe <- sum(scores$percentage*scores$probability_mod_sev)
  perc_severe <- sum(scores$percentage*scores$probability_sev)
  
  fies <- fies%>%
    mutate(I_FIES_score = rowSums(select(.,starts_with("FIES")),na.rm=TRUE))%>%
    left_join(scores%>%
                select(score, "I_FIES_prob_moderate" = probability_mod_sev, "I_FIES_prob_severe" = probability_sev),
              by = c("I_FIES_score" = "score"))
  
  indicators <- indicators%>%left_join(fies)
  
  indicators$I_FIES_prev_moderate <- perc_mod_severe
  indicators$I_FIES_prev_severe <- perc_severe
  
  indicators$I_FIES_category <- case_when(
    indicators$I_FIES_score == 0 ~ "Food Secure",
    (coalesce(indicators$FIES_1,0) + coalesce(indicators$FIES_2,0) + coalesce(indicators$FIES_3,0) + coalesce(indicators$FIES_4,0)) > 0 &
      (coalesce(indicators$FIES_5,0) + coalesce(indicators$FIES_6,0) + coalesce(indicators$FIES_7,0) + coalesce(indicators$FIES_8,0)) == 0 ~ "Mildly Food Insecure",
    (coalesce(indicators$FIES_5,0) + coalesce(indicators$FIES_6,0) + coalesce(indicators$FIES_7,0)) > 0 & coalesce(indicators$FIES_8,0) == 0 ~ "Moderately Food Insecure",
    coalesce(indicators$FIES_8,0) == 1 ~ "Severely Food Insecure"
  )
  
  fies_cat_labs <- translations%>%
    filter(indicator == "I_FIES_category" & type=="label")
  
  
  indicators$I_FIES_category <- factor(
    indicators$I_FIES_category,
    levels = c("Food Secure", "Mildly Food Insecure", "Moderately Food Insecure", "Severely Food Insecure"),
    labels = fies_cat_labs[,language]
  )
  
  return(indicators)
  
}
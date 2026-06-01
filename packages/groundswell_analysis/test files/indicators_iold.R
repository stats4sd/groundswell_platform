derive_indicators<-function(data1=data1){
  

indicators<-data1$data %>% select(id)

# Crops module

crops<-data1$repeats$survey_grp_section_crop_productivity_crop_repeat



crops<-crops %>%
  select(crop=crop_label,
         yield=crop_rep_grp_crop_details_crop_area_ha,
         area=crop_rep_grp_crop_details_crop_yield_kg,
         productivity=crop_rep_grp_crop_details_crop_yield_kg_ha) %>%
  filter(crop!="Other") %>%
  mutate(yield=as.numeric(yield),
         area  =as.numeric(area ),
         productivity=as.numeric(productivity))



## List of indicators to derive:



# Asset index
var=data1$data$survey_grp_financial_situation_module_hh_items
indicators$I_Assets<-ifelse(is.na(var),NA,
                                 ifelse(var=="",0,str_count(var," ")+1)
)
rm(var)

# Financial Situation

#financial<-select(data1$data,contains("finance_"))  
#indicators$I_financial<-ifelse(is.na(financial$survey_grp_financial_situation_module_y_n_finance_grp_finance_1),NA,
#                    rowSums(financial=="No",na.rm=T))


# Household Control#
#hhc<-select(data1$raw_data,id,contains("control_hh"))  %>%
#  gather(variable,value,-id) %>%
#  mutate(score=case_when(value=="none"~0,
#                         value=="little"~1,
#                         value=="moderate"~2,
#                         value=="equal"~3,
#                         value=="more_than"~4,.default=NA)) %>%
#  group_by(id) %>%
#  summarise(I_HouseholdControl=mean(score,na.rm=T))




# Community Control

#ccc<-select(data1$raw_data,id,contains("control_comm"))  %>%
#  gather(variable,value,-id) %>%
#  mutate(score=case_when(value=="none"~0,
#                         value=="little"~1,
#                         value=="moderate"~2,
#                         value=="equal"~3,
#                         value=="more_than"~4,.default=NA)) %>%
#  group_by(id) %>%
#  summarise(I_CommunityControl=mean(score,na.rm=T))

#indicators<-full_join(indicators,ccc)

# FIES
#FIES<-select(data1$data,contains("FIES"))  
#
#indicators$I_FIES<-ifelse(rowSums(is.na(FIES))>5,NA,
#                          rowSums(FIES=="Yes",na.rm=T))

# HDDS
hdds<-select(data1$data,contains("_24"),-contains("extra"))  
indicators$I_HDDS<-ifelse(rowSums(is.na(hdds))>5,NA,
  rowSums(hdds=="Yes",na.rm=T))

# HFIAS

# Sales of crops


# Food shortage months
var<-ifelse( data1$data$survey_grp_section_food_security_foodsecuritystatus_foodshortagetime=="No","",
  data1$data$survey_grp_section_food_security_foodshortage_group_foodshortagetime_months_which)
indicators$I_FoodShortageMths<-ifelse(is.na(var),NA,
                                 ifelse(var=="",0,str_count(var," ")+1)
                                 
                                 )
rm(var)

data1$modules<-list(crops=crops)
data1$indicators<-indicators
return(data1)
}
#saveRDS(data1,"indicators.RDS")




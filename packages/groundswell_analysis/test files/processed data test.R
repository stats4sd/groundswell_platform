input<-NULL
input$country <- "Senegal"
input$language <- "english_(e)"

data_directory <- read.xlsx("inputs/project list.xlsx")

data_processed <-NULL
source("functions/functions.R")
source("test files/data download test.R")

data_processed$global <- read.csv(data_directory$global[data_directory$project==input$country])%>%
  mutate_all(function(x) ifelse(x=='', NA, x))
data_processed$global <- merge_labels_clean(data_processed$global, data1$global$full_form, input$language)

#Women data
data_processed$women <- read.csv(data_directory$womens[data_directory$project==input$country])

wom_assets <- data_processed$women%>%select(household_id,ends_with("own"))
data_processed$women <- merge_labels_clean(data_processed$women, data1$women$full_form, input$language)
data_processed$women <- data_processed$women%>%select(-ends_with("own"))%>%
  left_join(wom_assets)
#
data_processed$indicators <- read.csv(data_directory$indicators[data_directory$project==input$country])
data_processed$indicators$partner<-names(proj_list)[proj_list==input$country]

#Crops Data
data_processed$crops <- read.csv(data_directory$crops[data_directory$project==input$country])

data_processed$crops$crop_consumed_prop[(is.na(data_processed$crops$crop_consumed_prop) | data_processed$crops$crop_consumed_prop == "")
                                        & data_processed$crops$crop_use=="eat"] <- "All"
data_processed$crops$crop_consumed_prop[(is.na(data_processed$crops$crop_consumed_prop) | 
                                           data_processed$crops$crop_consumed_prop == "")] <- "None"

data_processed$crops$crop_sold_prop[(is.na(data_processed$crops$crop_sold_prop) | data_processed$crops$crop_sold_prop == "")
                                    & data_processed$crops$crop_use=="sell"] <- "All"
data_processed$crops$crop_sold_prop[(is.na(data_processed$crops$crop_sold_prop) | data_processed$crops$crop_sold_prop == "")] <- "None"

data_processed$crops <- merge_labels_clean(data_processed$crops, data1$global$full_form, input$language)
data_processed$crops <- merge_crops_label(data_processed$crops, data1$global$full_form, input$language)

#Off farm
data_processed$off_farm <- read.csv(data_directory$off_farm[data_directory$project==input$country])

if(!is.na(data_directory$livestock[data_directory$project==input$country])){
  data_processed$livestock <- read.csv(data_directory$livestock[data_directory$project==input$country])
  
  data_processed$livestock <- merge_labels_clean(data_processed$livestock, data1$global$full_form, input$language)
  data_processed$livestock <- merge_livestock_label(data_processed$livestock, data1$global$full_form, input$language)
  
}

data_processed$hh_roster <- read.csv(data_directory$hh_roster[data_directory$project==input$country])

#translate perception
perception_labels <- report_translations%>%
  filter(indicator=="I_HI_self_perception")

data_processed$indicators <-  data_processed$indicators%>%
  mutate(I_HI_self_perception = factor(
    I_HI_self_perception,
    levels = c(7,6,5,4,3,2,1),
    labels = perception_labels[1:7,input$language]
  ))

#translate FIES category
fies_cat_labs <- report_translations%>%
  filter(indicator == "I_FIES_category" & type=="label")

data_processed$indicators$I_FIES_category <- factor(
  data_processed$indicators$I_FIES_category,
  levels = c("Food Secure", "Mildly Food Insecure", "Moderately Food Insecure", "Severely Food Insecure"),
  labels = fies_cat_labs[,input$language]
)

#translate IVCC
xx <- colnames(data_processed$indicators%>%select(starts_with("I_VCC")))
yy <- c("voice_hh_food", "voice_hh_crops", "voice_hh_spending",
        "choice_hh_income_women", "choice_comm_market", "choice_comm_committee")

for(i in 1:6){
  data_processed$indicators <- merge_labels_clean_indicator(
    variable = yy[i],
    indicator = xx[i],
    form = data1$women$full_form,
    data = data_processed$indicators,
    language = input$language)
}
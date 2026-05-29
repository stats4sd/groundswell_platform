input <- NULL
input$country <- "Honduras1"

ruODK::ru_setup(
  url = Sys.getenv("server_url"),
  pid = Sys.getenv(paste(input$country,"project_id",sep="_")),
  un = Sys.getenv("server_username"),
  pw = Sys.getenv("server_password")
)

fid <- Sys.getenv("form_id")

a <- form_schema_ext(fid=Sys.getenv("form_id"))

honduras1 <- read.csv("clean_data/Honduras1/Vecinos indicator form.csv")

translations <- read.xlsx("report_translations.xlsx")

#variable <- "education_level"

merge_labels_clean<-function(data, form, language){
  
  form$choices<-form[[paste0("choices_", language)]]
  form$choices<-ifelse(form$choices%in%c("NA", "NULL"), NA, form$choices)
  
  form$type<-ifelse(!is.na(form$choices) & !is.null(form$choices) & form$type=="string","select", form$type)
  
  selects<-filter(form,type=="select" & is.na(selectMultiple) & name%in%colnames(data))
  
  for(i in 1:nrow(selects)){
    
    variable <- selects$name[i]
  
    factors<-data.frame(filter(form,name==variable)$choices)
  
  
  if(nrow(factors)>0){
    out_of_list<-na.omit(data[[variable]][!data[[variable]] %in% factors$values])
    
    data[[variable]]=factor( data[[variable]],
                             levels=c(out_of_list,factors$values),
                             labels=c(out_of_list,factors$labels))
  }
    
  }
  return(data)
}

crops <- read.csv("clean_data/Senegal/crops.csv")

data <- crops
form <- a
language <- "english_(en)"

merge_crops_label <- function(data, form, language){
  
  form$choices<-form[[paste0("choices_", language)]]
  form$choices<-ifelse(form$choices%in%c("NA", "NULL"), NA, form$choices)
  
  form$choices[form$name=="crop_label"] <- form$choices[form$name=="crops_important"]
  
  factors<-data.frame(filter(form,name=="crop_label")$choices)
  
  out_of_list<-na.omit(data[["crop_id"]][!data[["crop_id"]] %in% factors$values])
  
  data[["label_lang"]]=factor( data[["crop_id"]],
                           levels=c(out_of_list,factors$values),
                           labels=c(out_of_list,factors$labels))
  
  return(data)
  
}

crops <- merge_crops_label(crops, a, language)

crops <- crops%>%filter(!is.na(label_lang) & label_lang!="")

market_labels <- translations%>%filter(indicator=="Markets")

sales <- crops%>%
  select(label_lang,crop_use, crop_consumed_prop,crop_sold_prop)%>%
  mutate(
    crop_consumed_prop = ifelse(crop_use=="eat", "All", crop_consumed_prop),
    crop_sold_prop = ifelse(crop_use=="sell", "All", crop_sold_prop)
  )%>%
  mutate_at(vars(crop_use:crop_sold_prop), function(x) ifelse(x == "","None", x))%>%
  filter(crop_use!="None")%>%
  group_by(label_lang)%>%
  mutate(n = n())%>%
  filter(n>5)

language_trans <- "english_(en)"

tbl1 <- sales%>%
  pivot_longer(cols = c(crop_consumed_prop, crop_sold_prop))%>%
  mutate(name = ifelse(name == "crop_sold_prop", market_labels[8,language_trans], market_labels[7,language_trans]))%>%
  filter(name == market_labels[7,language_trans])%>%
  mutate(label_lang = as.character(label_lang))%>%
  tbl_summary(
    include = value,
    by = label_lang,
    label = list(value = market_labels[7,language_trans])
  )

tbl2 <- sales%>%
  pivot_longer(cols = c(crop_consumed_prop, crop_sold_prop))%>%
  mutate(name = ifelse(name == "crop_sold_prop", market_labels[8,language_trans], market_labels[7,language_trans]))%>%
  filter(name == market_labels[8,language_trans])%>%
  tbl_summary(
    include = value,
    by = label_lang,
    label = list(value = market_labels[8,language_trans])
  )


honduras1_labelled <- merge_labels_clean(honduras1, a)

womens <- read.csv("clean_data/Honduras1/Vecinos womens form.csv")
womens_form <- form_schema_ext(fid=Sys.getenv("women_form_id"))

wom_assets <- womens%>%select(household_id,ends_with("own"))

womens <- merge_labels_clean(womens, womens_form, "english_(en)")

womens <- womens%>%select(-ends_with("own"))%>%
  left_join(wom_assets)

asset_labels <- translations%>%filter(indicator=="I_AWEAI_asset")

language_trans <- "english"

assets <- womens%>%
  select(household_id,ends_with("own"))%>%
  mutate_at(vars(ends_with("own")), function(x)
    case_when(
      x %in% c(1,2) ~ asset_labels[asset_labels$english=="Joint/Sole ownership", language_trans],
      x %in% c(3) ~ asset_labels[asset_labels$english=="Owned by other household member", language_trans],
      .default = asset_labels[asset_labels$english=="Unowned", language_trans]
    ))%>%
  pivot_longer(cols=-household_id)%>%
  group_by(name) %>%
  mutate(value = factor(value,
                        levels = asset_labels[asset_labels$type=="fill_label", language_trans],
                        labels = asset_labels[asset_labels$type=="fill_label", language_trans]))%>%
  mutate(name = case_when(
    name == "transportation_own" ~ asset_labels[4,language_trans],
    name == "small_livestock_own" ~ asset_labels[5,language_trans],
    name == "small_consumer_own" ~ asset_labels[6,language_trans],
    name == "poultry_own" ~ asset_labels[7,language_trans],
    name == "other_land_own" ~ asset_labels[8,language_trans],
    name == "nonfarm_eqp_own" ~ asset_labels[9,language_trans],
    name == "non_mech_eqp_own" ~ asset_labels[10,language_trans],
    name == "mech_eqp_own" ~ asset_labels[11,language_trans],
    name == "large_consumer_own" ~ asset_labels[12,language_trans],
    name == "land_own" ~ asset_labels[13,language_trans],
    name == "house_own" ~ asset_labels[14,language_trans],
    name == "fish_pond_own" ~ asset_labels[15,language_trans],
    name == "cell_phone_own" ~ asset_labels[16,language_trans],
    name == "large_livestockown" ~ asset_labels[17,language_trans],
    .default = name
  ))%>%
  mutate(name = str_wrap(name,45))

assets%>%
  ggplot(aes(y = reorder(name,desc(name)), fill = reorder(value,desc(value))))+
  geom_bar(colour = "black", position = "fill")+
  labs(x = NULL,
       title = asset_labels[asset_labels$english=="Women's Household Asset Ownership", language_trans],
       y = NULL,
       fill = asset_labels[asset_labels$english=="Ownership status", language_trans])+
  theme_bw()+
  theme(legend.position = "bottom",
        legend.margin = margin(l = -.35, unit = "npc"))+
  scale_fill_manual(values = c("grey80", "#61958F", "#F39F5D"))+
  scale_x_continuous(labels = scales::percent)

assets%>%
  ungroup()%>%
  group_by(name, value)%>%
  mutate(N = nrow(womens),
         n = n())%>%
  select(-household_id)%>%
  mutate(perc = n/N)%>%
  unique()%>%
  select(-N, -n)%>%
  pivot_wider(names_from = value, values_from = perc)%>%
  mutate_all(function(x) replace_na(x,0))%>%
  gt(groupname_col = NULL)%>%
  fmt_percent(columns = 2:4, decimals = 1)%>%
  cols_label(name = "Asset")

Honduras2 <- read.csv("clean_data/Honduras2/ACESH Indicator form.csv")
indicators <- read.csv("clean_data/Honduras2/ACESH Indicators.csv")
indicator_tab <- read.xlsx("indicator_tab.xlsx")

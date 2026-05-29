source("functions/functions.R")
source("functions/indicators_function.R")
  
report_translations <- read.xlsx("inputs/report_translations.xlsx")%>%
  mutate_all(function(x) trimws(x))

indicator_tab <- read.xlsx("inputs/indicator_tab.xlsx")

data1 <- NULL
input <- NULL
input$country <- "Honduras1"
input$language <- "english_(en)"
  
  ruODK::ru_setup(
    url = Sys.getenv("server_url"),
    pid = Sys.getenv(paste(input$country,"project_id",sep="_")),
    un = Sys.getenv("server_username"),
    pw = Sys.getenv("server_password")
  )
  
  fid=Sys.getenv("form_id")
  full_form=form_schema_ext(fid=Sys.getenv("form_id"))
  
  indicator_groupings <- read.xlsx("inputs/indicator_groupings.xlsx")%>%
    filter(country==input$country)%>%
    filter(variable_name!="partner")
  
  extra_vars <- indicator_groupings$variable_name

  ###########################
data1$global<-read_clean_data(fid=Sys.getenv("form_id"),language=input$language,
                                 full_form=form_schema_ext(fid=Sys.getenv("form_id")))

data1$women<- read_clean_data(fid=Sys.getenv("women_form_id"),language=input$language,
                              full_form=form_schema_ext(fid=Sys.getenv("women_form_id")))

data1$reg<- read_clean_data(fid=Sys.getenv("reg_form_id"),language=input$language,
                            full_form=form_schema_ext(fid=Sys.getenv("reg_form_id")))

data1$indicators <- derive_indicators(global = data1$global$full_data,
                                       women = data1$women$full_data,
                                       crops = data1$global$repeats$survey_grp_section_crop_productivity_crop_repeat,
                                       off_farm = data1$global$repeats$survey_grp_offfarm_income_grp_section_off_farm_income_offfarm_income_repeat,
                                      livestock = data1$global$repeats$survey_grp_section_livestock_livestock_repeat,
                                       global_form = data1$global$full_form,
                                       women_form = data1$women$full_form,
                                       translations = report_translations,
                                       language = input$language,
                                      extra_vars = extra_vars)

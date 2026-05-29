generate_svc<-function(server="https://odk.stats4sd.org",projID,xmlFormID){
    svc=paste0(server,"/v1/projects/",projID,"/forms/",xmlFormID,".svc",sep="")
}

language_file1<-read.xlsx("inputs/groundswell_monitor_text_fortranslation_translated.xlsx")

#indicators<-read.xlsx("indicators_modules.xlsx","indicators")
modules<-read.xlsx("inputs/indicators_modules.xlsx","modules")

indicator_directory <- read.xlsx("inputs/indicator_directory.xlsx")%>%
  filter(available==1)

available_indicators <- indicator_directory$indicator_name
names(available_indicators) <- indicator_directory$`english_(en)`

report_translations <- read.xlsx("inputs/report_translations.xlsx")%>%
  mutate_all(function(x) trimws(x))

indicator_tab <- read.xlsx("inputs/indicator_tab.xlsx")
indicator_groupings <- read.xlsx("inputs/indicator_groupings.xlsx")

#Read in directory of "processed" data - currently the manually processed data 
data_directory <- read.xlsx("inputs/project list.xlsx")
proj_list<-data_directory$project %>% 
  set_names(data_directory$Partner)  

language_switch<-function(element_name,language,language_file=language_file1){
  filter(language_file,element==element_name)[,language]
}
#language_switch("tabName_Data","english_(en)")

#check if a column can be numeric
can.be.numeric <- function(x) {
  stopifnot(is.atomic(x) || is.list(x)) # check if x is a vector
  numNAs <- sum(is.na(x))
  numNAs_new <- suppressWarnings(sum(is.na(as.numeric(x))))
  return(numNAs_new == numNAs)
}

#change any negative numbers to NA
na_99 <- function(data) {
  data <- data %>%
    mutate_if(is.numeric, function(x)
      ifelse(x < 0, NA, x))
  
  return(data)
  
}

#Convert back to numbers as variables are otherwise presented as characters
number_fix <- function(data) {
  #convert "NA" or "NaN" to NA proper
  # data <- data %>%
  #   mutate_all(function(x)
  #     ifelse(x == "NA", NA, x)) %>%
  #   mutate_all(function(x)
  #     ifelse(x == "NaN", NA, x))
  
  data <- as.data.frame(lapply(data, function(col) {
    if (can.be.numeric(col) & !is.POSIXct(col) & !is.POSIXlt(col) & !is.POSIXt(col) &!is.factor(col)) {

      as.numeric(col)
    } else {
      col
    }
  }))
  
  #data <- na_99(data)
  
  return(data)
  
}


final_string<-function(full,name){
  pos<-str_locate_all(full,paste0(tolower(name),"|_",tolower(name))) 
  substr(full,1,lapply(pos,function(x)max(x[,1])-1) %>% unlist())
}


groundswell_palette<-c("#F39F5D", "#E0EABE", 
                       "#8F4F30", "#61958F", "#FFFFFF","#7BBC49", "#C25D5C")
  

merge_labels<-function(variable,data,form,repeatgrp=FALSE){
  
  
  if(repeatgrp==FALSE){
  factors<-data.frame(filter(form,ruodk_name==variable)$choices)
  }
  else{
    factors<-data.frame(filter(form,repeat_name==variable)$choices)
  }

  
  if(nrow(factors)>0){
    out_of_list<-na.omit(data[[variable]][!data[[variable]] %in% factors$values])
    
    data[[variable]]=factor( data[[variable]],
                             levels=c(out_of_list,factors$values),
                             labels=c(out_of_list,factors$labels))
  }
  return(data)
}

# form <- data1$women$full_form
# data <- data_processed$indicators
# variable <- "voice_hh_food"
# indicator <- "I_VCC1"

merge_labels_clean_indicator <- function(variable,indicator, data, form, language){
  
  form$choices<-form[[paste0("choices_",language)]]
  form$choices<-ifelse(form$choices%in%c("NA", "NULL"), NA, form$choices)
  
  form$type<-ifelse(!is.na(form$choices) & !is.null(form$choices) & form$type=="string","select", form$type)
  
  selects<-filter(form,type=="select" & is.na(selectMultiple))
  
  factors<-data.frame(filter(form,name==variable)$choices)
  
  if(nrow(factors)>0){
    out_of_list<-na.omit(data[[indicator]][!data[[indicator]] %in% factors$values])
    
    data[[indicator]]=factor( data[[indicator]],
                             levels=c(out_of_list,factors$values),
                             labels=c(out_of_list,factors$labels))
  }
  
  return(data)
  
}

#ALTERNATIVE LABEL MERGING FOR CLEANDED DATA
# form <- data1$global$full_form
# language <- "english_(en)"
merge_labels_clean<-function(data, form, language){
  
  form$choices<-form[[paste0("choices_",language)]]
  form$choices<-ifelse(form$choices%in%c("NA", "NULL"), NA, form$choices)
  
  form$type<-ifelse(!is.na(form$choices) & !is.null(form$choices) & form$type=="string","select", form$type)
  
  selects<-filter(form,type=="select" & is.na(selectMultiple) & name%in%colnames(data))
  
  for(i in 1:nrow(selects)){
    
    variable <- selects$name[i]
    #print(variable)
    
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

#crop labelling function
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

#form <- global_form

merge_livestock_label <- function(data, form, language){
  
  form$choices<-form[[paste0("choices_", language)]]
  form$choices<-ifelse(form$choices%in%c("NA", "NULL"), NA, form$choices)
  
  form$choices[form$name=="livestock_label"] <- form$choices[form$name=="livestock"]
  
  factors<-data.frame(filter(form,name=="livestock_label")$choices)
  
  out_of_list<-na.omit(data[["livestock_id"]][!data[["livestock_id"]] %in% factors$values])
  
  data[["label_lang"]]=factor( data[["livestock_id"]],
                               levels=c(out_of_list,factors$values),
                               labels=c(out_of_list,factors$labels))
  
  return(data)
  
}




odk_date_table<-function(data,variable,group=NA){
  data$variable<-data[[variable]]
  data %>%
    summarise("Earliest"=min(variable,na.rm=T),
              "Median"=median(variable,na.rm=T),
              "Most Recent"=max(variable,na.rm=T))
  
}

odk_string_table<-function(data,variable,group=NA){
  
  data$response<-data[[variable]]
  
  data %>%
    filter(!is.na(response)) %>%
    dplyr::select(id,response) %>%
    return()
  
}

odk_number_table<-function(data,variable,group=NA){
  data$variable<-as.numeric(as.character(data[[variable]]))
  data %>%
    summarise("Responses"=length(variable[!is.na(variable)]),
              "NAs"=length(variable[is.na(variable)]),
              "Min"=min(variable,na.rm=T),
              "Mean"=median(variable,na.rm=T),
              "Median"=mean(variable,na.rm=T),
              "Standard Deviation"=sd(variable,na.rm=T),
              "Max"=max(variable,na.rm=T))
}

odk_select1_table<-function(data,variable,form,group=NA,style=TRUE){
  data$response<-data[[variable]]
  factors<-data.frame(filter(form,ruodk_name==variable)$choices)
  
  empty_set<-filter(factors,!labels%in%data$response)%>%
    mutate("Responses"=0,
           "Percent of Non-Missing"=0,
           "Percent of All"=0) %>%
    select(-values)
  
  table1<-data %>%
    group_by(response) %>%
    summarise("Responses"=n())  %>%
    mutate("Percent of Non-Missing"=ifelse(is.na(response),NA,
             (Responses/sum(Responses[!is.na(response)])))
           ) %>%
    mutate("Percent of All"=(Responses/sum(Responses))) 
  
  if(style==TRUE){
    empty_set<-filter(factors,!labels%in%data$response)%>%
      mutate("Responses"=0,
             "Percent of Non-Missing"="0%",
             "Percent of All"="0%") %>%
      select(-values)
    
    table1<- table1%>%
      mutate("Percent of Non-Missing"=scales::percent(`Percent of Non-Missing`)) %>%
      mutate("Percent of All"=scales::percent(`Percent of All`)) 
  }
  
  if(nrow(empty_set)>0){
    table1<- table1%>%
      full_join(empty_set,by=c("response"="labels","Responses","Percent of Non-Missing",
                               "Percent of All")) %>%
      mutate(response=factor(as.character(response),
                             levels=factors$labels
      )) %>%
      arrange(response)
  }
  table1
}

odk_selectn_table<-function(data,variable,form,group=NA,style=TRUE){
  
  factors<-data.frame(filter(form,ruodk_name==variable)$choices) %>% distinct()
  
  variables<-data.frame(ID=data$id,str_split_fixed(data[[variable]]," ",nrow(factors)))
  #print(variables)
  #print(factors)
  
  variables<-  variables %>% 
    gather(selection,value,-ID) %>%
    filter(value!="" & !is.na(value)) %>%
    inner_join(factors,by=c("value"="values")) %>%
    mutate("Total Respondents"=length(unique(ID))) %>%
    group_by(labels,`Total Respondents`) %>%
    summarise("Responses"=n()) %>%
    ungroup() %>%
    mutate("% of Responses"=(Responses/sum(Responses)),
           "% of Question Respondents"=(Responses/`Total Respondents`),
           "% of All Respondents"=(Responses/nrow(data)))
  
  if(style==TRUE){
    variables<-  variables %>%
      mutate("% of Responses"=scales::percent(`% of Responses`),
             "% of Question Respondents"=scales::percent(`% of Question Respondents`),
             "% of All Respondents"=scales::percent(`% of All Respondents`))
  }
  variables
  
}
available_types<-c("string","decimal","int","dateTime","date","select")



odk_table<-function(data,variable,form,group=NA,repeats=NULL,style=TRUE){
  
  variable_type<-filter(form,ruodk_name==variable)$type
  
  repeat_type<-filter(form,ruodk_name==variable)$repeats
  
  if(repeat_type==TRUE){
    data<-repeats[[filter(form,ruodk_name==variable)$repeat_data]] 
    data_variable<-filter(form,ruodk_name==variable)$repeat_name
    colnames(data)[colnames(data)==data_variable]<-variable

  }
  else{
    data_variable<-variable
  }
 # table(data[[variable]]) %>% print()
 # variable_type %>% print()
  
  if(variable_type %in% available_types){
  if(variable_type=="string"){
    tab<-odk_string_table(data,variable) 
  }
  
  if(variable_type%in%c("decimal","int")){
    tab<- odk_number_table(data=data,variable) 
  }
  if(variable_type%in%c("dateTime","date")){
    tab<-odk_date_table(data,variable)
  }
  
  if(variable_type%in%c("select")){
    if(!is.na(filter(form,ruodk_name==variable)$selectMultiple)){
      tab<-  odk_selectn_table(data = data,variable = variable,form = form,style=style) 
    }
    else{
      
      tab<-  odk_select1_table(data,variable,form,style=style)
    }
  }
  tab
  }
}


odk_plot<-function(data,variable,form,plotvar="",group=NA,repeats=NULL){
  
  variable_type<-filter(form,ruodk_name==variable)$type
  
  repeat_type<-filter(form,ruodk_name==variable)$repeats
  
  if(repeat_type==TRUE){
    data<-repeats[[filter(form,ruodk_name==variable)$repeat_data]] 
    data_variable<-filter(form,ruodk_name==variable)$repeat_name
    
    
    
    colnames(data)[colnames(data)==data_variable]<-variable
    
  }
  else{
    data_variable<-variable
  }
  
  
 
  if(variable_type %in% c("string","decimal","int","dateTime","date","select")){
  if(variable_type=="string"){
    p1<-NULL
  }
  
  if(variable_type%in%c("decimal","int","dateTime","date")){
       data$variable<-data[[variable]]
       p1<-data %>%
         ggplot(aes(x=variable))+
          geom_histogram()
  }

  
  if(variable_type%in%c("select")){
    
    #print(plotvar)
    if(plotvar==""){plotvar<-"Responses"}
    
    
    if(!is.na(filter(form,ruodk_name==variable)$selectMultiple)){
      p1<-  odk_selectn_table(data,variable,form,style=FALSE) %>%
        ungroup() %>%
        filter(Responses>0 & !is.na(!!sym(plotvar))) %>%
        mutate(response=reorder(str_wrap(Responses,50),!!sym(plotvar),sum,na.rm=T)) %>%
        ggplot(aes(y=labels,x=!!sym(plotvar)))+
        geom_col(fill=groundswell_palette[1],col="black")
      
    }
    else{
      
      p1<-  odk_select1_table(data,variable,form,style=FALSE) %>%
        ungroup() %>%
        filter(Responses>0 & !is.na(!!sym(plotvar))) %>%
        mutate(response=reorder(str_wrap(response,50),!!sym(plotvar),sum,na.rm=T)) %>%
        ggplot(aes(y=response,x=!!sym(plotvar)))+
        geom_col(fill=groundswell_palette[2],col="black")
    }
    if(plotvar!="Responses"){
      p1<-p1+scale_x_continuous(labels=scales::percent)
    }
  }
  p1+
    theme_light()+
    theme(axis.text.x = element_text(size=10),
          axis.title = element_text(size=12),
          axis.text.y = element_text(size=10))+
    labs(title=filter(form,ruodk_name==variable)$label) 
  }
  
}



#data1 <- NULL

read_clean_data<-function(fid=Sys.getenv("form_id"),language="default",
                          full_form=form_schema_ext(fid=fid)){
  
  data1<-list(selected_language=language  )
  
  repeats<-ruODK::odata_service_get(fid = fid) %>%
    filter(name!="Submissions")
  
  data1$full_data<-try(ruODK::odata_submission_get(fid = fid,
                                                   download=FALSE,
                                                   wkt = TRUE,
                                                   expand=FALSE#,
                                                  # filter = "__system/reviewState ne 'rejected'"
  ))
  
  data1$full_data <- data1$full_data%>%
    filter(data1$full_data$system_review_state!="rejected" | is.na(data1$full_data$system_review_state))
  
  if(nrow(repeats)>0){
    data1$repeats<-list()
    data1$repeats$list_name<-str_remove(repeats$name,"Submissions.") %>% str_replace_all(fixed("."),"_") %>% tolower()
    
    for(i in 1:nrow(repeats)){
      
      data1$repeats[[data1$repeats$list_name[i]]]<-try(ruODK::odata_submission_get(fid=fid,
                                                                                   table = repeats$name[i],
                                                                                   download=FALSE,
                                                                                   wkt = TRUE,
                                                                                   expand=FALSE
      ))
      
      data1$repeats[[data1$repeats$list_name[i]]] <- data1$repeats[[data1$repeats$list_name[i]]]%>%
        filter(submissions_id%in%data1$full_data$id)
    }
  }
  

  
  #  data_in <- reactive({
  #    class(data1$full_data)[1]!="try-error"
  #  })
  #  outputOptions(output, "data_in", suspendWhenHidden = FALSE)
  
  if(class(data1$full_data)[1]!="try-error"){
    
    data1$full_form<-full_form
    data1$form <-   data1$full_form
    
    # #print(!"FIES_1"%in%data1$full_form$name)
    # #print(!str_detect(data1$full_data$odata_context[1], "groundswell_farm_reg"))
    # #print("survey_grp_section_household_info_household_head_details_name_sex_calculated"%in%colnames(data1$full_data))
    
    if(nrow(data1$full_data)>0){
    if(!"FIES_1"%in%data1$full_form$name & !str_detect(data1$full_data$odata_context[1], "groundswell_farm_reg")){

      if("survey_grp_section_household_info_household_head_details_name_sex_calculated"%in%colnames(data1$full_data)){

        data1$full_data$household_head_sex <- data1$full_data$survey_grp_section_household_info_household_head_details_name_sex_calculated

      }else{

        data1$full_data$household_head_sex <- ifelse(data1$full_data$survey_grp_section_household_info_respondent_details_participant_is_head=="Y",
                                                     data1$full_data$section_meta_location2_participant_sex,
                                                     data1$repeats$survey_grp_section_household_info_householdpopulation_hh_pop_repeat$hh_pop_repeat_grp_person_gender[
                                                       data1$repeats$survey_grp_section_household_info_householdpopulation_hh_pop_repeat$submissions_id==data1$full_data$id &
                                                         data1$repeats$survey_grp_section_household_info_householdpopulation_hh_pop_repeat$hh_pop_rep_num==1])
        
        if(any(str_detect(colnames(data1$full_data),"participant_age_update") & data1$full_data$section_meta_location2_country[1]=="Nepal")){
          data1$full_data$participant_age <-
            data1$full_data$survey_grp_section_household_info_respondent_details_participant_age_update
        }
      }
      
      if("participant_age_update" %in% data1$full_form$name){
        
        data1$full_data$Age_Group <- cut(as.numeric(data1$full_data$survey_grp_section_household_info_respondent_details_participant_age_update),
                                                      breaks = c(18, 26, 55, 99),
                                                      labels = c("18 - 25", "26 - 55", "55+"),
                                                      ordered_result = TRUE)
        
        data1$full_data$Age_Group2 <- cut(as.numeric(data1$full_data$survey_grp_section_household_info_respondent_details_participant_age_update),
                                          breaks = c(18, 31, 50, 99),
                                          labels = c("18 - 30", "31 - 50", "51+"),
                                          ordered_result = TRUE)
      }else{
        
        data1$full_data$Age_Group <- cut(as.numeric(data1$full_data$survey_grp_section_household_info_respondent_details_participant_age),
                                         breaks = c(18, 26, 55, 99),
                                         labels = c("18 - 25", "26 - 55", "55+"),
                                         ordered_result = TRUE)
        
        data1$full_data$Age_Group2 <- cut(as.numeric(data1$full_data$survey_grp_section_household_info_respondent_details_participant_age),
                                          breaks = c(18, 31, 50, 99),
                                          labels = c("18 - 30", "31 - 50", "51+"),
                                          ordered_result = TRUE)
      }
    }
      
      if("FIES_1"%in%data1$full_form$name){
        
        data1$full_data$Womens_Age_Group <- cut(as.numeric(data1$full_data$survey_grp_womens_section_respondent_details_respondentage),
                                       breaks = c(18, 26, 55, 99),
                                       labels = c("18 - 25", "26 - 55", "55+"),
                                       ordered_result = TRUE)
        
        data1$full_data$Womens_Age_Group2 <- cut(as.numeric(data1$full_data$survey_grp_womens_section_respondent_details_respondentage),
                                        breaks = c(18, 31, 50, 99),
                                        labels = c("18 - 30", "31 - 50", "51+"),
                                        ordered_result = TRUE)
        
      }
    }

    keep_list<-data1$full_data %>%
      apply(2,function(x)sum(is.na(x))) %>%
      data.frame() %>%
      filter(.!=nrow(data1$full_data)) %>% rownames()
    
    data1$data<- data1$full_data %>%
      select(all_of(keep_list))
    
    if(language!="default"){
      data1$form$label<-data1$form[[paste0("label_",language)]]
      data1$form$choices<-data1$form[[paste0("choices_",language)]]
    }
    
    
    data1$form<-filter(  data1$form,!is.na(data1$form$label) | data1$form$type=="structure")
    
    
    
    data1$form$type<-ifelse(!is.na( data1$form$choices) &  
                              data1$form$type=="string","select", data1$form$type)
    if(nrow(repeats)>0){
      rep_names<-  data1$repeats%>%names() 
  
      data1$form$repeats<-str_detect(tolower(data1$form$path),
                                     paste(
                                       paste0("/", str_remove(tolower(repeats$name),"submissions.") %>% str_replace_all(fixed("."),"/")      ),
      collapse="|"))
      
      
      data1$form$repeat_name<-ifelse(data1$form$repeats==FALSE,NA,
                                     str_remove_all(data1$form$ruodk_name,paste(paste0(rep_names,"_"),collapse="|")))
    
      data1$form$repeat_data<-ifelse(data1$form$repeats==FALSE,NA,
                                     str_remove(data1$form$ruodk_name, paste0("_",data1$form$repeat_name)))
      
    }else{
      data1$form$repeats<-FALSE
    }
    
    selects<-filter( data1$form,type=="select" & is.na(selectMultiple) )
    #print(table(selects$repeats))
    #print(table(data1$form$repeats))
    
    for(i in 1:nrow(selects)){
    
        if(selects$repeats[i]==FALSE){
          if(length(na.omit(data1$data[[selects$ruodk_name[i]]]))>0){
          data1$data<-merge_labels(selects$ruodk_name[i],
                                   data = data1$data,
                                   form= data1$form)
          }
        }
        if(selects$repeats[i]==TRUE){
          if(length(na.omit(data1$repeats[selects$repeat_data[i]][[1]][[selects$repeat_name[i]]]))>0){
    
          data1$repeats[selects$repeat_data[i]][[1]]<-merge_labels(selects$repeat_name[i],
                                                                   data =  data1$repeats[selects$repeat_data[i]][[1]],
                                                                   form= data1$form,
                                                                   repeatgrp=TRUE)
        }
      } 
    }
    
    
    data1$groups<- data1$form %>%
      filter(type=="structure") %>%
      select(group_name=ruodk_name,group_label=label)%>%
      mutate(group_label=ifelse(is.na(group_label),group_name,group_label))
    
    if(nrow(data1$groups)>0){
      data1$form<- data1$form %>%
        mutate(group=final_string(ruodk_name,name)) %>%
        filter(type!="structure" & !is.na(group)) %>%
        left_join( data1$groups,by=c("group"="group_name"))
    }
    else{
      data1$form$group_label<-" "
    }
    
    data1$form2<-data1$form
    data1$form<-filter(data1$form,
                       ruodk_name%in%colnames(data1$data)|data1$form$repeats==TRUE )
    
    data1$any<-colnames(data1$data)[(colSums(is.na(data1$data))!=nrow(data1$data))]
    
    for(i in data1$repeats$list_name){

       if(!is.null(data1$repeats[[i]])){
      if(nrow(data1$repeats[[i]])>0){
      data1$any<-c(data1$any,
     paste(i,colnames(    data1$repeats[[i]])[(colSums(is.na(    data1$repeats[[i]]))!=nrow(    data1$repeats[[i]]))],sep="_")
      )
      }
      }
         }
    data1$form<-filter(  data1$form,
                         type%in%available_types & ruodk_name%in%data1$any)
    
    
    
    data1$form$group_label[is.na(data1$form$group_label)]<-" "
    data1$form$label<-make.unique( data1$form$label)
    
    data1$questionnairelist<-split( data1$form$label, factor(data1$form$group_label,
                                                             levels=unique(data1$form$group_label)))%>% lapply(function(x)(c(x,"")))
    
    

    
    
    return(data1)
    
  }
  else {
    
    return(data1$full_data)
  }
}



  

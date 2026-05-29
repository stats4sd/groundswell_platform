library(tidyverse)

#Honduras1

Honduras1_global <- read.csv("clean_data/Honduras1/Vecinos indicator form.csv")%>%
  select(-Age_Group,Age_Group2)

Honduras1_global$Age_Group <- cut(as.numeric(Honduras1_global$participant_age_update),
                                 breaks = c(18, 26, 55, 99),
                                 labels = c("18 - 25", "26 - 55", "55+"),
                                 ordered_result = TRUE)

Honduras1_global$Age_Group2 <- cut(as.numeric(Honduras1_global$participant_age_update),
                                  breaks = c(18, 31, 50, 99),
                                  labels = c("18 - 30", "31 - 50", "51+"),
                                  ordered_result = TRUE)

write.csv(Honduras1_global,"clean_data/Honduras1/Vecinos indicator form.csv", row.names = FALSE)

Honduras1_women <- read.csv("clean_data/Honduras1/Vecinos womens form.csv")%>%
  select(-Womens_Age_Group,Womens_Age_Group2)

Honduras1_women$Womens_Age_Group <- cut(as.numeric(Honduras1_women$respondentage),
                                  breaks = c(18, 26, 55, 99),
                                  labels = c("18 - 25", "26 - 55", "55+"),
                                  ordered_result = TRUE)

Honduras1_women$Womens_Age_Group2 <- cut(as.numeric(Honduras1_women$respondentage),
                                   breaks = c(18, 31, 50, 99),
                                   labels = c("18 - 30", "31 - 50", "51+"),
                                   ordered_result = TRUE)

write.csv(Honduras1_women,"clean_data/Honduras1/Vecinos womens form.csv", row.names = FALSE)

Honduras1_indicators <- read.csv("clean_data/Honduras1/Vecinos Indicators.csv")%>%
  select(-Age_Group,-Age_Group2,-Womens_Age_Group,-Womens_Age_Group2)

Honduras1_indicators <- Honduras1_indicators%>%
  left_join(
    Honduras1_global%>%select(household_id, Age_Group, Age_Group2)
  )%>%
  left_join(
    Honduras1_women%>%select(household_id, Womens_Age_Group, Womens_Age_Group2)
  )%>%
  relocate(Age_Group, Age_Group2, Womens_Age_Group, Womens_Age_Group2, .before = global_form_id)

write.csv(Honduras1_indicators,"clean_data/Honduras1/Vecinos Indicators.csv", row.names = FALSE)

#Honduras2
Honduras2_global <- read.csv("clean_data/Honduras2/ACESH indicator form.csv")%>%
  select(-Age_Group,-Age_Group2)

Honduras2_global$Age_Group <- cut(as.numeric(Honduras2_global$participant_age_update),
                                  breaks = c(18, 26, 55, 99),
                                  labels = c("18 - 25", "26 - 55", "55+"),
                                  ordered_result = TRUE)

Honduras2_global$Age_Group2 <- cut(as.numeric(Honduras2_global$participant_age_update),
                                   breaks = c(18, 31, 50, 99),
                                   labels = c("18 - 30", "31 - 50", "51+"),
                                   ordered_result = TRUE)

write.csv(Honduras2_global,"clean_data/Honduras2/ACESH indicator form.csv", row.names = FALSE)

Honduras2_women <- read.csv("clean_data/Honduras2/ACESH womens form.csv")%>%
  select(-Womens_Age_Group,-Womens_Age_Group2)

Honduras2_women$Womens_Age_Group <- cut(as.numeric(Honduras2_women$respondentage),
                                 breaks = c(18, 26, 55, 99),
                                 labels = c("18 - 25", "26 - 55", "55+"),
                                 ordered_result = TRUE)

Honduras2_women$Womens_Age_Group2 <- cut(as.numeric(Honduras2_women$respondentage),
                                  breaks = c(18, 31, 50, 99),
                                  labels = c("18 - 30", "31 - 50", "51+"),
                                  ordered_result = TRUE)

write.csv(Honduras2_women,"clean_data/Honduras2/ACESH womens form.csv", row.names = FALSE)

Honduras2_indicators <- read.csv("clean_data/Honduras2/ACESH Indicators.csv")#%>%
# select(-Age_Group,-Age_Group2,-Womens_Age_Group,-Womens_Age_Group2)

Honduras2_indicators <- Honduras2_indicators%>%
  left_join(
    Honduras2_global%>%select(household_id, Age_Group, Age_Group2)
  )%>%
  left_join(
    Honduras2_women%>%select(household_id, Womens_Age_Group, Womens_Age_Group2)
  )%>%
  relocate(Age_Group, Age_Group2, Womens_Age_Group, Womens_Age_Group2, .before = global_form_id)

write.csv(Honduras2_indicators,"clean_data/Honduras2/ACESH Indicators.csv", row.names = FALSE)


#Senegal

Senegal_global <- read.csv("clean_data/Senegal/Indicators form.csv")%>%
  select(-Age_Group,-Age_Group2)

Senegal_global$Age_Group <- cut(as.numeric(Senegal_global$participant_age),
                                  breaks = c(18, 26, 55, 99),
                                  labels = c("18 - 25", "26 - 55", "55+"),
                                  ordered_result = TRUE)

Senegal_global$Age_Group2 <- cut(as.numeric(Senegal_global$participant_age),
                                   breaks = c(18, 31, 50, 99),
                                   labels = c("18 - 30", "31 - 50", "51+"),
                                   ordered_result = TRUE)


write.csv(Senegal_global,"clean_data/Senegal/Indicators form.csv", row.names = FALSE)

Senegal_women <- read.csv("clean_data/Senegal/Womens form.csv")%>%
  select(-Womens_Age_Group,-Womens_Age_Group2)

Senegal_women$Womens_Age_Group <- cut(as.numeric(Senegal_women$respondentage),
                                 breaks = c(18, 26, 55, 99),
                                 labels = c("18 - 25", "26 - 55", "55+"),
                                 ordered_result = TRUE)

Senegal_women$Womens_Age_Group2 <- cut(as.numeric(Senegal_women$respondentage),
                                  breaks = c(18, 31, 50, 99),
                                  labels = c("18 - 30", "31 - 50", "51+"),
                                  ordered_result = TRUE)

write.csv(Senegal_women,"clean_data/Senegal/Womens form.csv", row.names = FALSE)

Senegal_indicators <- read.csv("clean_data/Senegal/Indicators.csv")#%>%
#  select(-Age_Group,-Age_Group2,-Womens_Age_Group,-Womens_Age_Group2)

Senegal_indicators <- Senegal_indicators%>%
  left_join(
    Senegal_global%>%select(household_id, Age_Group, Age_Group2)
  )%>%
  left_join(
    Senegal_women%>%select(household_id, Womens_Age_Group, Womens_Age_Group2)
  )%>%
  relocate(Age_Group, Age_Group2, Womens_Age_Group, Womens_Age_Group2, .before = global_form_id)

write.csv(Senegal_indicators,"clean_data/Senegal/Indicators.csv", row.names = FALSE)
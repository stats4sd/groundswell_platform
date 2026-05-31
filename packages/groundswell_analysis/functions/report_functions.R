# data <- global
# form <- global_form
# var <- "Agric_Inputs"
# filter_crop<- TRUE
# filter_livestock <- FALSE
# language = "english_(en)"
# language_file = params$translations
# data_type = "processed"

multi_table <- function(
  data,
  form,
  var,
  filter_crop = FALSE,
  filter_livestock = FALSE,
  language = "english_(en)",
  language_file,
  data_type = "raw"
) {
  factors <- data.frame(filter(form, name == var)$choices_lang) %>%
    distinct() %>%
    filter(values != "no_answer")

  if (data_type == "processed") {
    data$id <- data$KEY
  }

  if (filter_crop == TRUE) {
    multis <- data.frame(
      form_id = data$id,
      grow_crops = data[["grow_crops"]],
      str_split_fixed(data[[var]], " ", nrow(factors))
    ) %>%
      filter(grow_crops == levels(data$grow_crops)[1]) %>%
      select(-grow_crops) %>%
      filter(X1 != "no_answer" | is.na(X1))
  }

  if (filter_livestock == TRUE) {
    multis <- data.frame(
      form_id = data$id,
      livestock_owners = data[["livestock_owners"]],
      str_split_fixed(data[[var]], " ", nrow(factors))
    ) %>%
      filter(livestock_owners == levels(data$livestock_owners)[1]) %>%
      select(-livestock_owners) %>%
      filter(X1 != "no_answer" | is.na(X1))
  }

  for (i in factors$values) {
    colname <- i

    multis <- multis %>%
      rowwise() %>%
      mutate(
        !!colname := factor(ifelse(
          is.na(X1),
          0,
          ifelse(any(str_detect(c_across(2:(nrow(factors) + 1)), i)), 1, 0)
        ))
      )

    if (length(levels(multis[[colname]])) == 1) {
      levels(multis[[colname]]) <- c(0, 1)
    }
  }

  if ("None" %in% factors$values) {
    multis$Any <- ifelse(multis$None == 1, 0, 1)
    multis$Any <- ifelse(is.na(multis$X1), 0, multis$Any)
  } else if ("none" %in% factors$values) {
    multis$Any <- ifelse(multis$none == 1, 0, 1)
    multis$Any <- ifelse(is.na(multis$X1), 0, multis$Any)
  } else {
    multis$Any <- ifelse(!is.na(multis$X1), 1, 0)
  }

  colnames(multis)[(nrow(factors) + 2):ncol(multis)] <- c(
    factors$labels,
    language_file[language_file$`english_(en)` == "Any", language]
  )

  multis %>%
    tbl_summary(
      include = (nrow(factors) + 2):ncol(multis),
      type = everything() ~ "dichotomous",
      value = ~1,
      missing = "no"
    )
}

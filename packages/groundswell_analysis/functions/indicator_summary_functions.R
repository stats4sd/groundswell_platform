#data <- read.csv("clean_data/Honduras1/Vecinos Indicators.csv")
#indicator <- "I_HI_have_debt"
#groupvar <- "participant_sex"#
#data <- data1$indicators

theme_groundswell <- function() {
  #font <- "Phetsarath OT"   #assign font family up front

  theme_bw() %+replace% #replace elements we want to change

    theme(
      #grid elements
      #panel.grid.major = element_line(colour = "#006D5B"),    #strip major gridlines
      panel.grid.minor = element_blank(), #strip minor gridlines
      #axis.ticks = element_line(colour = "#006D5B"),          #strip axis ticks

      #since theme_minimal() already strips axis lines,
      #we don't need to do that again

      #text elements
      plot.title = element_text(
        #title
        #family = font,            #set font family
        size = 20, #set font size
        face = 'bold', #bold typeface
        hjust = 0, #left align
        vjust = 2,
        colour = "#000"
      ), #raise slightly

      plot.subtitle = element_text(
        #subtitle
        #family = font,            #font family
        size = 14,
        colour = "#000"
      ), #font size

      plot.caption = element_text(
        #caption
        #family = font,            #font family
        size = 9, #font size
        hjust = 1,
        colour = "#000"
      ), #right align

      axis.title = element_text(
        #axis titles
        #family = font,            #font family
        size = 15,
        colour = "#000", #set font size
        face = 'bold'
      ), #font size

      strip.text = element_text(
        #axis titles
        #family = font,            #font family
        size = 15,
        colour = "#000", #set font size
        margin = margin(t = 8, b = 8),
        face = 'bold'
      ), #font size

      axis.text = element_text(
        #axis text
        #family = font,            #axis famuly
        size = 12,
        colour = "#000"
      ), #font size

      axis.text.x = element_text(
        #margin for axis text
        margin = margin(5, b = 10)
      ),

      #panel.background = element_rect(fill = "#004D40"),
      #plot.background = element_rect(fill = "#004D40")

      legend.text = element_text(
        size = 12,
        colour = "#000"
      )

      #since the legend often requires manual tweaking
      #based on plot content, don't define it here
    )
}

indicator_table <- function(
  data,
  indicator,
  indicator_directory,
  groupvar,
  language,
  translation_file = report_translations
) {
  type <- indicator_directory$type[
    indicator_directory$indicator_name == indicator
  ]

  ignore_respondent_gender <- indicator_directory$ignore_respondent_gender[
    indicator_directory$indicator_name == indicator
  ]

  data$indicator <- data[[indicator]]
  data$groupvar <- data[[groupvar]]

  if (ignore_respondent_gender == TRUE & groupvar == "participant_sex") {
    data$groupvar <- language_switch("var_ignore_gender", language)
  }

  data <- data %>%
    filter(!is.na(groupvar))

  indicator_name <- indicator_directory[
    indicator_directory$indicator_name == indicator,
    language
  ]

  if (type %in% c("decimal", "integer", "percent")) {
    if (indicator %in% c("I_FIES_score", "I_HDDS", "I_HI_n_income_sources")) {
      table <- data %>%
        tbl_summary(
          by = groupvar,
          include = indicator,
          type = indicator ~ "categorical",
          label = list(indicator ~ indicator_name)
        )
    } else {
      if (type == "percent") {
        data$indicator <- data$indicator * 100
      }

      table <- data %>%
        tbl_summary(
          by = groupvar,
          include = indicator,
          type = indicator ~ "continuous2",
          label = list(indicator ~ indicator_name),
          statistic = all_continuous() ~ c(
            "{mean} ({sd})",
            "{median} ({p25}, {p75})",
            "{min}, {max}"
          ),
        )
    }
  }

  if (type == "categorical") {
    table <- data %>%
      tbl_summary(
        by = groupvar,
        include = indicator,
        label = list(indicator ~ indicator_name),
      )
  }

  if (type == "binary") {
    table <- data %>%
      mutate(
        indicator = factor(
          indicator,
          levels = c(0, 1),
          labels = translation_file[
            translation_file$indicator == "binaries",
            language
          ]
        )
      ) %>%
      tbl_summary(
        by = groupvar,
        include = indicator,
        type = indicator ~ "categorical",
        label = list(indicator ~ indicator_name),
      )
  }

  return(table)
}

#indicator_table(data1$indicators, "I_FIES_category", indicator_directory, "participant_sex", language = "english_(en)")

indicator_plot <- function(
  data,
  indicator,
  indicator_directory,
  groupvar,
  language,
  translation_file = report_translations
) {
  type <- indicator_directory$type[
    indicator_directory$indicator_name == indicator
  ]

  ignore_respondent_gender <- indicator_directory$ignore_respondent_gender[
    indicator_directory$indicator_name == indicator
  ]

  data$indicator <- data[[indicator]]
  data$groupvar <- data[[groupvar]]

  if (ignore_respondent_gender == TRUE & groupvar == "participant_sex") {
    data$groupvar <- language_switch("var_ignore_gender", language)
  }

  data <- data %>%
    filter(!is.na(groupvar))

  data <- data %>%
    filter(!is.na(indicator))

  indicator_name <- str_wrap(
    indicator_directory[
      indicator_directory$indicator_name == indicator,
      language
    ],
    45
  )

  if (type %in% c("decimal", "integer")) {
    bins <- length(unique(data$indicator))
    bins_max <- ceiling(max(data$indicator, na.rm = TRUE))

    if (bins > 13 & type == "integer") {
      plot <- data %>%
        ggplot(aes(x = indicator)) +
        geom_bar(fill = groundswell_palette[2], colour = "black") +
        facet_wrap(~groupvar) +
        theme_groundswell() +
        scale_y_continuous(n.breaks = 10) +
        labs(
          x = indicator_name,
          y = language_switch("title_households_number", language)
        )

      ht <- paste0(
        report_translations[
          report_translations$`english_(en)` == "Indicator",
          language
        ],
        ": %{x}<br>",
        report_translations[
          report_translations$`english_(en)` == "Number of Households",
          language
        ],
        ": %{y}<extra></extra>"
      )

      plot <- plot %>%
        ggplotly() %>%
        layout(margin = list(b = 80))
      plot$x$data <- lapply(plot$x$data, function(trace) {
        trace$hovertemplate <- rep(ht, max(length(trace$x), 1))
        trace$text <- NULL
        trace
      })
    } else if (bins > 13 & type == "decimal") {
      plot <- data %>%
        ggplot(aes(x = indicator)) +
        geom_histogram(
          fill = groundswell_palette[2],
          colour = "black",
          boundary = 0
        ) +
        scale_y_continuous(n.breaks = 10) +
        facet_wrap(~groupvar) +
        theme_groundswell() +
        labs(
          x = indicator_name,
          y = language_switch("title_households_number", language)
        )

      ht <- paste0(
        report_translations[
          report_translations$`english_(en)` == "Indicator",
          language
        ],
        ": %{x}<br>",
        report_translations[
          report_translations$`english_(en)` == "Number of Households",
          language
        ],
        ": %{y}<extra></extra>"
      )

      plot <- plot %>%
        ggplotly() %>%
        layout(margin = list(b = 80))
      plot$x$data <- lapply(plot$x$data, function(trace) {
        trace$hovertemplate <- rep(ht, max(length(trace$x), 1))
        trace$text <- NULL
        trace
      })
    } else {
      plot <- data %>%
        group_by(indicator, groupvar) %>%
        summarise(n = n(), n_lab = paste0("n = ", n)) %>%
        ggplot(aes(
          x = indicator,
          y = n,
          text = paste0(
            report_translations[
              report_translations$`english_(en)` == "Indicator",
              language
            ][1],
            ": ",
            indicator,
            "<br>",
            report_translations[
              report_translations$`english_(en)` == "Number of Households",
              language
            ][1],
            ": ",
            n
          )
        )) +
        geom_col(fill = groundswell_palette[2], colour = "black") +
        geom_label(aes(label = n_lab)) +
        facet_wrap(~groupvar) +
        theme_groundswell() +
        scale_y_continuous(n.breaks = 10) +
        labs(
          x = indicator_name,
          y = language_switch("title_households_number", language)
        )

      plot <- plot %>%
        ggplotly(tooltip = "text") %>%
        layout(margin = list(b = 80))
    }
  }

  if (type == "percent") {
    plot <- data %>%
      ggplot(aes(x = indicator)) +
      geom_histogram(
        fill = groundswell_palette[2],
        colour = "black",
        boundary = 0
      ) +
      facet_wrap(~groupvar) +
      theme_groundswell() +
      labs(
        x = indicator_name,
        y = language_switch("title_households_perc", language)
      ) +
      scale_y_continuous(n.breaks = 10) +
      scale_x_continuous(labels = scales::percent)

    ht <- paste0(
      report_translations[
        report_translations$`english_(en)` == "Indicator",
        language
      ],
      ": %{x}<br>",
      report_translations[
        report_translations$`english_(en)` == "Number of Households",
        language
      ],
      ": %{y}<extra></extra>"
    )
    plot <- plot %>%
      ggplotly() %>%
      layout(margin = list(b = 80))
    plot$x$data <- lapply(plot$x$data, function(trace) {
      trace$hovertemplate <- rep(ht, max(length(trace$x), 1))
      trace$text <- NULL
      trace
    })
  }

  if (type == "categorical") {
    if (indicator != "I_HI_income_diversification") {
      data$indicator <- factor(
        data$indicator,
        levels = levels(data$indicator),
        labels = str_wrap(levels(data$indicator), 40)
      )
    }

    plot <- data %>%
      group_by(groupvar) %>%
      mutate(N = n()) %>%
      group_by(indicator, groupvar) %>%
      summarise(
        n = n(),
        N = mean(N),
        perc = n / N,
        n_lab = paste0("n = ", n)
      ) %>%
      ggplot(aes(
        y = indicator,
        x = perc,
        text = paste0(
          report_translations[
            report_translations$`english_(en)` == "Indicator",
            language
          ][1],
          ": ",
          indicator,
          "<br>",
          report_translations[
            report_translations$`english_(en)` == "Percentage of households",
            language
          ][1],
          ": ",
          scales::percent(perc, accuracy = 0.1),
          "<br>",
          report_translations[
            report_translations$`english_(en)` == "Number of Households",
            language
          ][1],
          ": ",
          n
        )
      )) +
      geom_col(fill = groundswell_palette[2], colour = "black") +
      geom_label(aes(
        label = n_lab,
        x = ifelse(perc < 0.05, 0.1, ifelse(perc > 0.9, 0.85, perc))
      )) +
      facet_wrap(~groupvar) +
      scale_x_continuous(labels = scales::percent, limits = c(0, 1)) +
      #scale_y_discrete(labels = str_wrap(levels(indicator),25))+
      theme_groundswell() +
      labs(
        y = indicator_name,
        x = language_switch("title_households_perc", language)
      )

    plot <- plot %>%
      ggplotly(tooltip = "text") %>%
      layout(margin = list(b = 80))
  }

  if (type == "binary") {
    plot <-
      data %>%
      mutate(
        indicator = factor(
          indicator,
          levels = c(0, 1),
          labels = translation_file[
            translation_file$indicator == "binaries",
            language
          ]
        )
      ) %>%
      group_by(groupvar) %>%
      mutate(N = n()) %>%
      group_by(groupvar, indicator) %>%
      summarise(n = n(), perc = n / max(N), n_lab = paste("n = ", n)) %>%
      ggplot(aes(
        x = groupvar,
        y = perc,
        fill = as.factor(indicator),
        text = paste0(
          report_translations[
            report_translations$`english_(en)` == "Indicator",
            language
          ][1],
          ": ",
          indicator,
          "<br>",
          report_translations[
            report_translations$`english_(en)` == "Percentage of households",
            language
          ][1],
          ": ",
          scales::percent(perc, accuracy = 0.1),
          "<br>",
          report_translations[
            report_translations$`english_(en)` == "Number of Households",
            language
          ][1],
          ": ",
          n
        )
      )) +
      geom_col(position = position_dodge(width = 0.9), colour = "black") +
      geom_label(
        aes(label = n_lab),
        position = position_dodge(width = 0.9),
        show.legend = FALSE
      ) +
      theme_groundswell() +
      theme(legend.position = "top") +
      labs(y = indicator_name, x = NULL, fill = NULL) +
      scale_y_continuous(labels = scales::percent, limits = c(0, 1)) +
      scale_fill_manual(
        values = c(groundswell_palette[7], groundswell_palette[4])
      )

    plot <- plot %>%
      ggplotly(tooltip = "text") %>%
      layout(margin = list(b = 80))
  }

  return(plot)
}

#indicator_plot(data1$indicators, indicator = indicator, indicator_directory, groupvar = "participant_sex")

############## libraries needed
library(shiny)
library(shinydashboard)
library(shinydashboardPlus)
library(shinyWidgets)
library(openxlsx)
library(tidyverse)
library(leaflet)
library(dotenv)
library(DT)
library(gt)
library(ruODK)
#devtools::install_version("sf", version = "1.0-16", repos = "http://cran.us.r-project.org")
library(sf)
library(gitcreds)
library(shinyLaravelAuth)
library(jsonlite)
library(gtsummary)
library(shinycssloaders)
library(RM.weights)
library(plotly)

# options(repos = c(
#  ropensci = "https://ropensci.r-universe.dev",
#  CRAN = "https://cloud.r-project.org"
# ))
# install.packages("ruODK")

source("functions/functions.R")
source("functions/indicators_function.R")
source("functions/indicator_summary_functions.R")

dotenv::load_dot_env()

### HARD CODING THIS AT TOP - will need to update the project id dynamically
project_code <- "Senegal"
project_id <- Sys.getenv(paste(project_code, "project_id", sep = "_"))


## Static UI shell — contains NOTHING sensitive.
## Anyone hitting the Shiny URL directly sees only the loading spinner until
## Laravel calls back via the shinyLaravelAuth handshake. The real dashboard
## UI is defined inside the server in `render_authenticated_ui()` and dropped
## into the `authenticated_ui` placeholder once `on_authenticated` fires.
ui <- fluidPage(
  tags$head(
    laravel_auth_script(),
    tags$style(HTML("
      .gs-loading-wrap {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #FFF;
        z-index: 9999;
      }
      .gs-spinner {
        width: 64px;
        height: 64px;
        border: 6px solid #e8e8e8;
        border-top-color: #888;
        border-radius: 50%;
        animation: gs-spin 1s linear infinite;
      }
      @keyframes gs-spin {
        to { transform: rotate(360deg); }
      }
      /* Hide the loader once the authenticated UI has rendered content */
      body:has(#authenticated_ui > *) #gs-loading {
        display: none;
      }
    "))
  ),
  div(
    class = "gs-loading-wrap",
    id = "gs-loading",
    div(class = "gs-spinner")
  ),
  uiOutput("authenticated_ui")
)


authenticated_ui <- function() {
  dashboardPage(
    skin = "black",
    title = "Groundswell Survey Monitoring App",

    dashboardHeader(
      title = "Groundswell Survey Monitoring App",
      titleWidth = 450,
      disable = TRUE
    ),

    dashboardSidebar(
      sidebarMenu(
        id = "tabs",
        sidebarMenuOutput("DataMenu"),
        sidebarMenuOutput("View2Menu"),
        sidebarMenuOutput("SummariseMenu"),
        sidebarMenuOutput("IndicatorsMenu"),
        sidebarMenuOutput("ReportMenu"),

        width = 9
      ),
      width = 300
    ),
    dashboardBody(
      tags$head(
        tags$style(HTML("
          .content-wrapper, .right-side, body, .wrapper {
            background-color: #FFF !important;
          }
          .skin-black .main-sidebar, .skin-black .left-side {
            background-color: #FFF !important;
          }
          .skin-black .sidebar a {
            color: #333 !important;
          }
          .skin-black .sidebar-menu > li.active > a,
          .skin-black .sidebar-menu > li:hover > a {
            background-color: #e8e8e8 !important;
            color: #000 !important;
            border-left-color: #888 !important;
          }
          .skin-black .sidebar-menu > li > .treeview-menu {
            background-color: #FFF !important;
          }
        "))
      ),
      tabItems(
      tabItem(
        ## Data tab
        tabName = "Data",
        box(
          #if form is linked then bring in data
          fluidRow(
            conditionalPanel(
              "output.form_status",
              #initial language hard coded based on senegal as starting point
              column(
                width = 12,
                selectInput(
                  "language",
                  label = "Select Language",
                  choices = c(
                    "English" = "english_(en)",
                    "Français" = "français_(fr)",
                    "Español" = "español_(es)"
                  ),
                  width = "50%"
                )
              ),
              column(
                width = 12,
                conditionalPanel(
                  "output.data_in",
                  textOutput("lang_message"),
                ),
                actionButton("confirm", label = "Click Here To Load Data")
              )
            ),
            column(
              width = 12,
              textOutput("data_message"),
              textOutput("Message1"),
              textOutput("Message2"),
              textOutput("Message3")
            ),
            #If data is loaded then present summary table
            conditionalPanel(
              "output.data_in",
              box(
                fluidRow(
                  gt_output("MonitorTable"),

                  selectInput(
                    "monitor_table",
                    "Table:",
                    choices = c(
                      "Farmer Completion" = "match",
                      "Form Completion" = "completion"
                    ),
                    selected = "match",
                    width = "50%"
                  )
                ),
                width = 12
              )
            )
          ),
          width = 12
        )
      ),

      #Simple DT navigation and option to download data after selecting form
      tabItem(
        tabName = "View",
        conditionalPanel(
          "output.data_in",
          box(
            column(
              fluidRow(
                selectInput(
                  "form1",
                  label = "Select form",
                  choices = c(
                    "Global Indicators" = "global",
                    "Women's Form" = "women",
                    "Registration Form" = "reg"
                  )
                )
              ),
              width = 12
            ),
            column(
              conditionalPanel(
                "output.data_in",
                fluidRow(downloadButton("dl", "Download Form Data as CSV")),
                fluidRow(
                  downloadButton(
                    "dlx",
                    "Download Form Data as XLSX (including repeats)"
                  )
                )
              ),
              width = 12
            ),
            width = 6
          ),
          box(
            column(
              fluidRow(
                style = 'overflow-x: scroll',
                DT::dataTableOutput("data1")
              ),
              width = 12
            ),
            width = 12
          )
        )
      ),
      tabItem(
        tabName = "View2",
        conditionalPanel(
          "output.data_in",
          box(
            column(
              width = 12,
              fluidRow(
                selectInput(
                  "cleandat",
                  label = "Select processed dataset",
                  choices = c(
                    "Global Indicators" = "global",
                    "Women's Form" = "womens",
                    "Crop Production" = "crops",
                    "Off Farm Income" = "off_farm",
                    "Household Roster" = "hh_roster",
                    "Calculated Indicators" = "indicators"
                  ),
                )
              )
            ),
            column(
              width = 12,
              fluidRow(downloadButton(
                "dl_processed",
                "Download Selected Processed Data as CSV"
              )),
              fluidRow(downloadButton(
                "dlx_processed",
                "Download All Processed Data as XLSX"
              ))
            ),
            width = 6
          ),
          #,
          #fluidRow(downloadButton("dlz","Download All Data as Zip")
          #),
          box(fluidRow((gt_output("ProcessedTable"))), width = 6),
          box(
            width = 12,
            column(
              width = 12,
              fluidRow(
                style = 'overflow-x: scroll',
                DT::dataTableOutput("data_clean")
              )
            )
          )
        )
      ),
      #Monitoring results at enumerator level
      tabItem(
        tabName = "Enumerator",
        conditionalPanel(
          "output.data_in",
          box(
            column(
              width = 12,
              fluidRow(
                selectInput(
                  "enums",
                  "Select Enumerator",
                  choices = c(""),
                  multiple = TRUE
                )
              ),
              fluidRow(
                dateRangeInput(
                  "dates",
                  "Select Date Range",
                  start = as.Date("2025-01-01"),
                  end = as.Date("2026-12-12")
                )
              )
            ),
            width = 12
          ),
          box(
            column(width = 12, fluidRow(dataTableOutput("enum_summary"))),
            width = 12
          )
        )
      ),
      #Monitoring results at overall level
      tabItem(
        tabName = "Monitor",
        conditionalPanel(
          "output.data_in",

          box(
            column(fluidRow(plotlyOutput("Dates")), width = 12),
            width = 12
          ),

          box(
            column(
              fluidRow(
                selectInput(
                  "geo_var",
                  "Select Co-ordinate Variable",
                  choices = c("None" = 0)
                ),
                conditionalPanel(
                  "input.geo_var!='0'",
                  leafletOutput("MonitorMap")
                )
              ),
              width = 12
            ),
            width = 12
          ),
          box(
            column(
              width = 12,
              fluidRow(
                selectInput(
                  "inspect",
                  "Select Farmer to Inspect",
                  choices = c("None" = 0),
                  width = "50%"
                ),
                conditionalPanel(
                  "output.data_in",
                  gt_output("inspect_summary"),
                  selectInput(
                    "form_i",
                    label = "Select form",
                    choices = c(
                      "Global Indicators" = "global",
                      "Women's Form" = "women"
                    ),
                    width = "50%"
                  )
                ),
                DT::dataTableOutput("inspect_i")
              )
            ),
            width = 12
          )
        )
      ),

      #Summaries of the variables
      tabItem(
        tabName = "Summarise",
        conditionalPanel(
          "output.data_in",
          box(
            width = 12,
            column(
              width = 12,
              fluidRow(
                selectInput(
                  "form2",
                  label = "Select form",
                  choices = c(
                    "Global Indicators" = "global",
                    "Women's Form" = "women"
                  )
                )
              )
            )
          ),
          box(
            width = 12,
            column(
              width = 12,
              fluidRow(
                selectInput(
                  "question",
                  "Select Question",
                  choices = "Please Load Data",
                  width = "50%"
                )
              ),
              fluidRow(
                gt_output("SummaryTable"),
                conditionalPanel(
                  "output.select1",
                  selectInput(
                    "select1plot",
                    label = "Statistic to plot",
                    choices = c(
                      "Responses",
                      "Percent of Non-Missing",
                      "Percent of All"
                    ),
                    width = "50%"
                  )
                )
              )
            )
          ),
          box(
            width = 12,
            column(
              width = 12,
              fluidRow(
                conditionalPanel(
                  "output.selectm",
                  selectInput(
                    "selectmplot",
                    label = "Statistic to plot",
                    choices = c(
                      "Responses",
                      "% of Responses",
                      "% of Question Respondents",
                      "% of All Respondents"
                    ),
                    width = "50%"
                  )
                ),
                plotOutput("SummaryPlot"),
              )
            )
          )
        )
      ),
      tabItem(
        tabName = "Indicators",
        conditionalPanel(
          "output.data_in",
          box(
            column(
              width = 12,
              fluidRow(
                #  selectInput("indicatorData", "Select Indicator data source", choices = c(
                #    "Raw data" = "raw",
                #    "Processed data" = "processed"
                #  ))
                #  ,
                selectInput(
                  "indicator",
                  "Select Indicator",
                  choices = available_indicators
                ),
                selectInput(
                  "groupvar",
                  "Select grouping variable",
                  choices = c(
                    "Partner" = "partner",
                    "Respondent Sex" = "participant_sex",
                    "Household head education level" = "education_level",
                    "Sex of Household Head" = "household_head_sex"
                  )
                ),
                downloadButton(
                  "dl_indicators",
                  "Download Indicator Data as CSV"
                ),
              )
            ),
            width = 6
          ),
          box(
            fluidRow(column(htmlOutput("indicator_text"), width = 12)),
            fluidRow(column(
              withSpinner(
                gt_output("IndicatorTable")
              ),
              width = 12
            )),
            fluidRow(column(
              plotlyOutput("IndicatorPlot", height = 600),
              width = 12
            )),
            width = 12
          ),
          # box(column(width=12,
          #   fluidRow(
          #   style = 'overflow-x: scroll',
          #   DT::dataTableOutput("data_indicators"))),
          #   width = 12
          # )
        )
      ),
      tabItem(
        tabName = "Modules",
        conditionalPanel(
          "output.data_in",
          fluidRow(
            selectInput("module", "Select Module", choices = modules$module)
          ),
          fluidRow(gt_output("ModuleTable"), plotOutput("ModulePlot"))
        )
      ),
      tabItem(
        tabName = "Report",
        fluidRow(
          conditionalPanel(
            "output.data_in",
            box(
              #selectInput(
              #  "report_data",
              #  "Select data type",
              #  choices = c("Raw data" = "raw", "Processed data" = "processed")
              #),
              # selectInput(
              #   "language_report",
              #   "Select language",
              #   choices = c(
              #     "English" = "english_(en)",
              #     "Français" =
              #       "français_(fr)",
              #     "Español" =
              #       "español_(es)"
              #   )
              # ),
              # selectInput(
              #   "summary_vars",
              #   "Select variables to include in demographic summary",
              #   choices = c(
              #     "District" = "loc1_name",
              #     "Village" = "loc2_name",
              #     "Respondent age" = "participant_age",
              #     "Repondent sex" = "participant_sex",
              #     "Responding woman's age" = "respondentage",
              #     "Education level (Household Head)" = "education_level"
              #   ),
              #   selected = c(
              #     "loc1_name",
              #     "participant_age",
              #     "participant_sex",
              #     "respodentage",
              #     "education_level"
              #   ),
              #   multiple = TRUE
              # ),

              selectInput(
                "regen",
                label = "",
                choices = c("Generate new report" = 0)
              ),
              conditionalPanel(
                "input.regen==0",
                actionButton(
                  "genBaiscReport",
                  "Generate Project Summary Report"
                )
              )
            ),
            width = 8
          ),
          box(
            conditionalPanel(
              "input.regen==1",
              downloadButton("printBasicReport", "Download Report")
            ),
            conditionalPanel(
              "input.regen==0",
              downloadButton("printBasicReport2", "Download Report")
            ),
            width = 8
          )
        )
      )
    )
  )
  )
}


# Define server logic required to draw a histogram
server <- function(input, output, session) {

  # Renders the real dashboard into the placeholder once the Laravel
  # postMessage/POST handshake confirms the viewer is authorised.
  render_authenticated_ui <- function() {
    output$authenticated_ui <- renderUI({
      authenticated_ui()
    })
  }

  # auth$user is a constant placeholder confirming authorisation succeeded.
  # auth$input is a reactiveValues object holding whatever payload Laravel
  # POSTed alongside the callback. The Laravel app currently sends nothing
  # extra, but in future calls (e.g. an investment/project selector) the
  # posted keys will be available here as auth$input$<key_name>, e.g.
  # `auth$input$project_id` or `auth$input$investment_id`. Read them from
  # within an observeEvent(auth$user, { ... }) once the handshake fires.
  auth <- laravel_auth(session, on_authenticated = render_authenticated_ui)


  messages <- reactiveValues(
    data1 = NULL,
    data2 = NULL,
    data3 = NULL,
    data_message = "",
    progress = NULL,
    loading = 0,
    project_code = project_code
  )
  data1 <- reactiveValues(
    flag = FALSE,
    data = 0,
    forms = NULL,
    progress = 0,
    selected_forms = NULL,
    selected_language = "default",
    data0 = NULL
  )
  data_processed <- reactiveValues()
  outpath <- reactiveValues()
  outpath$outpath <- paste0("output/reports")

  #if password patches then load the dashboard

  output$DataMenu <- renderMenu({
    sidebarMenu(
      menuItem(
        tabName = "Data",
        text = language_switch("tabName_Data", input$language)
      )
    )
  })

  output$SummariseMenu <- renderMenu({
    sidebarMenu(
      menuItem(
        tabName = "Summarise",
        text = language_switch("tabName_Summarise", input$language)
      )
    )
  })
  output$View2Menu <- renderMenu({
    sidebarMenu(
      menuItem(
        tabName = "View2",
        text = language_switch("tabName_View2", input$language)
      )
    )
  })
  output$IndicatorsMenu <- renderMenu({
    sidebarMenu(
      menuItem(
        tabName = "Indicators",
        text = language_switch("tabName_Indicators", input$language)
      )
    )
  })

  proj_info <- reactiveValues(
    pid = Sys.getenv(paste(project_code, "project_id", sep = "_"))
  )

  #create a reactive object to indicate if this is a real project or one not set up yet
  #if not set up this will block progress in ui and return a message
  output$form_status <- reactive({
    proj_info$pid > 0
  })

  outputOptions(output, "form_status", suspendWhenHidden = FALSE)

  observeEvent(proj_info$pid, {
    if (proj_info$pid == 0) {
      output$data_message1 <- renderText(
        "No currently active ODK Central data collection activities for this project.
                                        If this is incorrect please link the project ID from within ODK Central to the dashboard and retry."
      )

      updateSelectInput(session = session, "language", choices = c(""))
    } else {
      output$data_message1 <- renderText("")

      #hard coding the languages for each project
      #it seemed more efficient to do it this way rather than after the data was read in since it keeps the data import to one single (long-ish) step
      #reading in the language options first and then reading in the data felt a bit annoying

      languages <- c(
        "english_(en)",
        "español_(es)",
        "nepali_(ne)",
        "français_(fr)"
      )
      names(languages) <- c("English", "Español", "Nepali", "Français")

      language_proj <- data_directory %>%
        filter(project == project_code) %>%
        select(Language) %>%
        as.character() %>%
        str_split("; ") %>%
        unlist() %>%
        trimws()
      updateSelectInput(
        session = session,
        "language",
        choices = languages[names(languages) %in% language_proj]
      )
    }
  })

  observeEvent(input$language, {
    #for nepali (and possibly other languages) the translations will not made for any app content - only the questions in the survey

    output$lang_message <- renderText(language_switch(
      "updateLanguage",
      input$language
    ))

    #update interface after changing languages

    updateSelectInput(
      session = session,
      inputId = "country",
      label = language_switch("select_Country", input$language)
    )

    updateSelectInput(
      session = session,
      "language",
      label = language_switch("select_Language", input$language)
    )

    updateActionButton(
      session = session,
      "confirm",
      label = language_switch("action_confirm", input$language)
    )

    #creating a named vector for the selection list to update language
    ch1 <- c("match", "completion")
    names(ch1) <- c(
      language_switch("select_monitor_table_match", input$language),
      language_switch("select_monitor_table_completion", input$language)
    )

    updateSelectInput(
      session = session,
      "monitor_table",
      language_switch("select_monitor_table", input$language),
      choices = ch1
    )

    #creating a named vector for the forms to update language
    ch2 <- c("global", "women", "reg")
    names(ch2) <- c(
      language_switch("select_form1_global", input$language),
      language_switch("select_form1_women", input$language),
      language_switch("select_form1_reg", input$language)
    )

    updateSelectInput(
      session = session,
      "form1",
      label = language_switch("select_form1", input$language),
      choices = ch2
    )

    #updateDownloadButton(session = session,"dl",language_switch("download_dl",input$language))
    #updateDownloadButton(session = session,"dlx",language_switch("download_dlx",input$language))

    updateSelectInput(
      session = session,
      "enums",
      language_switch("select_enums", input$language),
      choices = language_switch("select_enums_All", input$language)
    )
    updateDateRangeInput(
      session = session,
      "dates",
      language_switch("dateRange_dates", input$language)
    )

    ch3 <- 0
    names(ch3) <- language_switch("select_geo_var_None", input$language)

    updateSelectInput(
      session = session,
      "geo_var",
      language_switch("select_geo_var", input$language),
      choices = ch3
    )

    updateSelectInput(
      session = session,
      "inspect",
      language_switch("select_inspect", input$language),
      choices = ch3
    )

    updateSelectInput(
      session = session,
      "form_i",
      label = language_switch("select_form1", input$language),
      choices = ch2[-3]
    )
    updateSelectInput(
      session = session,
      "form2",
      label = language_switch("select_form1", input$language),
      choices = ch2
    )

    updateSelectInput(
      session = session,
      "question",
      language_switch("select_question", input$language),
      choices = "Error - Please Load Data"
    )

    indicator_directory$relevant <- indicator_directory[, project_code]

    indicator_directory <- indicator_directory %>%
      filter(relevant == 1)

    indicator_choices <- indicator_directory$indicator_name
    names(indicator_choices) <- indicator_directory[, input$language]

    updateSelectInput(
      session = session,
      "indicator",
      label = language_switch("select_indicator", input$language)
    )
    #  ch4 <- c(
    #    "raw",
    #    "processed"
    #  )
    #  names(ch4) <- c(language_switch("select_report_data_raw", input$language),
    #                  language_switch("select_report_data_processed", input$language))
    #
    #  updateSelectInput(session = session, "report_data", label = language_switch("select_report_data",
    #                                                                           input$language),
    #                    choices = ch4)
    #
    #  updateSelectInput(session = session, "indicatorData", label = language_switch("select_indicatorData",
    #                                                                              input$language),
    #                    choices = ch4)

    ch5 <- c(
      "global",
      "women",
      "crops",
      "off_farm",
      "hh_roster",
      "indicators"
    )

    print(project_code)
    if (
      !is.na(data_directory$livestock[data_directory$project == project_code])
    ) {
      ch5 <- c(ch5, "livestock")

      names(ch5) <- c(
        language_switch("select_cleandat_global", input$language),
        language_switch("select_cleandat_women", input$language),
        language_switch("select_cleandat_crops", input$language),
        language_switch("select_cleandat_off_farm", input$language),
        language_switch("select_cleandat_hh_roster", input$language),
        language_switch("select_cleandat_indicators", input$language),
        language_switch("select_cleandat_livestock", input$language)
      )
    } else {
      names(ch5) <- c(
        language_switch("select_cleandat_global", input$language),
        language_switch("select_cleandat_women", input$language),
        language_switch("select_cleandat_crops", input$language),
        language_switch("select_cleandat_off_farm", input$language),
        language_switch("select_cleandat_hh_roster", input$language),
        language_switch("select_cleandat_indicators", input$language)
      )
    }

    updateSelectInput(
      session = session,
      "cleandat",
      label = language_switch("select_cleandat", input$language),
      choices = ch5
    )

    # updateActionButton(session = session, "download_dl", label = language_switch("download_dl",
    #                                                                              input$language))

    updateSelectInput(
      session = session,
      "genBaiscReport",
      label = language_switch("action_genBasicReport", input$language)
    )
    updateSelectInput(
      session = session,
      "printBasicReport",
      label = language_switch("action_printBasicReport", input$language)
    )
    updateSelectInput(
      session = session,
      "printBasicReport2",
      label = language_switch("action_printBasicReport", input$language)
    )

    #indicator groupings
    indicator_groupings <- indicator_groupings %>%
      filter(country == project_code)

    ch_ig <- indicator_groupings$variable_name
    names(ch_ig) <- indicator_groupings[, input$language]

    updateSelectInput(
      session = session,
      "groupvar",
      label = language_switch("select_groupvar", input$language),
      choices = ch_ig
    )
  })

  #create an empty reactive oject to be filled with data

  observeEvent(input$language, {
    #create an empty reactive oject to be filled with messages

    messages$progress <- data_directory$Status[
      data_directory$project == project_code
    ]

    print(messages$progress)
    print(data_directory$Status[data_directory$project == project_code])
    print(project_code)
  })

  #if the load data button is pressed then update the message saying how long it takes for the data to load
  observeEvent(input$confirm, {
    withProgress(
      message = language_switch("output_data_message_loading", input$language),
      value = 0,
      {
        ruODK::ru_setup(
          url = Sys.getenv("server_url"),
          pid = Sys.getenv(paste(project_code, "project_id", sep = "_")),
          un = Sys.getenv("server_username"),
          pw = Sys.getenv("server_password")
        )

        if (messages$progress == 1) {
          #set up the ODK link with the details from encv file

          #try to get the 4 different types of form
          #try will catch the errors if no form matches (e.g/ if no reg form or enumerator form)
          data1$data0$full_form <- try(form_schema_ext(
            fid = Sys.getenv("form_id")
          ))
          incProgress(0.1)
          data1$data0$full_form_women <- try(form_schema_ext(
            fid = Sys.getenv("women_form_id")
          ))
          incProgress(0.1)
          data1$data0$full_form_registration <- try(form_schema_ext(
            fid = Sys.getenv("reg_form_id")
          ))
          incProgress(0.1)
          data1$data0$enum_form <- try(form_schema_ext(
            fid = Sys.getenv("rnum_form_id")
          ))

          #if there is no error then get the forms
          if (class(data1$data0$full_form)[1] != "try-error") {
            data1$data0$form <- data1$data0$full_form

            if (class(data1$data0$full_form_women)[1] != "try-error") {
              data1$data0$form_women <- data1$data0$full_form_women
            }
            if (class(data1$data0$full_form_registration)[1] != "try-error") {
              data1$data0$form_registration <- data1$data0$full_form_registration
            }
            incProgress(0.1)

            #flag for whether enumerator form is present or whether to look within form option list
            data1$data0$enum_flag <- FALSE

            if (
              class(data1$data0$enum_form)[1] != "try-error" &
                "Enumerator_List" %in% ruODK::entitylist_list()
            ) {
              data1$data0$enum_flag <- TRUE
            }

            #get language names
            data1$data0$languages <- str_remove_all(
              str_subset(colnames(data1$data0$full_form), "label_"),
              "label_"
            )
          } else {
            messages$data_message <- data1$data0$full_form[1]
          }

          #return messages if any are created

          observeEvent(messages$data1, {
            output$Message1 <- renderText(messages$data1)
          })

          observeEvent(messages$data2, {
            output$Message2 <- renderText(messages$data2)
          })
          observeEvent(messages$data3, {
            output$Message3 <- renderText(messages$data3)
          })

          observeEvent(messages$data_message, priority = 998, {
            output$data_message <- renderText(messages$data_message)
          })

          #add entity list to the data object
          data1$data0$entities <- ruODK::entity_list(did = "Farm_Summary") %>%
            mutate(uuid = paste("uuid", uuid, sep = ":"))
          incProgress(0.1)

          #use read_clean_data function to apply appropriate classes to each variable and inherit the labels from chosen language
          #will create list including data and form and labelled list of questions
          data1$data0$global <- read_clean_data(
            fid = Sys.getenv("form_id"),
            language = input$language,
            full_form = form_schema_ext(fid = Sys.getenv("form_id"))
          )

          #create message saying either fail or how many records in data
          messages$data1 <- ifelse(
            class(data1$data0$global$full_data)[1] != "try-error",
            paste(
              language_switch("message1_data_success", input$language),
              nrow(data1$data0$global$full_data),
              language_switch("message1_data_records", input$language)
            ),
            language_switch("message1_data_failure", input$language)
          )

          #repeat for womens data

          data1$data0$women <- read_clean_data(
            fid = Sys.getenv("women_form_id"),
            language = input$language,
            full_form = form_schema_ext(fid = Sys.getenv("women_form_id"))
          )

          messages$data2 <- ifelse(
            class(data1$data0$women$full_data)[1] != "try-error",
            paste(
              language_switch("message2_data_success", input$language),
              nrow(data1$data0$women$full_data),
              language_switch("message1_data_records", input$language)
            ),
            language_switch("message2_data_failure", input$language)
          )

          #indicator groupings
          indicator_groupings <- indicator_groupings %>%
            filter(country == project_code)

          ch_ig <- indicator_groupings$variable_name
          names(ch_ig) <- indicator_groupings[, input$language]

          updateSelectInput(
            session = session,
            "groupvar",
            label = language_switch("select_groupvar", input$language),
            choices = ch_ig
          )

          data1$data0$global$full_data$partner <- names(proj_list)[
            proj_list == project_code
          ]

          if ("livestock_owners" %in% data1$data0$global$full_form$name) {
            data1$data0$indicators <- derive_indicators(
              global = data1$data0$global$full_data,
              women = data1$data0$women$full_data,
              crops = data1$data0$global$repeats$survey_grp_section_crop_productivity_crop_repeat,
              livestock = data1$data0$global$repeats$survey_grp_section_livestock_livestock_repeat,
              off_farm = data1$data0$global$repeats$survey_grp_offfarm_income_grp_section_off_farm_income_offfarm_income_repeat,
              global_form = data1$data0$global$full_form,
              women_form = data1$data0$women$full_form,
              translations = report_translations,
              language = input$language,
              extra_vars = indicator_groupings$variable_name
            )
          } else {
            data1$data0$indicators <- derive_indicators(
              global = data1$data0$global$full_data,
              women = data1$data0$women$full_data,
              crops = data1$data0$global$repeats$survey_grp_section_crop_productivity_crop_repeat,
              off_farm = data1$data0$global$repeats$survey_grp_offfarm_income_grp_section_off_farm_income_offfarm_income_repeat,
              global_form = data1$data0$global$full_form,
              women_form = data1$data0$women$full_form,
              translations = report_translations,
              language = input$language,
              extra_vars = indicator_groupings$variable_name
            )
          }

          #repeat for reg data
          data1$data0$reg <- read_clean_data(
            fid = Sys.getenv("reg_form_id"),
            language = input$language,
            full_form = form_schema_ext(fid = Sys.getenv("reg_form_id"))
          )

          messages$data3 <- ifelse(
            class(data1$data0$reg$full_data)[1] != "try-error",
            paste(
              language_switch("message3_data_success", input$language),
              nrow(data1$data0$reg$full_data),
              language_switch("message1_data_records", input$language)
            ),
            language_switch("message3_data_failure", input$language)
          )
          incProgress(0.1)
          #if there is an enumerator entity - merge this in
          if (data1$data0$enum_flag == TRUE) {
            data1$data0$enumerators <- ruODK::entity_list(
              did = "Enumerator_List"
            ) %>%
              select(
                section_meta_interviewername = uuid,
                interviewername = current_version_label
              )

            data1$data0$reg$data <- data1$data0$reg$data %>%
              left_join(data1$data0$enumerators)

            #only merge with reg data if reg data also exists
            if (nrow(data1$data0$reg$data) > 0) {
              data1$data0$reg$data <- data1$data0$reg$data %>%
                rename(
                  "section_meta_interviewerid" = section_meta_interviewername,
                  "section_meta_interviewername" = interviewername
                ) %>%
                select(
                  section_meta_interviewername,
                  colnames(data1$data0$reg$data)[
                    colnames(data1$data0$reg$data) !=
                      "section_meta_interviewername"
                  ],
                  section_meta_interviewerid
                )
            }
            #merge enumerators into womens data
            data1$data0$women$data <- data1$data0$women$data %>%
              left_join(data1$data0$enumerators) %>%
              rename(
                "section_meta_interviewerid" = section_meta_interviewername,
                "section_meta_interviewername" = interviewername
              ) %>%
              select(
                section_meta_interviewername,
                colnames(data1$data0$women$data)[
                  colnames(data1$data0$women$data) !=
                    "section_meta_interviewername"
                ],
                section_meta_interviewerid
              )

            #merge enumerators into global data
            data1$data0$global$data <- data1$data0$global$data %>%
              left_join(data1$data0$enumerators) %>%
              rename(
                "section_meta_interviewerid" = section_meta_interviewername,
                "section_meta_interviewername" = interviewername
              ) %>%
              select(
                section_meta_interviewername,
                colnames(data1$data0$global$data)[
                  colnames(data1$data0$global$data) !=
                    "section_meta_interviewername"
                ],
                section_meta_interviewerid
              )

            #update enumerator selection menu
            updateSelectInput(
              session = session,
              "enums",
              choices = data1$data0$enumerators$interviewername,
              selected = data1$data0$enumerators$interviewername
            )
          } else {
            #if no enumerator entity
            #get list of enumerators from within form selections
            data1$data0$enumerators <- unique(c(
              unique(data1$data0$reg$data$section_meta_interviewername),
              unique(as.character(
                data1$data0$women$data$section_meta_interviewername
              )),
              unique(as.character(
                data1$data0$global$data$section_meta_interviewername
              ))
            ))

            updateSelectInput(
              session = session,
              "enums",
              choices = data1$data0$enumerators,
              selected = data1$data0$enumerators
            )
          }

          incProgress(0.1)

          #to give consistency between different modules - if a form is selected in the
          #data menu, this will be the form on screen in the summarise menu later. and
          #vice versa

          #create a reactive object to confirm whether the data is loaded into the environment
          output$data_in <- reactive({
            z <- FALSE
            if (class(data1$data0[[input$form1]]$full_data)[1] != "try-error") {
              if (
                nrow(data1$data0$global$full_data) > 0 |
                  nrow(data1$data0$women$full_data) > 0 |
                  nrow(data1$data0$reg$full_data) > 0
              ) {
                z <- TRUE
              }
            }
            z
          })
          outputOptions(output, "data_in", suspendWhenHidden = FALSE)

          #identify the potential coordinate fields from the forms

          data1$data0$geopoints <- filter(
            data1$data0$global$full_form,
            type == "geopoint"
          ) %>%
            mutate(data.frame(data = "global")) %>%
            full_join(
              filter(data1$data0$reg$full_form, type == "geopoint") %>%
                mutate(data = "reg")
            ) %>%
            full_join(
              filter(data1$data0$women$full_form, type == "geopoint") %>%
                mutate(data.frame(data = "women"))
            )

          if (nrow(data1$data0$geopoints) > 0) {
            updateSelectInput(
              session = session,
              "geo_var",
              choices = c(paste(
                data1$data0$geopoints$data,
                data1$data0$geopoints$ruodk_name,
                sep = ": "
              )),
              selected = paste(
                data1$data0$geopoints$data,
                data1$data0$geopoints$ruodk_name,
                sep = ": "
              )[1]
            )
          } else {
            updateSelectInput(session = session, "geo_var", choices = "None")
          }

          #remove metadata flag from colnames (can't remember why this is needed?)
          colnames(data1$data0$reg$data) <- str_remove_all(
            colnames(data1$data0$reg$data),
            "_metadata"
          )
          colnames(data1$data0$women$data) <- str_remove_all(
            colnames(data1$data0$women$data),
            "_metadata"
          )
          colnames(data1$data0$global$data) <- str_remove_all(
            colnames(data1$data0$global$data),
            "_metadata"
          )

          incProgress(0.1)

          #get the metadata fields from all of the datasets and make them consistent across the different form types

          if (nrow(data1$data0$reg$data) > 0) {
            data1$data0$reg_id <- data1$data0$reg$data %>%
              select(
                submission = system_submission_date,
                interview = section_meta_interview_date,
                interviewer = section_meta_interviewername,
                entity = meta_entity_label,
                instance = meta_instance_id
              ) %>%
              mutate(meta_id = paste0("uuid", ":", instance))

            data1$data0$reg_n <- data1$data0$reg_id %>%
              group_by(entity) %>%
              summarise(
                registrations = n(),
                reg_date = paste(unique(submission), collapse = "; ")
              )
          } else {
            data1$data0$reg_id <- data.frame()

            data1$data0$reg_n <- data.frame(
              entity = "",
              registrations = 0,
              reg_date = NA
            )
          }

          data1$data0$women_id <- data1$data0$women$data %>%
            select(
              submission = system_submission_date,
              interview = section_meta_interview_date,
              interviewer = section_meta_interviewername,
              entity = meta_instance_name,
              instance = meta_instance_id,
              start = section_meta_start_time_user,
              end = survey_grp_section_enumerator_feedback2_end_time_user,
              section_meta_location2_id
            ) %>%
            mutate(meta_id = paste0("uuid", ":", section_meta_location2_id))

          data1$data0$global_id <- data1$data0$global$data %>%
            select(
              submission = system_submission_date,
              interview = section_meta_interview_date,
              interviewer = section_meta_interviewername,
              entity = meta_instance_name,
              instance = meta_instance_id,
              start = section_meta_start_time_user,
              end = survey_grp_section_enumerator_feedback2_end_time_user,
              section_meta_location2_id
            ) %>%
            mutate(meta_id = paste0("uuid", ":", section_meta_location2_id))

          #produce summaries of any duplocates

          data1$data0$women_n <- data1$data0$women_id %>%
            group_by(meta_id) %>%
            summarise(
              womens_form = n(),
              womens_date = paste(unique(interview), collapse = "; ")
            )

          data1$data0$global_n <- data1$data0$global_id %>%
            group_by(meta_id) %>%
            summarise(
              global = n(),
              global_date = paste(unique(interview), collapse = "; ")
            )

          incProgress(0.1)

          #create labels for the different scenarios possible for duplication
          data1$data0$status <- data1$data0$entities %>%
            left_join(
              data1$data0$reg_n,
              by = c("current_version_label" = "entity")
            ) %>%
            left_join(data1$data0$women_n, by = c("uuid" = "meta_id")) %>%
            left_join(data1$data0$global_n, by = c("uuid" = "meta_id")) %>%
            #group_by(uuid,current_version_label,womens_form,global,womens_date,global_date) %>%
            #summarise(n=n()) %>%
            mutate(complete_forms = 2 - is.na(womens_form) - is.na(global)) %>%
            mutate(
              Status = case_when(
                womens_form == 1 & global == 1 ~ language_switch(
                  "Status_1",
                  input$language
                ),
                (womens_form > 1 | global > 1) &
                  complete_forms == 2 ~ language_switch(
                  "Status_2",
                  input$language
                ),
                (womens_form > 1 | global > 1) &
                  (complete_forms < 2) ~ language_switch(
                  "Status_4",
                  input$language
                ),
                is.na(womens_form) & is.na(global) ~ language_switch(
                  "Status_5",
                  input$language
                ),
                .default = language_switch("Status_3", input$language)
              )
            ) %>%
            mutate(
              Status = factor(
                Status,
                levels = c(
                  language_switch("Status_1", input$language),
                  language_switch("Status_2", input$language),
                  language_switch("Status_3", input$language),
                  language_switch("Status_4", input$language),
                  language_switch("Status_5", input$language)
                )
              )
            )

          incProgress(0.05)

          #merge the labels into the datasets

          data1$data0$women$data <- left_join(
            (data1$data0$women$data %>%
              mutate(uuid = paste0("uuid", ":", section_meta_location2_id))),
            select(data1$data0$status, uuid, Status)
          )

          data1$data0$global$data <- left_join(
            (data1$data0$global$data %>%
              mutate(uuid = paste0("uuid", ":", section_meta_location2_id))),
            select(data1$data0$status, uuid, Status)
          )

          if (nrow(data1$data0$reg$data) > 0) {
            data1$data0$reg$data <- left_join(
              data1$data0$reg$data,
              select(
                data1$data0$status,
                meta_entity_label = current_version_label,
                Status
              )
            )
          }

          #update the list of respondents based on their status

          updateSelectInput(
            session = session,
            "inspect",
            choices = split(
              data1$data0$status$current_version_label,
              data1$data0$status$Status
            )
          )
          data1$data0$flag <- TRUE
          messages$data_message <- language_switch(
            "output_data_message_success",
            input$language
          )

          saveRDS(
            data1$data0,
            file = paste(
              "data_objects/",
              project_code,
              "_",
              input$language,
              ".RDS",
              sep = ""
            )
          )

          incProgress(0.05)

          print(data1$data0$global$data)
        }

        if (messages$progress == 2) {
          print(messages$progress)
          data1$data0 <- readRDS(
            file = paste(
              "data_objects/",
              project_code,
              "_",
              input$language,
              ".RDS",
              sep = ""
            )
          )

          #indicator groupings
          indicator_groupings <- indicator_groupings %>%
            filter(country == project_code)

          ch_ig <- indicator_groupings$variable_name
          names(ch_ig) <- indicator_groupings[, input$language]

          updateSelectInput(
            session = session,
            "groupvar",
            label = language_switch("select_groupvar", input$language),
            choices = ch_ig
          )

          updateSelectInput(
            session = session,
            inputId = "question",
            choices = data1$data0[[input$form2]]$questionnairelist
          )

          if (data1$data0$enum_flag == TRUE) {
            #update enumerator selection menu
            updateSelectInput(
              session = session,
              "enums",
              choices = data1$data0$enumerators$interviewername,
              selected = data1$data0$enumerators$interviewername
            )
          } else {
            updateSelectInput(
              session = session,
              "enums",
              choices = data1$data0$enumerators,
              selected = data1$data0$enumerators
            )
          }

          output$data_in <- reactive({
            z <- FALSE
            if (class(data1$data0[[input$form1]]$full_data)[1] != "try-error") {
              if (
                nrow(data1$data0$global$full_data) > 0 |
                  nrow(data1$data0$women$full_data) > 0 |
                  nrow(data1$data0$reg$full_data) > 0
              ) {
                print("z=true")
                z <- TRUE
              }
            }
            z
          })
          outputOptions(output, "data_in", suspendWhenHidden = FALSE)

          if (nrow(data1$data0$geopoints) > 0) {
            updateSelectInput(
              session = session,
              "geo_var",
              choices = c(paste(
                data1$data0$geopoints$data,
                data1$data0$geopoints$ruodk_name,
                sep = ": "
              )),
              selected = paste(
                data1$data0$geopoints$data,
                data1$data0$geopoints$ruodk_name,
                sep = ": "
              )[1]
            )
          } else {
            updateSelectInput(session = session, "geo_var", choices = "None")
          }

          incProgress(0.05)
          updateSelectInput(
            session = session,
            "inspect",
            choices = split(
              data1$data0$status$current_version_label,
              data1$data0$status$Status
            )
          )

          incProgress(0.05)
          #data_processed <-NULL

          #global data
          data_processed$global <- read.csv(data_directory$global[
            data_directory$project == project_code
          ]) %>%
            mutate_all(function(x) ifelse(x == '', NA, x))

          print(data_directory$global)

          data_processed$global <- merge_labels_clean(
            data_processed$global,
            data1$data0$global$full_form,
            input$language
          )

          #Women data
          data_processed$women <- read.csv(data_directory$womens[
            data_directory$project == project_code
          ]) %>%
            mutate_all(function(x) ifelse(x == '', NA, x))

          wom_assets <- data_processed$women %>%
            select(household_id, ends_with("own"))
          data_processed$women <- merge_labels_clean(
            data_processed$women,
            data1$data0$women$full_form,
            input$language
          )
          data_processed$women <- data_processed$women %>%
            select(-ends_with("own")) %>%
            left_join(wom_assets)
          incProgress(0.05)
          data_processed$indicators <- read.csv(data_directory$indicators[
            data_directory$project == project_code
          ]) %>%
            mutate_all(function(x) ifelse(x == '', NA, x))
          data_processed$indicators$partner <- names(proj_list)[
            proj_list == project_code
          ]

          #Crops Data
          data_processed$crops <- read.csv(data_directory$crops[
            data_directory$project == project_code
          ]) %>%
            mutate_all(function(x) ifelse(x == '', NA, x))

          data_processed$crops$crop_consumed_prop[
            (is.na(data_processed$crops$crop_consumed_prop) |
              data_processed$crops$crop_consumed_prop == "") &
              data_processed$crops$crop_use == "eat"
          ] <- "All"
          data_processed$crops$crop_consumed_prop[
            (is.na(data_processed$crops$crop_consumed_prop) |
              data_processed$crops$crop_consumed_prop == "")
          ] <- "None"

          data_processed$crops$crop_sold_prop[
            (is.na(data_processed$crops$crop_sold_prop) |
              data_processed$crops$crop_sold_prop == "") &
              data_processed$crops$crop_use == "sell"
          ] <- "All"
          data_processed$crops$crop_sold_prop[
            (is.na(data_processed$crops$crop_sold_prop) |
              data_processed$crops$crop_sold_prop == "")
          ] <- "None"

          data_processed$crops <- merge_labels_clean(
            data_processed$crops,
            data1$data0$global$full_form,
            input$language
          )
          data_processed$crops <- merge_crops_label(
            data_processed$crops,
            data1$data0$global$full_form,
            input$language
          )
          incProgress(0.05)
          #Off farm
          data_processed$off_farm <- read.csv(data_directory$off_farm[
            data_directory$project == project_code
          ]) %>%
            mutate_all(function(x) ifelse(x == '', NA, x))

          if (
            !is.na(data_directory$livestock[
              data_directory$project == project_code
            ])
          ) {
            data_processed$livestock <- read.csv(data_directory$livestock[
              data_directory$project == project_code
            ]) %>%
              mutate_all(function(x) ifelse(x == '', NA, x))

            data_processed$livestock <- merge_labels_clean(
              data_processed$livestock,
              data1$data0$global$full_form,
              input$language
            )
            data_processed$livestock <- merge_livestock_label(
              data_processed$livestock,
              data1$data0$global$full_form,
              input$language
            )
          }

          data_processed$hh_roster <- read.csv(data_directory$hh_roster[
            data_directory$project == project_code
          ]) %>%
            mutate_all(function(x) ifelse(x == '', NA, x))

          #translate perception
          perception_labels <- report_translations %>%
            filter(indicator == "I_HI_self_perception")
          incProgress(0.05)
          data_processed$indicators <- data_processed$indicators %>%
            mutate(
              I_HI_self_perception = factor(
                I_HI_self_perception,
                levels = c(7, 6, 5, 4, 3, 2, 1),
                labels = perception_labels[1:7, input$language]
              )
            )

          #translate FIES category
          fies_cat_labs <- report_translations %>%
            filter(indicator == "I_FIES_category" & type == "label")

          data_processed$indicators$I_FIES_category <- factor(
            data_processed$indicators$I_FIES_category,
            levels = c(
              "Food Secure",
              "Mildly Food Insecure",
              "Moderately Food Insecure",
              "Severely Food Insecure"
            ),
            labels = fies_cat_labs[, input$language]
          )
          incProgress(0.05)
          #translate IVCC
          xx <- colnames(
            data_processed$indicators %>% select(starts_with("I_VCC"))
          )
          yy <- c(
            "voice_hh_food",
            "voice_hh_crops",
            "voice_hh_spending",
            "choice_hh_income_women",
            "choice_comm_market",
            "choice_comm_committee"
          )

          for (i in 1:6) {
            data_processed$indicators <- merge_labels_clean_indicator(
              variable = yy[i],
              indicator = xx[i],
              form = data1$data0$women$full_form,
              data = data_processed$indicators,
              language = input$language
            )
          }

          ch5 <- c(
            "global",
            "women",
            "crops",
            "off_farm",
            "hh_roster",
            "indicators"
          )

          if (
            !is.na(data_directory$livestock[
              data_directory$project == project_code
            ])
          ) {
            ch5 <- c(ch5, "livestock")

            names(ch5) <- c(
              language_switch("select_cleandat_global", input$language),
              language_switch("select_cleandat_women", input$language),
              language_switch("select_cleandat_crops", input$language),
              language_switch("select_cleandat_off_farm", input$language),
              language_switch("select_cleandat_hh_roster", input$language),
              language_switch("select_cleandat_indicators", input$language),
              language_switch("select_cleandat_livestock", input$language)
            )
          } else {
            names(ch5) <- c(
              language_switch("select_cleandat_global", input$language),
              language_switch("select_cleandat_women", input$language),
              language_switch("select_cleandat_crops", input$language),
              language_switch("select_cleandat_off_farm", input$language),
              language_switch("select_cleandat_hh_roster", input$language),
              language_switch("select_cleandat_indicators", input$language)
            )
          }
          updateSelectInput(
            session = session,
            "cleandat",
            label = language_switch("select_cleandat", input$language),
            choices = ch5
          )
        }

        #indicator choices
        indicator_directory$relevant <- indicator_directory[, project_code]

        indicator_directory <- indicator_directory %>%
          filter(relevant == 1)

        indicator_choices <- indicator_directory$indicator_name
        names(indicator_choices) <- indicator_directory[, input$language]

        updateSelectInput(
          session = session,
          "indicator",
          label = language_switch("select_indicator", input$language),
          choices = indicator_choices
        )
        #update question list

        output$report <- reactive({
          file.exists(file.path(
            paste0("output/reports"),
            paste(project_code, input$language, "report.pdf", sep = "_")
          )) &
            messages$loading == 0
        })

        outputOptions(
          output,
          "report",
          suspendWhenHidden = FALSE,
          priority = 10
        )

        rep_names <- c(
          language_switch("genReport", input$language),
          language_switch("prevReport", input$language)
        )
        rep_choices <- c(0, 1)
        names(rep_choices) <- rep_names

        if (
          file.exists(file.path(
            paste0("output/reports"),
            paste(project_code, input$language, "report.pdf", sep = "_")
          ))
        ) {
          updateSelectInput(
            session = session,
            "regen",
            label = "",
            choices = rep_choices[c(2, 1)]
          )
        } else {
          updateSelectInput(
            session = session,
            "regen",
            label = "",
            choices = rep_choices[1]
          )
        }
      }
    )

    #end of data loading
  })

  #render data table for main survey

  observeEvent(input$form1, {
    if (input$form1 != input$form2) {
      updateSelectInput(
        session = session,
        inputId = "form2",
        selected = input$form1
      )
    }
  })
  observeEvent(input$form2, {
    if (input$form1 != input$form2) {
      updateSelectInput(
        session = session,
        inputId = "form1",
        selected = input$form2
      )
    }
    updateSelectInput(
      session = session,
      inputId = "question",
      choices = data1$data0[[input$form2]]$questionnairelist
    )
  })

  output$data1 <- DT::renderDT({
    if (data1$data0$flag == TRUE) {
      data1$data0[[input$form1]]$data
    }
  })

  output$data_clean <- DT::renderDT({
    data_processed[[input$cleandat]]
  })

  output$data_indicators <- DT::renderDT({
    if (data_directory$Status[data_directory$project == project_code] == 1) {
      data1$data0$indicators
    } else if (
      data_directory$Status[data_directory$project == project_code] == 2
    ) {
      data_processed$indicators
    }
  })

  output$ProcessedTable <- render_gt({
    print(input$language)
    print(c(
      nrow(data_processed$global),
      nrow(data_processed$women),
      sum(
        data_processed$global$household_id %in%
          data_processed$women$household_id
      ),
      sum(
        !data_processed$global$household_id %in%
          data_processed$women$household_id
      ),
      sum(
        !data_processed$women$household_id %in%
          data_processed$global$household_id
      )
    ))

    print(report_translations[
      report_translations$type == "label" &
        report_translations$indicator == "Survey Summary",
      input$language
    ])
    data.frame(
      Form = report_translations[
        report_translations$type == "label" &
          report_translations$indicator == "Survey Summary",
        input$language
      ],
      N = c(
        nrow(data_processed$global),
        nrow(data_processed$women),
        sum(
          data_processed$global$household_id %in%
            data_processed$women$household_id
        ),
        sum(
          !data_processed$global$household_id %in%
            data_processed$women$household_id
        ),
        sum(
          !data_processed$women$household_id %in%
            data_processed$global$household_id
        )
      )
    ) %>%
      gt() %>%
      cols_label(
        Form = report_translations[
          report_translations$english == "Form",
          input$language
        ]
      ) %>%
      gt::tab_header(title = "Processed data summary")
  })

  #download the datasets
  output$dl <- downloadHandler(
    filename = function() {
      # Use the selected dataset as the suggested file name
      paste0(
        input$forms,
        input$form1,
        data1$data0[[input$form1]]$selected_language,
        ".csv"
      )
    },
    content = function(file) {
      # Write the dataset to the `file` that will be downloaded
      write.csv(data1$data0[[input$form1]]$data, file, row.names = FALSE)
    }
  )

  output$dl_processed <- downloadHandler(
    filename = function() {
      # Use the selected dataset as the suggested file name
      paste0(input$cleandat, "_", project_code, ".csv")
    },
    content = function(file) {
      # Write the dataset to the `file` that will be downloaded
      write.csv(data_processed[[input$cleandat]], file, row.names = FALSE)
    }
  )

  output$dl_indicators <- downloadHandler(
    filename = function() {
      # Use the selected dataset as the suggested file name
      paste0("Indicators_", project_code, ".csv")
    },
    content = function(file) {
      # Write the dataset to the `file` that will be downloaded
      # if(input$indicatorData=="raw"){
      # write.csv(data1$data0$indicators, file)
      # }else{
      write.csv(data_processed$indicators, file, row.names = FALSE)
      #}
    }
  )

  output$dlx_processed <- downloadHandler(
    filename = function() {
      # Use the selected dataset as the suggested file name
      paste0(project_code, "_processed_data.xlsx")
    },
    content = function(file) {
      # Write the dataset to the `file` that will be downloaded
      # out_xl <- list()
      #
      # for(i in 1:length(data_processed)){
      #
      #   out_xl[[names(data_processed)[i]]] <- data_processed[[i]]
      #
      # }

      write.xlsx(reactiveValuesToList(data_processed), file)
    }
  )

  output$dlx <- downloadHandler(
    filename = function() {
      # Use the selected dataset as the suggested file name
      paste0(
        input$forms,
        input$form1,
        data1$data0[[input$form1]]$selected_language,
        ".xlsx"
      )
    },
    content = function(file) {
      # Write the dataset to the `file` that will be downloaded

      out_xl <- list()
      out_xl[["main_data"]] <- data1$data0[[input$form1]]$data

      if (length(data1$data0[[input$form1]]$repeats) > 0) {
        for (i in 1:length(data1$data0[[input$form1]]$repeats)) {
          out_xl[[substr(
            names(data1$data0[[input$form1]]$repeats)[i],
            1,
            30
          )]] <- data1$data0[[input$form1]]$repeats[[i]]
        }
      }

      write.xlsx(out_xl, file)
    }
  )

  output$dlz <- downloadHandler(
    filename = function() {
      # Use the selected dataset as the suggested file name
      paste0(input$forms, input$form1, "zip")
    },
    content = function(file) {
      # Write the dataset to the `file` that will be downloaded

      dir.create(tmp <- tempfile())
      dir.create(file.path(tmp, "mydir"))

      out_xl <- list()
      out_xl[["main_data"]] <- data1$data0[["global"]]$data

      for (i in 1:length(data1$data0[["global"]]$repeats)) {
        out_xl[[substr(
          names(data1$data0[["global"]]$repeats)[i],
          1,
          30
        )]] <- data1$data0[["global"]]$repeats[[i]]
      }

      write.xlsx(
        out_xl,
        paste0(
          tmp,
          "/mydir/",
          input$forms,
          "global",
          data1$data0[["global"]]$selected_language,
          ".xlsx"
        )
      )

      out_xl2 <- list()
      out_xl2[["main_data"]] <- data1$data0[["women"]]$data

      for (i in 1:length(data1$data0[["women"]]$repeats)) {
        out_xl2[[substr(
          names(data1$data0[["women"]]$repeats)[i],
          1,
          30
        )]] <- data1$data0[["women"]]$repeats[[i]]
      }

      write.xlsx(
        out_xl2,
        paste0(
          tmp,
          "/mydir/",
          input$forms,
          "women",
          data1$data0[["women"]]$selected_language,
          ".xlsx"
        )
      )

      out_xl3 <- list()
      out_xl3[["main_data"]] <- data1$data0[["reg"]]$data

      for (i in 1:length(data1$data0[["reg"]]$repeats)) {
        out_xl3[[substr(
          names(data1$data0[["reg"]]$repeats)[i],
          1,
          30
        )]] <- data1$data0[["reg"]]$repeats[[i]]
      }

      write.xlsx(
        out_xl3,
        paste0(
          tmp,
          "/mydir/",
          input$forms,
          "reg",
          data1$data0[["reg"]]$selected_language,
          ".xlsx"
        )
      )

      zip(file, "mydir", root = tmp)
      file.remove(paste(tmp, "/mydir"))
    }
  )

  #output plot showing completion over time
  output$Dates <- renderPlotly({
    rbind(
      (data1$data0$women$data %>%
        select(date = section_meta_interview_date) %>%
        mutate(form = "Women's Form")),
      (data1$data0$global$data %>%
        select(date = section_meta_interview_date) %>%
        mutate(form = "Global Form"))
    ) -> data1

    pd <- data1 %>%
      mutate(date = format.Date(date, format = "%b %d")) %>%
      ggplot(aes(x = date, fill = form)) +
      geom_bar(position = "dodge2", col = "black") +
      scale_fill_manual(values = groundswell_palette) +
      labs(x = "Interview Date", title = "Interviews by Date") +
      theme_light()

    pd %>%
      ggplotly()
  })

  #update maps based on changes to selection of geo variable
  observeEvent(input$geo_var, {
    #create map in leaflet and plot points as selected
    output$MonitorMap <- renderLeaflet({
      if (input$geo_var != "0") {
        geo_var <- str_split_fixed(input$geo_var, ": ", 2)

        geo_data <- data1$data0[[geo_var[1]]]$data %>%
          select(all_of(c(
            "id",
            "Status",
            "system_submission_date",
            geo_var[2]
          ))) %>%
          na.omit() %>%
          st_as_sf(wkt = geo_var[2])

        pal <- colorFactor(
          palette = "Dark2",
          domain = c(
            language_switch("Status_1", input$language),
            language_switch("Status_2", input$language),
            language_switch("Status_3", input$language),
            language_switch("Status_4", input$language),
            language_switch("Status_5", input$language)
          )
        )

        leaflet(geo_data) %>%
          addProviderTiles(
            "OpenStreetMap.Mapnik",
            group = "OSM (default)",
            options = providerTileOptions(opacity = 0.5)
          ) %>%
          addProviderTiles(
            "OpenTopoMap",
            group = "Topography",
            options = providerTileOptions(opacity = 0.5)
          ) %>%
          addProviderTiles(
            "Esri.WorldImagery",
            group = "World Imagery (satellite)",
            options = providerTileOptions(opacity = 0.5)
          ) %>%
          # Layers control
          addLayersControl(
            baseGroups = c(
              "OSM (default)",
              "Topography",
              "World Imagery (satellite)"
            ),
            options = layersControlOptions(collapsed = FALSE)
          ) %>%
          addCircles(
            popup = ~ paste(
              Status,
              id,
              as.character(system_submission_date),
              sep = "\n"
            ),
            color = ~ pal(Status),
            opacity = 0.75
            #,clusterOptions = circleClusterOptions()
          ) %>%
          addLegend(
            pal = pal,
            values = c(
              language_switch("Status_1", input$language),
              language_switch("Status_2", input$language),
              language_switch("Status_3", input$language),
              language_switch("Status_4", input$language),
              language_switch("Status_5", input$language)
            ),
            group = "circles",
            position = "bottomleft"
          )
      }
    })
  })

  #create a table summarising the completion of the forms

  output$inspect_summary <- render_gt({
    f1 <- filter(data1$data0$status, current_version_label == input$inspect) %>%
      ungroup() %>%
      select(
        current_version_label,
        Status,
        complete_forms,
        "Global Forms" = global,
        "Global Date(s): " = global_date,
        "Women's Forms" = womens_form,
        "Women' Date(s): " = womens_date
      )
    colnames(f1)[4:7] <- c(
      language_switch("select_form1_global", input$language),
      language_switch("label_dates", input$language),
      language_switch("select_form1_women", input$language),
      paste0(language_switch("label_dates", input$language), " ")
    )
    f1
  })

  #create a table with duplicated entries side by side for comparison
  output$inspect_i <- DT::renderDataTable(
    {
      resps <- filter(
        data1$data0[[input$form_i]]$data,
        uuid %in%
          filter(
            data1$data0$status,
            current_version_label == input$inspect
          )$uuid
      )

      if (nrow(resps) > 0) {
        l1 <- language_switch("label_Response", input$language)
        resps %>%
          t() %>%
          as.data.frame() %>%
          rownames_to_column(var = "ruodk_name") %>%
          inner_join(select(
            data1$data0[[input$form_i]]$form,
            ruodk_name,
            any_of(paste0("label_", input$language))
          )) %>%
          select(
            any_of(paste0("label_", input$language)),
            any_of(paste0("V", 1:9))
          ) %>%
          return()
      } else {
        data.frame(Response = NULL) %>% return()
      }
    },
    options = list(filter = "top", pagelength = 150)
  )

  #mointoring table at either form level or respondent level
  output$MonitorTable <- render_gt({
    if (data1$data0$flag == TRUE) {
      if (input$monitor_table == "match") {
        data1$data0$status %>%
          mutate(Total = n()) %>%
          group_by(Status) %>%
          summarise("Number of Responses" = paste0(n(), "/", mean(Total))) %>%
          ungroup() -> x
        colnames(x)[2] <- language_switch("label_n_response", input$language)

        gt(x) -> x
      }
      if (input$monitor_table == "completion") {
        #respondne tlevel summary
        if (nrow(data1$data0$reg$data) > 0) {
          reg_sum <- data1$data0$reg$data %>%
            select(
              submission = system_submission_date,
              interview = section_meta_interview_date,
              interviewer = section_meta_interviewername,
              entity = meta_entity_label,
              instance = meta_instance_id
            ) %>%
            summarise(
              Form = language_switch("select_form1_reg", input$language),
              "Submissions" = n(),
              "Start Date" = min(date(interview), na.rm = T),
              "End Date" = max(date(interview), na.rm = T),
              "Average Time" = NA,
              "Interviews < 10 minutes" = NA
            )
        } else {
          #form level summary
          reg_sum <-
            data.frame(
              Form = language_switch("select_form1_reg", input$language),
              "Submissions" = 0,
              "Start Date" = NA,
              "End Date" = NA,
              "Average Time" = NA,
              "Interviews < 10 minutes" = NA
            )
        }

        women_sum <- data1$data0$women$data %>%
          select(
            submission = system_submission_date,
            interview = section_meta_interview_date,
            interviewer = section_meta_interviewername,
            entity = meta_instance_name,
            instance = meta_instance_id,
            start = section_meta_start_time_user,
            end = survey_grp_section_enumerator_feedback2_end_time_user,
            section_meta_location2_id
          ) %>%
          summarise(
            Form = language_switch("select_form1_women", input$language),
            "Submissions" = n(),
            "Start Date" = min(date(interview), na.rm = T),
            "End Date" = max(date(interview), na.rm = T),
            "Average Time" = round(
              median((as.numeric(end) - as.numeric(start)) / 60, na.rm = T),
              1
            ),
            "Interviews < 10 minutes" = scales::percent(mean(
              ((as.numeric(end) - as.numeric(start))) < (60 * 10),
              na.rm = T
            ))
          )

        global_sum <- data1$data0$global$data %>%
          select(
            submission = system_submission_date,
            interview = section_meta_interview_date,
            interviewer = section_meta_interviewername,
            entity = meta_instance_name,
            instance = meta_instance_id,
            start = section_meta_start_time_user,
            end = survey_grp_section_enumerator_feedback2_end_time_user,
            section_meta_location2_id
          ) %>%
          summarise(
            Form = language_switch("select_form1_global", input$language),
            "Submissions" = n(),
            "Start Date" = min(date(interview), na.rm = T),
            "End Date" = max(date(interview), na.rm = T),
            "Average Time" = round(
              median((as.numeric(end) - as.numeric(start)) / 60, na.rm = T),
              1
            ),
            "Interviews < 10 minutes" = scales::percent(mean(
              ((as.numeric(end) - as.numeric(start))) < (60 * 10),
              na.rm = T
            ))
          )

        colnames(reg_sum) <- colnames(global_sum) <- colnames(women_sum) <- c(
          "Form",
          language_switch("label_submissions", input$language),
          language_switch("label_start", input$language),
          language_switch("label_end", input$language),
          language_switch("label_time", input$language),
          language_switch("label_interview10", input$language)
        )

        rbind(reg_sum, global_sum, women_sum) %>%
          gt() -> x
      }
      return(x)
    }
  })

  #produce table with enumerator summary statistics

  output$enum_summary <- renderDataTable({
    if (data1$data0$flag == TRUE) {
      women_sum <- data1$data0$women$data %>%
        select(
          submission = system_submission_date,
          interview = section_meta_interview_date,
          interviewer = section_meta_interviewername,
          entity = meta_instance_name,
          instance = meta_instance_id,
          start = section_meta_start_time_user,
          end = survey_grp_section_enumerator_feedback2_end_time_user,
          section_meta_location2_id
        ) %>%
        filter(interviewer %in% input$enums) %>%
        mutate(interview = as.Date(interview)) %>%
        filter(
          interview >= as.Date(input$dates)[1] &
            interview <= as.Date(input$dates)[2]
        ) %>%
        group_by(interviewer) %>%
        summarise(
          Form = language_switch("select_form1_women", input$language),
          "Submissions" = n(),
          "Start Date" = min(date(interview), na.rm = T),
          "End Date" = max(date(interview), na.rm = T),
          "Average Time" = round(
            median((as.numeric(end) - as.numeric(start)) / 60, na.rm = T),
            1
          ),
          "Interviews < 10 minutes" = scales::percent(mean(
            ((as.numeric(end) - as.numeric(start))) < (60 * 10),
            na.rm = T
          ))
        )

      global_sum <- data1$data0$global$data %>%
        select(
          submission = system_submission_date,
          interview = section_meta_interview_date,
          interviewer = section_meta_interviewername,
          entity = meta_instance_name,
          instance = meta_instance_id,
          start = section_meta_start_time_user,
          end = survey_grp_section_enumerator_feedback2_end_time_user,
          section_meta_location2_id
        ) %>%
        filter(interviewer %in% input$enums) %>%
        mutate(interview = as.Date(interview)) %>%
        filter(
          interview >= as.Date(input$dates)[1] &
            interview <= as.Date(input$dates)[2]
        ) %>%
        group_by(interviewer) %>%
        summarise(
          Form = language_switch("select_form1_global", input$language),
          "Submissions" = n(),
          "Start Date" = min(date(interview), na.rm = T),
          "End Date" = max(date(interview), na.rm = T),
          "Average Time" = round(
            median((as.numeric(end) - as.numeric(start)) / 60, na.rm = T),
            1
          ),
          "Interviews < 10 minutes" = scales::percent(mean(
            ((as.numeric(end) - as.numeric(start))) < (60 * 10),
            na.rm = T
          ))
        )

      # colnames(reg_sum)<-
      colnames(global_sum) <- colnames(women_sum) <- c(
        "Enumerator",
        "Form",
        language_switch("label_submissions", input$language),
        language_switch("label_start", input$language),
        language_switch("label_end", input$language),
        language_switch("label_time", input$language),
        language_switch("label_interview10", input$language)
      )

      rbind(global_sum, women_sum) %>%
        arrange(Enumerator, Form) -> x
    }
    return(x)
  })

  #produce summary statistics for selected variable

  output$SummaryTable <- render_gt({
    #     print(data1$data0$flag)
    if (data1$data0$flag == TRUE) {
      variable <- filter(
        data1$data0[[input$form2]]$form,
        label == input$question
      )$ruodk_name

      #use defined odk_table function to detect class of variable and produce a relevant table
      odk_table(
        data1$data0[[input$form2]]$data,
        variable,
        data1$data0[[input$form2]]$form,
        repeats = data1$data0[[input$form2]]$repeats
      ) %>%
        gt()
    }
  })

  output$indicator_text <- renderText({
    if (str_detect(input$indicator, "FIES")) {
      HTML(paste0(
        report_translations[
          report_translations$element == "extra_text3",
          input$language
        ],
        "</br>",
        "</br>",
        report_translations[
          report_translations$element == "extra_text4",
          input$language
        ],
        "</br>",
        "</br>",
        "<ul>",
        "<li>",
        report_translations[
          report_translations$element == "extra_text5",
          input$language
        ],
        "</li>",
        "<li>",
        report_translations[
          report_translations$element == "extra_text6",
          input$language
        ],
        "</li>",
        "<li>",
        report_translations[
          report_translations$element == "extra_text7",
          input$language
        ],
        "</li>",
        "<li>",
        report_translations[
          report_translations$element == "extra_text8",
          input$language
        ],
        "</li>",
        "</ul>"
      ))
    } else if (str_detect(input$indicator, "HDDS")) {
      HTML(paste0(
        report_translations[
          report_translations$element == "extra_text1",
          input$language
        ],
        "</br>",
        "</br>",
        report_translations[
          report_translations$element == "extra_text2",
          input$language
        ]
      ))
    } else if (str_detect(input$indicator, "simpson")) {
      HTML(paste0(
        report_translations[
          report_translations$element == "extra_text11",
          input$language
        ],
        "</br>",
        "</br>",
        report_translations[
          report_translations$element == "extra_text12",
          input$language
        ]
      ))
    } else {
      HTML(paste0(""))
    }
  })

  #produce indicator table
  output$IndicatorTable <- render_gt({
    if (data_directory$Status[data_directory$project == project_code] == 1) {
      indicator_table(
        data1$data0$indicators,
        input$indicator,
        indicator_directory,
        input$groupvar,
        input$language
      ) %>%
        as_gt()
    } else {
      indicator_table(
        data_processed$indicators,
        input$indicator,
        indicator_directory,
        input$groupvar,
        input$language
      ) %>%
        as_gt()
    }
  })

  #produce indicator plot
  output$IndicatorPlot <- renderPlotly({
    if (data_directory$Status[data_directory$project == project_code] == 1) {
      indicator_plot(
        data1$data0$indicators,
        input$indicator,
        indicator_directory,
        input$groupvar,
        language = input$language
      )
    } else {
      indicator_plot(
        data_processed$indicators,
        input$indicator,
        indicator_directory,
        input$groupvar,
        language = input$language
      )
    }
  })

  observeEvent(input$question, ignoreInit = TRUE, {
    #determine if question is a select one / select multiple to modify options shown under plot
    output$select1 <- reactive({
      filter(data1$data0[[input$form2]]$form, label == input$question)$type ==
        "select" &
        is.na(
          filter(
            data1$data0[[input$form2]]$form,
            ruodk_name ==
              filter(
                data1$data0[[input$form2]]$form,
                label == input$question
              )$ruodk_name
          )$selectMultiple
        )
    })
    output$selectm <- reactive({
      filter(data1$data0[[input$form2]]$form, label == input$question)$type ==
        "select" &
        !is.na(
          filter(
            data1$data0[[input$form2]]$form,
            ruodk_name ==
              filter(
                data1$data0[[input$form2]]$form,
                label == input$question
              )$ruodk_name
          )$selectMultiple
        )
    })
    outputOptions(output, "select1", suspendWhenHidden = FALSE)
    outputOptions(output, "selectm", suspendWhenHidden = FALSE)
  })

  #use defined odk_plot function to detect class of variable and produce a relevant plot
  output$SummaryPlot <- renderPlot({
    if (data1$data0$flag == TRUE) {
      variable <- filter(
        data1$data0[[input$form2]]$form,
        label == input$question
      )$ruodk_name
      plotvar = ""

      if (
        filter(data1$data0[[input$form2]]$form, label == input$question)$type ==
          "select"
      ) {
        if (
          is.na(
            filter(
              data1$data0[[input$form2]]$form,
              label == input$question
            )$selectMultiple
          )
        ) {
          plotvar = input$select1plot
        } else {
          plotvar = input$selectmplot
        }
      }

      p2 <- odk_plot(
        data1$data0[[input$form2]]$data,
        variable,
        data1$data0[[input$form2]]$form,
        repeats = data1$data0[[input$form2]]$repeats,
        plotvar = plotvar
      )
      print(p2)
    }
  })

  observeEvent(input$genBaiscReport, priority = 2, {
    messages$loading <- 1
  })

  observeEvent(input$genBaiscReport, priority = 1, {
    withProgress(
      message = language_switch("output_report_generating", input$language),
      {
        if (messages$progress < 2) {
          print(1)
          if (!dir.exists(outpath$outpath)) {
            dir.create(outpath$outpath, recursive = TRUE)
          }

          tempReport <- file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.Rmd", sep = "_")
          )
          htmlReport <- file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.html", sep = "_")
          )
          pdfReport <- file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.pdf", sep = "_")
          )

          file.copy("report_template.Rmd", tempReport, overwrite = TRUE)

          params <- list(
            title = paste0(project_code, " Summary Report"),
            data_type = "raw",
            data_processed = NA,
            data_raw = data1$data0,
            language = input$language,
            #summary_vars = input$summary_vars,
            translations = report_translations,
            indicator_tab = indicator_tab,
            global_form = form_schema_ext(fid = Sys.getenv("form_id")),
            womens_form = form_schema_ext(fid = Sys.getenv("women_form_id"))
          )

          #   if(input$report_data=="raw"){
          #     params$data_raw = data1$data0
          #   }
          #   else{
          #     params$data_processed = data_processed
          #    }

          print("OK")
        }

        if (
          messages$progress == 2 &
            (!file.exists(file.path(
              outpath$outpath,
              paste(project_code, input$language, "report.pdf", sep = "_")
            )) |
              input$regen == 0)
        ) {
          print(2)
          if (!dir.exists(outpath$outpath)) {
            dir.create(outpath$outpath, recursive = TRUE)
          }

          tempReport <- file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.Rmd", sep = "_")
          )
          htmlReport <- file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.html", sep = "_")
          )
          pdfReport <- file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.pdf", sep = "_")
          )

          file.copy("report_template.Rmd", tempReport, overwrite = TRUE)

          params <- list(
            title = paste0(project_code, " Summary Report"),
            data_type = "processed",
            data_processed = data_processed,
            data_raw = NA,
            language = input$language,
            #summary_vars = input$summary_vars,
            translations = report_translations,
            indicator_tab = indicator_tab,
            global_form = form_schema_ext(fid = Sys.getenv("form_id")),
            womens_form = form_schema_ext(fid = Sys.getenv("women_form_id"))
          )
        }
        if (
          (messages$progress == 2 &
            (!file.exists(file.path(
              outpath$outpath,
              paste(project_code, input$language, "report.pdf", sep = "_")
            )) |
              input$regen == 0)) |
            messages$progress < 2
        ) {
          print(3)
          html_fn <- rmarkdown::render(
            tempReport,
            rmarkdown::html_document(
              theme = bslib::bs_theme("cosmo", version = 4)
            ),
            params = params,
            output_file = htmlReport,
            envir = new.env(parent = globalenv())
          )

          pagedown::chrome_print(
            html_fn,
            pdfReport,
            options = list(printBackground = TRUE)
          )
        }

        #   output$report <- reactive({
        #     file.exists(file.path(paste0("output/reports"),paste(project_code,input$language,"report.pdf",sep="_")))
        #
        #   })

        #    outputOptions(output, "report", suspendWhenHidden = FALSE)
      }
    )

    output$printBasicReport2 <- downloadHandler(
      filename = paste(project_code, input$language, "report.pdf", sep = "_"),
      content = function(file) {
        file.copy(
          file.path(
            outpath$outpath,
            paste(project_code, input$language, "report.pdf", sep = "_")
          ),
          file
        )
      }
    )
    messages$loading <- 0
  })

  output$printBasicReport <- downloadHandler(
    filename = paste(project_code, input$language, "report.pdf", sep = "_"),
    content = function(file) {
      file.copy(
        file.path(
          outpath$outpath,
          paste(project_code, input$language, "report.pdf", sep = "_")
        ),
        file
      )
    }
  )
}
# Run the application
shinyApp(ui = ui, server = server)

#!/usr/bin/env Rscript

# Smoke-test script for app:calculate-indicators. Prints the arguments it
# receives so you can confirm the Laravel command is passing them through
# correctly. Not the real indicator calculation script.

args <- commandArgs(trailingOnly = TRUE)

if (length(args) < 3) {
  stop(sprintf(
    "Expected at least 3 arguments (odk_projects.id, odk_central_project_id, and at least one xlsform odk_id), got %d: %s",
    length(args), paste(args, collapse = ", ")
  ))
}

project_id <- args[1]
odk_central_project_id <- args[2]
xlsform_ids <- args[-c(1, 2)]

cat("Received arguments:\n")
cat(sprintf("  odk_projects.id = %s\n", project_id))
cat(sprintf("  odk_central_project_id = %s\n", odk_central_project_id))
for (i in seq_along(xlsform_ids)) {
  cat(sprintf("  xlsform_%d = %s\n", i, xlsform_ids[i]))
}

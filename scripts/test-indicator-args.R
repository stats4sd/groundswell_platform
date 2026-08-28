#!/usr/bin/env Rscript

# Smoke-test script for app:calculate-indicators. Prints the arguments it
# receives so you can confirm the Laravel command is passing them through
# correctly. Not the real indicator calculation script.

args <- commandArgs(trailingOnly = TRUE)

expected <- c("project", "xlsform_1", "xlsform_2", "xlsform_3")

if (length(args) != length(expected)) {
  stop(sprintf(
    "Expected %d arguments (%s), got %d: %s",
    length(expected), paste(expected, collapse = ", "),
    length(args), paste(args, collapse = ", ")
  ))
}

names(args) <- expected

cat("Received arguments:\n")
for (name in expected) {
  cat(sprintf("  %s = %s\n", name, args[[name]]))
}

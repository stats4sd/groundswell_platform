# Data Governance Documentation — Questions for the Team

**Date**: 2026-07-16
**Purpose**: Questions to resolve before drafting the platform's data rights & responsibilities documentation (privacy policy, consent forms, retention plan, etc.) ahead of go-live.

## Context

Three parties are involved:

- **Projects / programs / organisations** — collect the data (likely data controllers)
- **Stats4SD** — platform maintainer and data processor
- **Groundswell** — program coordinator

Target documents:

| Document                                                    | Responsible | Required    |
| ----------------------------------------------------------- | ----------- | ----------- |
| Privacy Policy                                              | Stats4SD ?  | ✅          |
| Information Form for Respondents                            | Stats4SD    | ✅          |
| Informed Consent Form (when required)                       | Stats4SD    | ✅          |
| Data Processing Record                                      | Stats4SD    | Recommended |
| Data Retention and Archiving Plan                           | Stats4SD    | ✅          |
| Procedure for Deleting or Anonymizing Data                  | Stats4SD    | ✅          |
| Confidentiality agreement signed by investigators           | Unsure      | ✅          |
| Incident management procedure (data loss, theft, or breach) | Unsure      | Recommended |

## 1. Roles and legal responsibility

*Settles the two "Unsure" rows — and most other answers hang off this.*

- For each dataset, who is the data controller — the individual project/organisation collecting the data, Groundswell as coordinator, or is it joint? Stats4SD is presumably a processor, but does Stats4SD ever use the data for its own purposes (methodology research, demos), which would make it a controller too?
- Which law governs? GDPR/UK GDPR via Stats4SD, plus local data protection laws in each country where farmers are surveyed?
- Is there (or will there be) a Data Processing Agreement (DPA) between each controller and Stats4SD? A DPA is arguably the missing document in the table — the Privacy Policy and confidentiality agreements flow from it.
- Who signs the confidentiality agreements — and with whom? Enumerators with their employing organisation, or with Stats4SD?

## 2. What data, from whom

*Needed for the Privacy Policy, Information Form, and Processing Record.*

- What personal data do surveys actually capture? Names, phone numbers, GPS coordinates of farms/households? Are farm GPS points treated as personal data (they usually identify a household)?
- Any special-category data (health, ethnicity, income of vulnerable groups)?
- Are respondents ever children or otherwise unable to consent?

## 3. Legal basis and consent

- Is consent the legal basis, or legitimate interest / public task? (This determines when the Informed Consent Form is "required".)
- How is consent captured in practice — inside the ODK form, on paper, orally? In what languages, and what's the plan for low-literacy respondents?
- Can a respondent withdraw consent later — and how would a farmer actually reach Stats4SD to do so?

## 4. Retention, deletion, anonymisation

- Who decides retention periods — each program, or a platform-wide default?
- When data is "deleted", where does it need to be deleted *from*? The platform DB, ODK Central, backups, Excel exports users have downloaded, media attachments?
- What does "anonymised" mean for this data — can GPS + crop data ever be truly anonymous?
- What happens to a program's data when the program ends or an organisation leaves the platform?

## 5. Access and downstream use

- Users can export full datasets to Excel. Once exported, who's responsible for that copy? Should exports of identifiable data be restricted by role?
- Do Stats4SD staff (Super Admins) have access to all raw data across all tenants? Is that documented and justified?
- Is any data published or shared publicly (e.g., the public map)? What's aggregated/anonymised before that?

## 6. Sub-processors and infrastructure

- Where is the platform hosted, and where is ODK Central hosted? Any cross-border transfers?
- List all third-party services touching the data: hosting, backups, email, error tracking (does Sentry receive request data containing PII — is it scrubbed?).

## 7. Incident management

- Who is notified first when a breach is suspected, and who decides whether to report to a regulator (and which regulator, for multi-country data)?
- Who informs affected respondents, given Stats4SD likely has no direct channel to farmers — does that obligation flow through the projects?

## Priority

If the meeting only answers three things:

1. Controller vs processor, per dataset
2. Whether consent is the legal basis
3. What deletion must cover

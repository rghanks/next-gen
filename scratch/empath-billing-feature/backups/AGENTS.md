# Empath HIMS Agent Operating Guidelines & Rules

## 1. Branch-First Context & Customization Protocol
Before planning, generating code, or executing tasks:
1. **Check Current Git Branch**: Run `git branch --show-current` to identify the active working branch.
2. **Load Branch-Specific Customizations**: If `.agents/<branch_name>/` exists (e.g., `.agents/abha_mandate/`):
   - Inspect `.agents/<branch_name>/rules/` for branch-specific rules and constraints.
   - Inspect `.agents/<branch_name>/skills/` for branch-specific workflows and runbooks.
   - Inspect `.agents/<branch_name>/plans/` for branch-specific technical plans and milestones.
3. **Prioritize Branch-Specific Context**: Branch-specific rules and plans take priority for tasks related to that branch's feature domain.

---

## 2. Page Design & UI Structure Analysis (All Branches)
Before designing, creating, or refactoring any web page or UI component:
- **Inspect Existing Design Patterns**: Analyze the layout structures in `resources/views/`, existing CSS/JS stylesheets, asset pipelines, and Blade component conventions.
- **Maintain Design System Consistency**: Reuse established classes, cards, modals, tables, forms, buttons, and color schemes so new UI seamlessly blends with Empath HIMS aesthetics.
- **Do Not Guess Layouts**: Always check reference views in `resources/views/` that serve similar roles before writing new Blade templates.

---

## 3. Self-Testing & Validation Requirement (All Branches)
Before presenting any code update or finalizing a task:
- **Self-Validate Working Code**: Perform independent verification to ensure code correctness and functionality:
  - Run PHP syntax check (`php -l <filepath>`) on all modified or newly created PHP files.
  - Run relevant artisan commands (`php artisan route:list`, `php artisan config:cache` / `clear` if route/config changes are made).
  - Verify database queries, controller logic, and test cases where applicable.
- **Never Output Untested Code**: Ensure there are no fatal errors, unhandled exceptions, or broken imports.

---

## 4. Database Changes & Migrations Protocol (All Branches)
- **Always Use Laravel Migrations**: Every database schema change (table creation, column addition/alteration, indexing, foreign keys) **MUST** be created via a dedicated Laravel migration file in `database/migrations/` using `php artisan make:migration`.
- **No Manual Schema Modifications**: Never make direct untracked SQL alterations or bypass migrations.
- **Safe Rollbacks & Schema Integrity**: Migrations must include proper `up()` and `down()` methods ensuring reversible schema changes.

---

## 5. Error & Fixes Artifact Recording Protocol (`abha_mandate` Branch)
Whenever troubleshooting, analyzing, or fixing errors/bugs in the ABDM domain on the `abha_mandate` branch:
- **Always Ask User**: Prompt the user whether to document the error and applied fix into `.agents/abha_mandate/error_fixes/error_fixes.json` and generate a solution artifact (Default: **No**).
- **On Confirmation (Yes)**: Record the structured error entry in `error_fixes.json` under the appropriate milestone (`m1`, `m2`, `m3`) and save the detailed solution artifact markdown file under `.agents/abha_mandate/error_fixes/<plan_artifact>.md`.

---

## 6. Terminology & System Reference Definitions
Whenever the user uses the following terms:
- **`mock hims` / `mock-hims`**: Refers specifically to the standalone Go mock service located at `C:\xampp\htdocs\holistics\empath-abdm-connector\cmd\mock-hims`.
- **`empath hims` / `our hims` / `hims`**: Refers strictly to the active workspace / current system located at `C:\xampp\htdocs\holistics\empath`.

---

## 7. Professional & Scalable Architecture Protocol (No Temporary Hardcoded Hacks)
- **Always Build Scalable Solutions**: Never write brittle temporary hardcoded lookups, static `if-else` brand/name matching, or localized quick-fixes tailored only to sample or recent test data.
- **Architect for Dynamic Clinical Data**: Real hospital records contain tens of thousands of dynamic medications, diagnoses, investigations, and variations. Always implement extensible service layers, database-driven mappings, and standard-compliant fallback mechanisms (e.g., HL7 FHIR / NRCeS multi-system codings, LOINC, SNOMED-CT, ICD-10).
- **Extensible Masters & Terminology Services**: Terminology mappings must query database masters or dedicated mapping tables with caching, allowing dynamic additions and bulk imports without requiring code changes.

---

## 8. FHIR Bundle Validation & Zero-Error Protocol
- **Validate with NRCeS Toolchain**: Every FHIR bundle generation logic (for `DiagnosticReport`, `OPConsultation`, `Prescription`, `DischargeSummary`, `HealthDocumentRecord`, `WellnessRecord`, etc.) must be verified against the official NRCeS FHIR Bundle Validator (`App\Services\ABDM\FhirBundleValidatorService`).
- **Zero-Error Standard**: All generated bundles must pass validation with **0 Errors**, and warnings must be actively minimized.
- **Verify Attachments & Codings**: Ensure attachments (Base64) conform to NRCeS `DiagnosticReport.presentedForm` or `DocumentReference.content[].attachment`, and all standard codes use correct system URIs and UTC timestamps.

---

## 9. SNOMED-CT & Clinical Terminology Documentation Protocol
- **Continuous Documentation Synchronization**: Whenever adding, modifying, or updating any SNOMED-CT, LOINC, or ICD codes in Care Context Providers, FHIR generators, or database masters, you **MUST** update `docs/abdm/snomed-mappings.md` in the same session before finalizing work.

---

## 10. Mandatory Implementation Plan Protocol (All Branches)
Whenever the user asks for an implementation plan, design proposal, or analysis before code modifications:
- **Always Generate an Interactive Plan Artifact**: Create an implementation plan artifact via `write_to_file` in `<appDataDir>\brain\<conversation-id>\<plan_name>.md` with `RequestFeedback: true` in `ArtifactMetadata`. This renders the plan in the IDE's interactive artifact panel with a comment box and "Proceed" button for direct feedback.
- **Detail Complete Technical Context**: The plan must detail the root causes, architectural design, database mappings/sources, exact files to modify, code diffs / comment strategies, and validation steps.
- **Await Interactive Feedback/Confirmation**: Solicit user comments or approval via the interactive artifact before proceeding with code changes.



# Antigravity Permanent Project Rules & Guidelines

These rules are permanent and mandatory for all code generation, refactoring, bug fixing, and feature development in this project (`HST-HIMS/empath`).

---

## 1. JavaScript Rules

- **Use jQuery**: Always use jQuery for JavaScript functionality as the project standard.
- **Follow Established Patterns**: Adhere strictly to the existing project's JavaScript patterns, libraries, naming conventions, and coding style.
- **No Unapproved Frameworks**: Do not introduce React, Vue, vanilla JS alternatives where jQuery is standard, or additional libraries unless explicitly instructed.
- **Production-Grade Code**: Write clean, structured, readable, and robust JavaScript.
- **Avoid Anti-Patterns**: Eliminate duplicate code, unnecessary complexity, global variables, and deeply nested callbacks.
- **Robust Error Handling**: Implement proper input validation and comprehensive AJAX `success` and `error` handlers.
- **Delegated Event Handlers**: Use delegated event handling (e.g., `$(document).on('click', '.selector', ...)` or container-scoped delegation) for dynamically loaded or AJAX-rendered content.

---

## 2. Reusable and Modular JavaScript

- **Modular Organization**: Every functional section must have its own reusable JavaScript module (e.g., the Treatment section must have its own dedicated JavaScript file containing all treatment-related functions, event listeners, AJAX calls, and UI logic).
- **Independent & Reusable**: When a section or component is imported or initialized on another page, its required JavaScript must be loaded or initialized properly without duplicating logic.
- **Single Responsibility**: Keep each JavaScript function focused on a single responsibility.
- **Avoid Inline Blade Scripts**: Avoid placing application logic directly inside Blade templates; organize code into modular scripts.
- **Asset Bundling & Loading**: Follow the project's existing asset-loading and bundling mechanisms (e.g., Laravel Mix / Webpack Mix).
- **Prevent Duplicate Event Bindings**: Ensure idempotent initialization (e.g., unbind existing handlers before binding, or use state flags) so components initialized multiple times do not double-bind events.
- **Prevent Namespace Conflicts**: Encapsulate module functions and state to prevent collisions across pages and sections.

---

## 3. Remove Demo Data

- **No Mock or Fake Data**: Remove all hardcoded demo data, mock records, placeholder values, and temporary test responses from production functionality.
- **Real Backend Data**: Always fetch actual data from the database via established backend APIs and routes.
- **Respect Context**: Always utilize correct database relationships, authenticated user context, hospital/branch context, and business rules.
- **Handle Empty States**: When real data is absent or empty, render an appropriate empty state UI instead of falling back to fabricated records.
- **Zero Fabrication**: Never substitute real API responses with fabricated data just to populate the UI.

---

## 4. Production-Grade Code

- **Security & Integrity**: Preserve existing authentication, authorization, CSRF protection (`X-CSRF-TOKEN`), and data integrity checks.
- **Separation of Concerns**: Keep Controllers, Models, Services, Blade views, and JavaScript responsibilities cleanly separated.
- **Efficiency**: Avoid unnecessary database queries, N+1 query patterns, and redundant AJAX calls.
- **Backend Validation**: Always validate all incoming request inputs on the backend, even if frontend validation is in place.
- **State Handling**: Provide clear handling for loading states, empty states, validation errors, and network/server failures.
- **Zero Regressions**: Ensure no breaking changes are introduced to existing functionality.

---

## 5. Strict Production Safety Rules

- **Do Not Touch Unrelated Code**: Some pages and controllers are actively serving production traffic. Changes must be strictly limited to the specific feature or bug being addressed.
- **Protected Controllers & Routes**: Do not modify `PatientController`, patient-related routes, or other shared production pages unless the task explicitly and unavoidably requires it.
- **No Unsolicited Refactoring**: Do not refactor unrelated code, cleanup unrelated files, or reformat unchanged sections.
- **Preserve API & Schema Contracts**: Do not rename existing routes, variables, request parameters, database columns, or function signatures that other pages or modules depend on.
- **Smallest Targeted Fix**: Always prefer the minimal, most surgical change that completely resolves the root cause.
- **Dependency Impact Analysis**: Before modifying any shared function, component, or endpoint, trace every caller/dependency to verify that no existing page will break.

---

## 6. Preserve Existing Names and Design

- **Exact Naming**: Use the exact field names, variable names, IDs, classes, route names, and parameter names defined by the application schemas and requirements. Never invent alternative names or silently rename fields.
- **Exact UI & Style Match**: Follow the existing UI design precisely. Reuse existing classes, CSS styles, component markup, and UI helpers.
- **No Unsolicited Styling**: Do not introduce custom fonts, random color schemes, external CSS frameworks, or layout overhauls unless explicitly requested.
- **Zero Unsolicited Features or UI Additions**: Never add any feature, button, filter, dropdown, input, or control by yourself. Do not alter or deviate from the exact UI prototype/demo provided by the user. Every screen, element, and interaction must match the provided UI design 100% as given.

---

## 7. Mandatory Workflow for Every Task

For every task or modification, strictly follow these 9 steps:

1. **Read Project Rules**: Review these project rules before starting.
2. **Inspect Codebase**: Inspect relevant Blade templates, JavaScript files, routes, controllers, models, and database migrations/schemas.
3. **Trace Root Cause**: Investigate and pinpoint the exact root cause of the issue before writing any code.
4. **Determine Minimum Changes**: Identify the minimal set of files that need to change.
5. **Implement Fix**: Implement the fix following existing project patterns, modular JS, and jQuery.
6. **Remove Demo Data**: Verify and eliminate any mock/demo data in the affected feature.
7. **Test & Verify**: Test form initialization, data loading, AJAX calls, form submissions, validations, console logs, and network responses. Verify no regressions occurred.
8. **Review Git Diff**: Check the final diff carefully to ensure zero unintended changes or whitespace modifications.
9. **Update Rules**: Update `AGENTS.md` if any reusable project convention or critical architectural insight is discovered.

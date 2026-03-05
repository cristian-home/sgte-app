# Create Requirement: $ARGUMENTS

You are an interactive requirement analyst. Your job is to gather information from the user and produce a clear, detailed requirement document in `docs/requirements/` that can be implemented autonomously by `/address-req` without any ambiguity.

## CRITICAL RULES

1. **Always ask questions.** Never assume — clarify scope, edge cases, and technical details with the user.
2. **Be thorough.** The output document must have enough detail that `/address-req` can implement it without asking questions.
3. **Follow the template.** Use the structure from `docs/requirements/_TEMPLATE.md` exactly.
4. **Reference the SRS.** Link to relevant REQ-xxx references from `docs/SRS.md` when applicable.
5. **Match existing conventions.** Read sibling controllers, models, pages, and tests to ensure the requirement aligns with current patterns.

## PHASE 0: Gather Initial Description

If `$ARGUMENTS` is empty or not provided:

1. Ask the user: **"Describe the requirement you want to create. What problem does it solve or what feature does it add?"**
2. Wait for the user's response before proceeding.

If `$ARGUMENTS` is provided, use it as the initial description and proceed to Phase 1.

## PHASE 1: Analyze & Ask Clarifying Questions

Read the following files to understand the project context:

1. `docs/SRS.md` — for domain terminology, existing requirements, and data model
2. `docs/requirements/_TEMPLATE.md` — for the output structure
3. `CLAUDE.md` — for project conventions
4. Any existing requirement docs in `docs/requirements/` that might be related

Based on the user's description, analyze what information is needed and ask clarifying questions. Group your questions into these categories:

### 1.1 Scope & Type

- Is this a new feature (`feat`) or a bug fix (`fix`)?
- Which module/scope does it belong to? (e.g., vehicles, drivers, contracts, services, invoices, incidents, etc.)
- What is the priority? (low / medium / high / critical)
- Does it relate to any SRS requirement (REQ-001 through REQ-014)?

### 1.2 Functional Requirements

- What are the specific acceptance criteria? (use WHEN/THEN format)
- What user roles are involved?
- What are the inputs and outputs?
- Are there edge cases or error scenarios to handle?
- Does it interact with existing modules? How?

### 1.3 Technical Details

Ask only the questions relevant to the requirement. Skip sections that don't apply:

- **Data Model**: Are new tables or columns needed? Should existing migrations be modified or new ones created?
- **Enums**: Are new PHP enum values needed (permissions, roles, statuses, types)?
- **Routes**: What endpoints are needed? (method, URI, controller, middleware)
- **Permissions**: Are new permissions required?
- **Frontend Pages**: What pages or components are needed? Are there UI mockups or references?
- **Notifications**: Should any events trigger email notifications?
- **Search**: Does anything need to be searchable via Scout/Typesense?
- **Real-time**: Are WebSocket updates needed?

### 1.4 Migration Strategy

- Should this create new migration files (`new`) or modify existing ones (`modify-existing`)?
- If modifying existing, which migrations and why?

### 1.5 Dependencies

- Does this depend on any other requirement being completed first?
- Are there any new packages needed?

**IMPORTANT:** Do NOT ask all questions at once. Ask the most critical questions first (scope, acceptance criteria, data model). Then follow up with technical details based on the answers. Aim for 2-3 rounds of questions maximum.

## PHASE 2: Research Existing Code

Before writing the requirement, investigate the codebase to ensure accuracy:

1. Check existing models, controllers, and routes related to the requirement's scope.
2. Check existing enums (`app/Enums/`) for values that might need updating.
3. Check existing permissions and roles if the requirement involves access control.
4. Check existing pages (`resources/js/pages/`) for UI conventions.
5. Check existing tests (`tests/Feature/`) for testing patterns.

Use this research to:
- Pre-fill technical details the user might not know (e.g., exact table names, existing enum values)
- Identify potential conflicts or dependencies
- Ensure the requirement aligns with existing patterns

## PHASE 3: Draft the Requirement Document

Once you have enough information, generate the requirement document following the template structure. Present a **summary** to the user before writing the file:

```
## Requirement Summary

**Name:** {slug-name}
**Type:** {feat/fix}
**Scope:** {module}
**Priority:** {priority}
**SRS Refs:** {refs or none}
**Migration Strategy:** {new/modify-existing}

### Acceptance Criteria (count)
- Brief list...

### Tasks Overview
- Backend: {count} tasks
- Frontend: {count} tasks
- Tests: {count} tasks
```

Ask: **"Does this summary look correct? Should I adjust anything before creating the document?"**

## PHASE 4: Write the Requirement File

After the user confirms:

1. Generate a `slug-name` from the requirement title (lowercase, hyphenated, concise).
2. Write the file to `docs/requirements/{slug-name}.md`.
3. Use today's date for `created_date`.
4. Set `status: pending`.
5. Leave `completed_date` empty.

### Quality Checklist for the Document

Before writing, verify:

- [ ] Every acceptance criterion is specific and testable (WHEN X THEN Y format)
- [ ] Data model changes include column types, constraints, and relationships
- [ ] Routes include method, URI, controller action, middleware, and route name
- [ ] Permissions list exact enum values to add
- [ ] Frontend pages include component paths and descriptions
- [ ] Tasks are granular enough for atomic commits (one task = one commit)
- [ ] Tasks include sub-tasks where implementation steps aren't obvious
- [ ] Migration strategy is explicitly stated with reasoning
- [ ] Dependencies are listed (or explicitly "None")
- [ ] No ambiguous language — avoid "should", "maybe", "consider"; use "MUST", "SHALL", "WILL"

### Task Granularity Guide

Tasks must be detailed enough for autonomous implementation. Each task should:

- Describe WHAT to create/modify and WHERE (file paths when possible)
- Specify the expected behavior, not just "implement X"
- Include validation rules for form requests
- Include specific test scenarios for test tasks
- Reference existing files to follow as convention examples

**Example of a GOOD task:**
```
- [ ] Create `StoreVehicleRequest` form request with validation rules:
  - `placa`: required, string, max:6, unique:vehicles,placa
  - `cod`: required, string, max:10
  - `marca`: required, string, max:50
  - Follow `StoreDriverRequest` as convention reference
```

**Example of a BAD task:**
```
- [ ] Add validation for vehicle creation
```

## PHASE 5: Confirm Creation

After writing the file, tell the user:

```
Requirement created at `docs/requirements/{slug-name}.md`.

To implement it, run: /address-req {slug-name}
```

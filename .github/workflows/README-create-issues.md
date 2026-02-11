# Create Issues from Templates

This GitHub Action workflow automatically creates GitHub issues from markdown template files.

## Purpose

This workflow is designed to automate the process of creating GitHub issues from standardized template files. This is particularly useful when:
- Extracting unresolved review comments from PRs
- Batch-creating issues from analysis outputs
- Converting documentation into trackable issues

## Usage

### Manual Trigger (Workflow Dispatch)

The workflow can be triggered manually from the GitHub Actions tab:

1. Go to the **Actions** tab in your repository
2. Select **"Create Issues from Templates"** from the workflow list
3. Click **"Run workflow"**
4. Configure the inputs:
   - **Template Pattern**: File pattern to match template files (default: `docs/*-issue-*.md`)
   - **Dry Run**: Enable to preview what would be created without actually creating issues

### Example: PR #10 Use Case

After creating issue templates like `docs/pr8-unresolved-issue-1.md`:

1. Navigate to Actions → Create Issues from Templates
2. Set pattern to `docs/pr8-unresolved-issue-*.md`
3. Run with dry_run enabled first to preview
4. Run again with dry_run disabled to create the issues

## Template Format

Template files should follow this format:

```markdown
# Issue: Your Issue Title Here

## Source
Reference to where this issue came from (e.g., PR #8)

## Status
Current status

## Description
Detailed description of the issue

## Location
- **File**: `path/to/file.php`
- **Line**: 123

## Impact
- Impact point 1
- Impact point 2

## Labels
- bug
- priority: high
- component: your-component
```

### Required Sections

- **Title**: The workflow extracts the title from the first `# Issue:` heading
  - If no `# Issue:` heading is found, it uses the first `#` heading
- **Labels**: Listed under `## Labels` section as a bulleted list

### Example Templates

See the following examples from PR #10:
- `docs/pr8-unresolved-issue-1.md`
- `docs/pr8-unresolved-issue-2.md`

## Features

- **Duplicate Prevention**: Checks if an issue with the same title already exists before creating
- **Dry Run Mode**: Preview what would be created without actually creating issues
- **Flexible Pattern Matching**: Use glob patterns to select specific template files
- **Label Support**: Automatically applies labels specified in the template
- **Full Body Preservation**: The entire template file becomes the issue body, maintaining formatting

## Workflow Inputs

| Input | Description | Default | Required |
|-------|-------------|---------|----------|
| `template_pattern` | File glob pattern to match template files | `docs/*-issue-*.md` | No |
| `dry_run` | Preview mode - shows what would be created without creating issues | `false` | No |

## Permissions

The workflow requires:
- `issues: write` - To create issues
- `contents: read` - To read template files from the repository

## Example Workflow Runs

### Dry Run Example
```bash
# This will show what would be created without actually creating issues
Template Pattern: docs/pr8-unresolved-issue-*.md
Dry Run: true
```

Output:
```
📋 Found template files:
./docs/pr8-unresolved-issue-1.md
./docs/pr8-unresolved-issue-2.md

📄 Processing: ./docs/pr8-unresolved-issue-1.md
  Title: Somerset incidents filtered out due to missing coordinates
  Labels: bug,priority: high,component: road-incidents
  🏃 DRY RUN - Would create issue with:
     Title: Somerset incidents filtered out due to missing coordinates
     Labels: bug,priority: high,component: road-incidents
     Body length: 1234 characters
```

### Live Run Example
```bash
# This will actually create the issues
Template Pattern: docs/pr8-unresolved-issue-*.md
Dry Run: false
```

Output:
```
📋 Found template files:
./docs/pr8-unresolved-issue-1.md
./docs/pr8-unresolved-issue-2.md

📄 Processing: ./docs/pr8-unresolved-issue-1.md
  Title: Somerset incidents filtered out due to missing coordinates
  Labels: bug,priority: high,component: road-incidents
  ✅ Creating issue...
  ✨ Created issue #123
```

## Limitations

- The workflow skips creating duplicate issues if an issue with the same title already exists
- Title extraction requires a `# Issue:` heading or at least one `#` heading
- Labels must be listed in a `## Labels` section to be automatically applied
- Invalid label names will cause the workflow to fail

## Integration with PR Workflows

This workflow complements PR review processes:

1. During PR review, create issue templates for unresolved items
2. Store templates in `docs/` directory following the naming pattern
3. After PR merge (or before), run this workflow to create tracking issues
4. Reference the created issues back in the PR

## Troubleshooting

**No templates found:**
- Verify your pattern matches your file locations
- Check that template files are committed to the repository

**Issue creation fails:**
- Verify the `GITHUB_TOKEN` has `issues: write` permission
- Check that label names are valid (no spaces around colons in priority labels)

**Title not extracted:**
- Ensure template has a heading starting with `# Issue:` or at least one `#` heading
- Check for proper markdown formatting

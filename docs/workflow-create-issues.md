# How to Use the Create Issues from Templates Workflow

This guide shows you how to use the automated issue creation workflow.

## Quick Start

1. **Create your issue template(s)** in the `docs/` directory:
   ```bash
   docs/my-issue-1.md
   docs/my-issue-2.md
   ```

2. **Go to GitHub Actions**:
   - Navigate to your repository on GitHub
   - Click on the **Actions** tab
   - Select **"Create Issues from Templates"** from the workflows list

3. **Run the workflow**:
   - Click **"Run workflow"**
   - Choose your options:
     - **Template Pattern**: `docs/my-issue-*.md` (or whatever pattern matches your files)
     - **Dry Run**: Enable this first to preview what will be created
   - Click **"Run workflow"**

4. **Review the output**:
   - Click on the running workflow to see the logs
   - Review what issues will be created
   - If everything looks good, run again with dry run disabled

## Example: Creating Issues from PR Review Comments

Following the pattern from PR #10:

### Step 1: Create Template Files

Create `docs/pr8-unresolved-issue-1.md`:
```markdown
# Issue: Somerset incidents filtered out due to missing coordinates

## Source
PR #8 Review Comment: https://github.com/iammikek/floodwatch/pull/8#discussion_r2790180053

## Status
Resolved

## Description
Current implementation keeps incidents without coordinates (by design) so that
Somerset Council roadworks are retained when proximity filtering is enabled.
No bug to file; original concern is resolved.

## Location
- **File**: `app/Roads/Services/RoadIncidentOrchestrator.php`
- **Function**: `filterIncidentsByProximity`

## Impact
- N/A

## Labels
- status: resolved
```

### Step 2: Run the Workflow

1. Go to **Actions** → **Create Issues from Templates**
2. Set **Template Pattern** to: `docs/pr8-unresolved-issue-*.md`
3. Enable **Dry Run** to preview
4. Review the output
5. Run again with **Dry Run** disabled to create the issues

### Step 3: Verify

The workflow will create GitHub issues with:
- Title from the `# Issue:` heading
- Labels from the `## Labels` section
- Full template content as the issue body

## Template Format Requirements

Your template files must:

1. Have a title in one of these formats:
   ```markdown
   # Issue: Your Title Here
   ```
   OR
   ```markdown
   # Your Title Here
   ```

2. (Optional) Include labels in this format:
   ```markdown
   ## Labels
   - bug
   - priority: high
   - component: ui
   ```

## Advanced Usage

### Custom Patterns

You can use any file glob pattern:

- All issue templates in docs: `docs/*-issue-*.md`
- All templates for a specific PR: `docs/pr10-*.md`
- All markdown files: `docs/*.md`
- Templates in any subdirectory: `docs/**/*-issue-*.md`

### Dry Run Mode

Always use dry run mode first to:
- Verify your templates are formatted correctly
- Check what issues will be created
- Confirm labels will be applied correctly
- Avoid creating duplicate issues

### Duplicate Prevention

The workflow automatically checks for existing issues with the same title.
If an issue already exists, it will be skipped.

## Troubleshooting

**Template not found:**
- Check your file pattern matches your template locations
- Ensure files are committed to the repository
- Verify the path is relative to the repository root

**Title not extracted:**
- Ensure your template has a `# Issue:` heading or a `#` heading
- Check for proper markdown formatting (# must be at start of line)

**Labels not applied:**
- Verify you have a `## Labels` section
- Ensure labels are formatted as a bulleted list: `- label-name`
- Check that label names don't have extra spaces

**Permission errors:**
- The workflow requires write access to issues
- Check repository settings → Actions → General → Workflow permissions

## Example Workflow Output

```
🔍 Searching for issue templates matching pattern: docs/*-issue-*.md
📋 Found 2 template file(s)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📄 Processing: ./docs/pr8-unresolved-issue-1.md
  Title: Somerset incidents filtered out due to missing coordinates
  Labels: bug,priority: high,component: road-incidents
  ✅ Creating issue...
  ✨ Created issue: https://github.com/iammikek/floodwatch/issues/42

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📄 Processing: ./docs/pr8-unresolved-issue-2.md
  Title: Missing Str import in Blade template
  Labels: bug,priority: medium,component: ui,frontend
  ✅ Creating issue...
  ✨ Created issue: https://github.com/iammikek/floodwatch/issues/43

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Done processing templates!
```

## Tips

1. **Start with dry run** to preview changes
2. **Use descriptive filenames** to make templates easy to find
3. **Include context** in your templates (PR links, file locations, etc.)
4. **Check existing issues** before running to avoid duplicates
5. **Review created issues** after running to ensure they're correct

## Related Documentation

- Full workflow documentation: [.github/workflows/README-create-issues.md](../.github/workflows/README-create-issues.md)
- Example templates: See PR #10

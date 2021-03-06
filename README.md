# github-security-jira

Create JIRA tickets for security updates for projects used on a Drupal site.

This CLI tool compares modules, themes and core version used on a Drupal site
with information about projects, versions and security updates provided by
Drupal.org.

When a security update for a used module is detected a corresponding ticket is
created in JIRA.

This tool can be run on as is but also provides integration with GitHub where it
can be run using actions.

If JIRA ticket for a security update already exists it will not be recreated.

## Usage

1. Install the project and its dependencies using Composer
2. Configure the tool with environment variables or an `.env` file. The
   following keys are supported:

   - `DRUPAL_HOST`: The host name of the Drupal site to check, e.g. `reload.dk`
     (**REQUIRED**)
   - `SYSTEM_STATUS_TOKEN`: The token used to retrieve data exposed by the
     System Status module. This is the part of the site UUID *before* the `-` as
     reported on <https://example.com/admin/config/system/system-status>
     (**REQUIRED**)
   - `SYSTEM_STATUS_KEY`: The key used to decrypt data exposed by the System
     Status module (**REQUIRED**). This is the part of the site UUID *after* the
     `-` as reported on <https://example.com/admin/config/system/system-status>
   - `JIRA_HOST`: The endpoint for your JIRA instance, e.g.
     <https://reload.atlassian.net> (**REQUIRED**)
   - `JIRA_USER`: The ID of the JIRA user which is associated with `JIRA_TOKEN`
     e.g. 'someuser@reload.dk' (**REQUIRED**)
   - `JIRA_PROJECT`: The project key for the Jira project where issues should be
     created, e.g. `TEST` or `ABC`. (**REQUIRED**)
   - `JIRA_ISSUE_TYPE`: Type of issue to create, e.g. `Security`. Defaults to
     `Bug`. (*Optional*)
   - `JIRA_WATCHERS`: JIRA users to add as watchers to tickets. Separate
     multiple watchers with comma (no spaces).
   - `JIRA_RESTRICTED_COMMENT_ROLE`: A comment with restricted visibility to
     this role is posted with info about who was added as watchers to the issue.
     Defaults to `Developers`. (*Optional*)
   - `DRY_RUN`: Do not actually create any tickets but report that they would be
     created. Defaults to `FALSE`. (*Optional*)

3. Run `./bin/drupal-security-jira sync`

## Setup on GitHub Actions

You need the following pieces:

1. A Drupal site with the [System status module](https://www.drupal.org/project/system_status)
   installed.
2. Repository secrets containing site token, site key JIRA API token,
   respectively.
3. A workflow file which runs the action on a schedule, continually creating new
   tickets when necessary.

### Repo secrets

The `reload/github-security-jira` action requires you to
[create three encrypted secrets](https://help.github.com/en/actions/automating-your-workflow-with-github-actions/creating-and-using-encrypted-secrets#creating-encrypted-secrets)
in the repo:

1. `JiraApiToken` containing an [API Token](https://confluence.atlassian.com/cloud/api-tokens-938839638.html)
   for the JIRA user that should be used to create tickets.
2. `SystemStatusToken` containing the part of the site UUID before the `-` as
   reported on `/admin/config/system/system-status`
3. `SystemStatusKey` containing the part of the site UUID after the `-` as
   reported on `/admin/config/system/system-status`

### Workflow file setup

The [GitHub workflow file](https://help.github.com/en/actions/automating-your-workflow-with-github-actions/configuring-a-workflow#creating-a-workflow-file)
should reside in any repo where you want to sync security alerts with Jira.

Here is an example setup which runs this action every 6 hours and also
exposes a `workflow_dispatch` event for manual triggering a run.

The manual run is useful to perform checks in between the 6 hour
intervals in cases of very critical security issues and for testing
(the dry run mode).

```yaml
name: Drupal Security Alerts for Jira

on:
  workflow_dispatch:
    inputs:
      dry_run:
        description: Dry run
        required: true
        default: "1"
  schedule:
    - cron: "0 */6 * * *"

jobs:
  syncDrupalSecurityUpdates:
    runs-on: ubuntu-latest
    steps:
      - name: "Sync JIRA security issues from Drupal security updates"
        uses: reload/drupal-security-jira@main
        env:
          DRUPAL_HOST: example.com
          SYSTEM_STATUS_TOKEN: ${{ secrets.SystemStatusToken }}
          SYSTEM_STATUS_KEY: ${{ secrets.SystemStatusKey }}
          JIRA_TOKEN: ${{ secrets.JiraApiToken }}
          JIRA_HOST: https://reload.atlassian.net
          JIRA_USER: someone@reload.dk
          JIRA_PROJECT: ABC
          JIRA_ISSUE_TYPE: Security
          JIRA_WATCHERS: customer@example.com,boss@example.com
          DRY_RUN: ${{ github.event.inputs.dry_run || '0' }}
```

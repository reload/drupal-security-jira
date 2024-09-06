# drupal-security-jira

> [!Note]
> The `v2` version of this action is designed to work with the
> [Project Versions](https://www.drupal.org/project/project_versions)
> Drupal module.
>
> The legacy version that works with the [System Status
> module](https://www.drupal.org/project/system_status) can be found
> on the [main
> branch](https://github.com/reload/drupal-security-jira/tree/main).

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
   - `URL_TOKEN`: The URL token used to retrieve data exposed by the
     Project Versions module. See <https://example.com/admin/config/system/project-versions>
     (**REQUIRED**)
   - `ENCRYPTION_KEY`: The key used to decrypt data exposed by the
     Project Versions module (**REQUIRED**). See
     <https://example.com/admin/config/system/project-versions>
   - `JIRA_HOST`: The endpoint for your JIRA instance, e.g.
     <https://reload.atlassian.net> (**REQUIRED**)
   - `JIRA_USER`: The ID of the JIRA user which is associated with
     `JIRA_TOKEN` e.g. '<someuser@reload.dk>' (**REQUIRED**)
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

1. A Drupal site with the [Project Versions module](https://www.drupal.org/project/project_versions)
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
2. `PROJECT_VERSIONS_URL_TOKEN` from `/admin/config/system/project-versions`
3. `PROJECT_VERSIONS_ENCRYPTION_KEY` from `/admin/config/system/project-versions`

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
        uses: reload/drupal-security-jira@v2
        env:
          DRUPAL_HOST: example.com
          URL_TOKEN: ${{ secrets.PROJECT_VERSIONS_URL_TOKEN }}
          ENCRYPTION_KEY: ${{ secrets.PROJECT_VERSIONS_ENCRYPTION_KEY }}
          JIRA_TOKEN: ${{ secrets.JiraApiToken }}
          JIRA_HOST: https://reload.atlassian.net
          JIRA_USER: someone@reload.dk
          JIRA_PROJECT: ABC
          JIRA_ISSUE_TYPE: Security
          JIRA_WATCHERS: customer@example.com,boss@example.com
          DRY_RUN: ${{ github.event.inputs.dry_run || '0' }}
```

# AakSamlBundle

[![Release](https://img.shields.io/github/v/release/itk-kimai/AakSamlBundle?style=flat-square)](https://github.com/itk-kimai/AakSamlBundle/releases)
[![PHP](https://img.shields.io/github/actions/workflow/status/itk-kimai/AakSamlBundle/php.yaml?style=flat-square&logo=github&label=PHP)](https://github.com/itk-kimai/AakSamlBundle/actions/workflows/php.yaml)
[![Tests](https://img.shields.io/github/actions/workflow/status/itk-kimai/AakSamlBundle/test.yaml?style=flat-square&logo=github&label=tests)](https://github.com/itk-kimai/AakSamlBundle/actions/workflows/test.yaml)
[![Kimai](https://img.shields.io/badge/Kimai-%E2%89%A5%202.61-3AA5A0?style=flat-square)](https://www.kimai.org/)
[![License](https://img.shields.io/github/license/itk-kimai/AakSamlBundle?style=flat-square)](https://github.com/itk-kimai/AakSamlBundle/blob/main/LICENSE)

Bundle to handle mapping between City of Aarhus SAML claims and Kimai team structure.

Developed as a [Kimai](https://www.kimai.org/) plugin following the [developer documentation](https://www.kimai.org/documentation/plugins.html)

## Mapping SAML claims to Kimai entities

When a user logs in the following is created or updated. This is done by listening for a
`Symfony\Component\Security\Http\Event\CheckPassportEvent` in `KimaiPlugin\AakSamlBundle\EventSubscriber\CheckPassportEventSubscriber`
which in turn delegates the heavy lifting to `KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService`.

### `App\Entity\Team`

- `Office (orgUnitId, personaleLederUPN)` -> `name`

We map `Office` (e.g. "ITK Development") to a Kimai team. Kimai has a unique constraint on team names, so we append the
org-unit id and the manager ("personaleleder") email to ensure uniqueness, e.g. `ITK Development (6530, john@example.org)`
(or `ITK Development (6530)` when there is no manager). The name is truncated to Kimai's 100-character limit while the
uniqueness suffix is kept intact.

- The team MUST have one team lead only.
- The team lead MUST be the user with email matching the `personaleLederUPN` SAML claim.

### `KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta`

- `Office` -> `team`
- SAML claims -> entity fields

This plugin entity maps AAK organisation values to Kimai Teams. Most claims are mapped 1:1 through a `SamlDTO` object.
Note that `id`s for the various "departments" are given as a list in `extensionAttribute7`, e.g. `1001;1004;1012;1103;6530`.
These are split and handled as individual IDs.

- There MUST be exactly one `AakSamlTeamMeta` entity for each Kimai team created through SAML.
- The `AakSamlTeamMeta` entity MUST be updated with SAML claims on each login.

### `App\Entity\User` (Team Lead)

- `personaleLederUPN` -> `username`
- `personaleLederUPN` -> `email`
- `personaleLederDisplayName` -> `alias`

A user is created/updated for the manager. The user is added as "Team Lead" to the `Team` and given the `ROLE_TEAMLEAD`.
If the `Team` have other team leads they are removed to handle situations where the manager changes.

- It is UNKNOWN if a user can be team lead for multiple teams but we allow it if it happens.
- Any team leads removed from the team MUST have `ROLE_TEAMLEAD` removed UNLESS they are team leads for other teams.

### `App\Entity\User` (User)

- `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress` -> `username`
  (set by Kimai from `kimai.saml.username_attribute`, not the attribute mapping;
  a `kimai: username` mapping entry is forbidden as of Kimai 2.61)
- `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress` -> `email`
- `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name` -> `alias`
- `Office` -> `title` (Kimai "title" field renamed in the UI)
- `http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname (az)` -> `account number` ("Staff number"
  in the UI)
- TeamLead User -> `supervisor`

Kimai's SAML integration handles creating/finding the User logging in. We only ensure that claims and team are
mapped correctly. The user is added as "Member" to the team. If the user is a member of other teams these memberships
are removed.

- The user MUST be a private member of _one_ team only.
- The user MUST be a private member of the team matching the `office` / `personaleLederUPN` SAML claim.

## Implementation Notes

All data sync happens on login through claims. This means

- We have no data on users or teams prior to login
- We cannot delete or disable users (@todo implement delta sync)
- A manager logging in prior to any private team members will have `ROLE_TEAMLEAD` but employees will only be created as
  team members when they log in
- SAML claims are unbounded, so values are guarded against Kimai's field limits before saving: `title` (50), `alias`
  (60), `account` (30) and team `name` (100) are truncated (team names keep their uniqueness suffix). The manager email
  backing the unique `username`/`email` cannot be truncated safely, so a login fails with a clear error if it exceeds
  the 64-character limit

## Claims structure for reference

```json
{
  "http:\/\/schemas.microsoft.com\/ws\/2008\/06\/identity\/claims\/windowsaccountname": [
    "az1234"
  ],
  "http:\/\/schemas.xmlsoap.org\/ws\/2005\/05\/identity\/claims\/name": [
    "Jane Doe"
  ],
  "http:\/\/schemas.xmlsoap.org\/ws\/2005\/05\/identity\/claims\/emailaddress": [
    "jane@example.org"
  ],
  "companyname": [
    "Aarhus Kommune"
  ],
  "division": [
    "Kultur og Borgerservice"
  ],
  "department": [
    "Borgerservice og Biblioteker"
  ],
  "extensionAttribute12": [
    "ITK"
  ],
  "Office": [
    "ITK Development"
  ],
  "extensionAttribute7": [
    "1001;1004;1012;1103;6530"
  ],
  "personaleLederUPN": [
    "john@example.org"
  ],
  "personaleLederDisplayName": [
    "John Doe"
  ],
  "employeeList": [
    "abc@example.org;def@example.org;hij@example.org;klm@example.org"
  ]
}
```

## Development

``` shell
git clone --branch develop https://github.com/itk-kimai/AakSamlBundle var/plugins/AakSamlBundle
bin/console kimai:reload --no-interaction
```

Rather that hard copying plugin assets (cf. [Installation](#installation) above), you can run

``` shell
bin/console assets:install --symlink
```

to [symlink](https://en.wikipedia.org/wiki/Symbolic_link) the `public` folder.

### Coding standards and tooling

A `docker-compose.yml` file with a PHP 8.4 image is included in this project.
A [Taskfile](https://taskfile.dev/) is used to run common development tasks.

Set up the project (start the containers and install dependencies) with

``` shell
task setup
```

Run all CI checks locally (coding standards, static analysis):

``` shell
task pr:actions
```

Check all coding standards (PHP, Markdown, YAML, composer):

``` shell
task lint
```

Fix coding standards:

``` shell
task lint:php:fix
task lint:markdown:fix
task lint:yaml:fix
```

Run static analysis:

``` shell
task analyze:php
```

Run the tests:

``` shell
task test
```

Run `task --list` to see all available tasks.

_Note_: During development you should remove the `vendor/` folder to not confuse Kimai's autoloading.

## Installation

Requires Kimai >= 2.61 (enforced via `extra.kimai.require`).

Download [a release](https://github.com/itk-kimai/AakSamlBundle/releases) and move it to `var/plugins/`. Or check out
a release tag from this the repository in the `var/plugins/` repository.

```shell
bin/console kimai:bundle:aak-saml:install --no-interaction
bin/console kimai:reload --no-interaction
```

See [Install and update Kimai plugins](https://www.kimai.org/documentation/plugin-management.html) for details.

Edit your [`local.yaml`](https://www.kimai.org/documentation/local-yaml.html#localyaml) and set the `kimai:saml` config.
See [local.yaml.example](https://github.com/itk-kimai/kimai-docker/blob/main/local.yaml.example)

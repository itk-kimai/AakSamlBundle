# AakSamlBundle

Bundle to handle mapping between City of Aarhus SAML claims and Kimai team structure.

Developed as a [Kimai](https://www.kimai.org/) plugin following the [developer documentation](https://www.kimai.org/documentation/plugins.html)

## Mapping SAML claims to Kimai entities

When a user logs in the following is created or updated. This is done by listening for a
`Symfony\Component\Security\Http\Event\CheckPassportEvent` in `KimaiPlugin\AakSamlBundle\EventSubscriber\CheckPassportEventSubscriber`
which in turn delegates the heavy lifting to `KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService`.

### `App\Entity\Team`

- `Office (officeId)` -> `name`

We map `Office` (e.g. "ITK Development") to a Kimai team. Kimai has a unique constraint on team names. We include the
manager ("personaleleder") email to ensure uniqueness. E.g. "ITK Development (<jane@examlpe.org>)"

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
git clone --branch develop https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle var/plugins/AarhusKommuneBundle
bin/console kimai:reload --no-interaction
```

Rather that hard copying plugin assets (cf. [Installation](#installation) above), you can run

``` shell
bin/console assets:install --symlink
```

to [symlink](https://en.wikipedia.org/wiki/Symbolic_link) the `public` folder.

### Coding standards

``` shell
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer install
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer normalize
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer coding-standards-apply
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer coding-standards-check
```

``` shell
docker run --platform=linux/amd64 --rm --volume "$(pwd):/md" peterdavehello/markdownlint markdownlint --ignore LICENSE.md --ignore vendor/ '**/*.md' --fix
docker run --platform=linux/amd64 --rm --volume "$(pwd):/md" peterdavehello/markdownlint markdownlint --ignore LICENSE.md --ignore vendor/ '**/*.md'
```

``` shell
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer install
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer code-analysis
```

_Note_: During development you should remove the `vendor/` folder to not confuse Kimai's autoloading.

## Installation

Download [a release](https://github.com/itk-kimai/AakSamlBundle/releases) and move it to `var/plugins/`. Or check out
a release tag from this the repository in the `var/plugins/` repository.

```shell
bin/console kimai:bundle:aak-saml:install --no-interaction
bin/console kimai:reload --no-interaction
```

See [Install and update Kimai plugins](https://www.kimai.org/documentation/plugin-management.html) for details.

Edit your [`local.yaml`](https://www.kimai.org/documentation/local-yaml.html#localyaml) and set the `kimai:saml` config.
See [local.yaml.example](https://github.com/itk-kimai/kimai-docker/blob/main/local.yaml.example)

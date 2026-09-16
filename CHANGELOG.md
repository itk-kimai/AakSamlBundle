# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.2] - 2026-09-16

* [PR-18](https://github.com/itk-kimai/AakSamlBundle/pull/18)
  Move the GitHub actions to their current versions, and re-copy the `changelog`, `markdown`,
  `yaml` and `composer` workflows from the ITK templates. The composer workflow stays
  repo-specific: the template audits a committed `composer.lock`, which a library does not ship,
  so the audit job installs first and audits the result.
* [PR-17](https://github.com/itk-kimai/AakSamlBundle/pull/17)
  * Fix `AakSamlTeamMeta::setValues()` leaving the levels below the org unit uninitialised, so
    reading them threw "must not be accessed before initialization" for an org unit outside the
    claimed hierarchy.
  * Fix the team lead swap saving the new team lead instead of the demoted one when removing
    `ROLE_TEAMLEAD`.
  * Cover `SamlClaimsLogger`, `CheckPassportEventSubscriber` and `AakSamlTeamMeta`, and the team
    creation, team lead swap, membership pruning and manager creation paths in
    `SamlDataHydrateService`.
* [PR-16](https://github.com/itk-kimai/AakSamlBundle/pull/16)
  * Raise static analysis to PHPStan level 9 with strict rules, deprecation rules and
    `bleedingEdge`.
  * Compare organization ids strictly when resolving the team depth, and filter the employee list
    explicitly.
  * Save new team lead users through the public `UserService::saveUser()` instead of Kimai's
    `@internal` `saveNewUser()`.
  * Widen the coverage source to `Entity` and `EventSubscriber`; it only listed `Service`.
* Sync the README with current behaviour and add status badges.

## [1.4.1] - 2026-07-02

* [PR-15](https://github.com/itk-kimai/AakSamlBundle/pull/15)
  Rename the `tests` directory to `Tests`, to comply with Kimai's PSR-4 autoloading.

## [1.4.0] - 2026-07-02

* [PR-13](https://github.com/itk-kimai/AakSamlBundle/pull/13)
  Require Kimai >= 2.61 through `extra.kimai.require`, and document that the SAML user
  identifier comes from `kimai.saml.username_attribute` rather than the attribute mapping.
* [PR-12](https://github.com/itk-kimai/AakSamlBundle/pull/12)
  Guard user and team values against Kimai's length limits, to avoid failed logins.
* [PR-11](https://github.com/itk-kimai/AakSamlBundle/pull/11)
  Fix the claims log discarding exception messages, which were truncated away.
* [PR-10](https://github.com/itk-kimai/AakSamlBundle/pull/10)
  Add the PHPUnit setup and unit tests for `SamlDTO`.
* [PR-9](https://github.com/itk-kimai/AakSamlBundle/pull/9)
  Align the dev tooling and release setup with AarhusKommuneBundle.

## [1.3.1] - 2025-05-04

* Add the missing version number to `composer.json`.

## [1.3.0] - 2024-09-09

* [PR-7](https://github.com/itk-kimai/AakSamlBundle/pull/7)
  Add a command to batch update users from the claims log.

## [1.2.1] - 2024-09-09

* [PR-6](https://github.com/itk-kimai/AakSamlBundle/pull/6)
  Log the last SAML login datetime, and add a view for easier debugging.

## [1.2.0] - 2024-07-12

* [PR-5](https://github.com/itk-kimai/AakSamlBundle/pull/5)
  Adapt the SAML login to the `personaleLeder***` manager claims.

## [1.1.0] - 2024-07-01

* [PR-2](https://github.com/itk-kimai/AakSamlBundle/pull/2)
  Map teams for team leads, with their teams and roles.

## [1.0.0] - 2024-06-26

* [PR-3](https://github.com/itk-kimai/AakSamlBundle/pull/3)
  Log the SAML claims on login when they change, for debugging.
* [PR-1](https://github.com/itk-kimai/AakSamlBundle/pull/1)
  Add the plugin: the SAML claim mapping to Kimai users and teams.

[Unreleased]: https://github.com/itk-kimai/AakSamlBundle/compare/1.4.2...HEAD
[1.4.2]: https://github.com/itk-kimai/AakSamlBundle/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/itk-kimai/AakSamlBundle/compare/1.4.0...1.4.1
[1.4.0]: https://github.com/itk-kimai/AakSamlBundle/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/itk-kimai/AakSamlBundle/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/itk-kimai/AakSamlBundle/compare/1.2.1...1.3.0
[1.2.1]: https://github.com/itk-kimai/AakSamlBundle/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/itk-kimai/AakSamlBundle/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/itk-kimai/AakSamlBundle/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/itk-kimai/AakSamlBundle/releases/tag/1.0.0

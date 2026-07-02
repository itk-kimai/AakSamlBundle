# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.4.0 - 2026-07-02

- Align dev tooling and release setup with AarhusKommuneBundle
- Add unit tests for `SamlDTO`
- Fix claims-log discarding exception messages (they were truncated away)
- Guard user and team values against Kimai's length limits to avoid failed logins
- Document that the SAML user identifier comes from `kimai.saml.username_attribute`
  rather than the attribute mapping (required by Kimai 2.61)
- Require Kimai >= 2.61 (`extra.kimai.require`)

## 1.3.1 - 2025-05-04

- Add missing version number to composer.json

## 1.3.0 - 2024-09-09

- Add command to update all users from their logged claims

## 1.2.1 - 2024-09-06

- Add logging og last SAML login datetime. Add view for easier debugging.

## 1.2.0 - 2024-06-26

- Adapt SAML login to `personaleLeder***` claims

## 1.1.0 - 2024-07-01

- Map teams for team leads

## 1.0.0 - 2024-06-26

- Plugin setup
- Mapping SAML claims to Kimai Users/Teams
- Mapping Team Leads
- Logging claims for debugging

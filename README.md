# AakSamlBundle
Bundle to handle mapping between City of Aarhus SAML claims and Kimai team structure.

Developed as a [Kimai](https://www.kimai.org/) plugin following the [developer documentation](https://www.kimai.org/documentation/plugins.html)

## Mapping SAML claims to Kimai entities

When a user logs in the following is created or updated. This is done by listening for a
`Symfony\Component\Security\Http\Event\CheckPassportEvent` in `KimaiPlugin\AakSamlBundle\EventSubscriber\CheckPassportEventSubscriber` 
which in turn delegates the heavy lifting to `KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService`.

### `App\Entity\Team`
- Office (`officeId`) -> `name`

We map `Office` (e.g. "ITK Development") to a Kimai team. Kimai has a unique constraint on team names. We include the id
to ensure uniqueness. E.g. "ITK Development (6530)"

- The team MUST have one team lead only.
- The team lead MUST be the user with email matching the `managerUPN` SAML claim.

### `KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta`
- Office -> `team`
- SAML claims -> entity fields

This plugin entity maps AAK organisation values to Kimai Teams. Most claims are mapped 1:1 through a `SamlDTO` object. 
Note that `id's` for the various "departments" are given as a list in `extensionAttribute7`, e.g. `1001;1004;1012;1103;6530`. 
These are split and handled as individual id's.

- There MUST be exactly one `AakSamlTeamMeta` entity for each Kimai team created through SAML.
- The `AakSamlTeamMeta` entity MUST be updated with SAML claims on each login.

### `App\Entity\User` (Team Lead)
- managerUPN -> `username`
- managerUPN -> `email`
- managerdisplayname -> `alias`

A user is created/updated for the manager. The user is added as "Team Lead" to the `Team` and given the `ROLE_TEAMLEAD`.
If the `Team` have other team leads they are removed to handle situations where the manager changes.

- It is UNKNOWN if a user can be team lead for multiple teams.
- Any team leads removed from the team MUST have `ROLE_TEAMLEAD` removed UNLESS they are team leads for other teams.

### `App\Entity\User` (User)
- "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress" -> `username`
- "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress" -> `email`
- "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name" -> `alias` 
- "Office" -> `title` (Kimai "title" field renamed in the UI)
- "http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname" (az) -> `account number` ("Staff number" in the UI)
- TeamLead User -> `supervisor`

Kimai's SAML integration handles creating/finding the User logging in. We only ensure that claims and team are 
mapped correctly. The user is added as "Member" to the team. If the user is a member of other teams these memberships
are removed.

- The user MUST be a private member of _one_ team only.
- The user MUST be a private member of the team matching the `office` SAML claim.

## Implementation Notes

All data sync happens on login through claims. This means

- We have no data on users or teams prior to login
- We cannot delete or disable users (@todo implement delta sync)
- A manager logging in prior to any private team members will NOT have `ROLE_TEAMLEAD` until a team member has logged in.


## Claims structure for reference:

```php
array (
  'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname' => 
  array (
    0 => 'azxy123',
  ),
  'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => 
  array (
    0 => 'John Doe',
  ),
  'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 
  array (
    0 => 'john@example.com',
  ),
  'managerUPN' => 
  array (
    0 => 'jane@example.com',
  ),
  'managerdisplayname' => 
  array (
    0 => 'Jane Doe',
  ),
  'companyname' => 
  array (
    0 => 'Aarhus Kommune',
  ),
  'division' => 
  array (
    0 => 'Kultur og Borgerservice',
  ),
  'department' => 
  array (
    0 => 'Borgerservice og Biblioteker',
  ),
  'extensionAttribute12' => 
  array (
    0 => 'ITK',
  ),
  'Office' => 
  array (
    0 => 'ITK Development',
  ),
  'extensionAttribute7' => 
  array (
    0 => '1001;1004;1012;1103;6530',
  ),
  'sessionIndex' => '104ec668-7e72-46e3-9bbb-e871337f45eb',
)
```

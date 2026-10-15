# Doctor infrastructure

`UnavailableClinicalStatusSource` deliberately returns no authoritative clinical
evidence. The additive guard/observation tables preserve case serialization and
observation provenance; their existence does not establish reporting authority.
Production migrations and clinical integration require separate authorization.

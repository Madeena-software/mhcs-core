# Doctor module

Doctor owns the bounded clinical-finalization guard used by controlled technical
reopen. It owns a case lock and immutable status observations, not clinical
reports, accounts, signatures, publication or a reporting workflow.

`ClinicalStatusSource` defaults to unavailable. A registry row, absent report or
missing finalization timestamp cannot authorize reopen. A later authoritative
adapter must prove exact case/source/handoff coverage, complete reporting-source
coverage, freshness and transaction coordination. External sources without
reliable coordination must return unknown. Ordinary workspace privileges cannot
populate or override clinical evidence.

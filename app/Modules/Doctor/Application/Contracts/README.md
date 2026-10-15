# Doctor application contracts

`ClinicalReopenGuard` initializes the unknown case guard and checks controlled
reopen within the caller's workspace transaction. `ClinicalStatusSource` is a
trusted adapter contract; its default implementation returns unavailable.

Reporting writers must coordinate on the same case guard row and validate the
current technical handoff. When both locks are needed, acquire the Operator
workspace lock first (through `PendingRadiographerHandoff::isCurrent`) and the
Doctor guard second. No reporting writer or external clinical integration is
implemented here. Synthetic adapters live under tests and establish no real
clinical authority.

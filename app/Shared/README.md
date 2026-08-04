# Shared boundary

Shared contains only cross-cutting primitives and infrastructure used by all
modules. Business rules remain in their owning module, and Shared must not
depend on `App\\Modules`.

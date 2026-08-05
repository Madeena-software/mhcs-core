# MHCS Core

MHCS Core is one Laravel modular monolith containing the Member, Operator,
Doctor, and Image Gateway modules. The Shared boundary contains only genuine
cross-cutting primitives and infrastructure; business rules remain in their
owning module.

## Stack

- PHP `^8.4`
- Laravel `^13.8`
- Filament `^5.0` (installed; product panels are added by later work packages)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Run the local web server on port `8013` with:

```bash
php artisan serve --host=0.0.0.0 --port=8013
```

Run the foundation checks with:

```bash
php artisan test
```

WP-01 provides the application foundation, module boundaries, local contracts,
transactional events, and idempotency infrastructure. Member, Operator,
Doctor, Image Gateway business workflows, user-facing surfaces, and external
adapters are implemented by later work packages.

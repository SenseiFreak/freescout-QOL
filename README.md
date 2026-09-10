# freescout-qol

A small Freescout module to add quality-of-life improvements.

Current features:
- Persist mailbox "Arrange by" preference per-user
- Adds a "New" dropdown on the mailbox topbar with quick links to create a ticket or contact

Installation (Laravel/Freescout module style):
1. Copy or install this package into your Freescout application (e.g., composer require vendor/freescout-qol or place files in a modules/ folder)
2. Add `FreescoutQOL\\ServiceProvider` to your application providers (if not auto-discovered)
3. Publish migrations and assets: php artisan vendor:publish --provider="FreescoutQOL\\ServiceProvider" --tag="migrations"
4. Run migrations: php artisan migrate
5. Publish JS/CSS: php artisan vendor:publish --provider="FreescoutQOL\\ServiceProvider" --tag="public"

Notes:
- This scaffold is minimal. You'll need to adapt controller/route middleware to match your Freescout installation.
- The JS expects an element with `data-qol-arrange-by` attribute for the arrange-by control.

Next steps:
- Wire UI into Freescout mailbox template
- Add tests and a composer package release flow

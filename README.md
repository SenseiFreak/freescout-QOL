# freescout-qol

A small Freescout module adding several quality-of-life improvements.

Current features:
1. **Persisted conversation sorting** – the "Arrange by" / column sort order (Subject, Number, Waiting Since) is
   saved per-user and re-applied automatically on every reload, instead of resetting to the default.
2. **"New" dropdown in the top bar** – adds a `New` menu next to the main navigation with quick links to create a
   Ticket (per mailbox) or a Contact.
3. **Accurate "Waiting Since" times** – forces FreeScout's `use_mail_date_on_fetching` setting on, so imported/bulk
   fetched emails are timestamped using the email's own `Date` header instead of the time they were fetched/imported.
4. **Freshdesk-style merge** – when two conversations are merged, the secondary conversation's customer is
   automatically kept as a CC on the primary conversation so future replies still reach them.
5. **Customer/Agent reply badges** – a small highlighted "Customer reply" / "Agent reply" label is shown under the
   sender's name and email in each thread, so you can tell replies apart at a glance.

## Layout

This repo ships the same feature set in two forms:

- **Top level** (`src/`, `routes/`, `resources/`, `public/`, `migrations/`, `tests/`, `composer.json`) – a
  standalone Composer package. Install via `composer require` into a FreeScout app; `FreescoutQOL\ServiceProvider`
  is auto-discovered from `composer.json`'s `extra.laravel.providers`.
- **`Modules/<folder>/`** (e.g. `Modules/Freescout-QoL/`) – the same functionality repackaged as a real FreeScout
  module (built on `nwidart/laravel-modules`, same shape as FreeScout's official modules). The folder can be named
  anything; the module's identity comes from `module.json`'s `"name"` (`Qol`) and `"alias"` (`qol`) fields, not the
  folder name. Use this to drop straight into a FreeScout installation's `Modules/` folder and activate/test from
  the Manage → Modules admin page.

```
Modules/<folder>/
  module.json                          Module manifest ("name": "Qol", "alias": "qol", "files": ["bootstrap.php"])
  bootstrap.php                        Manually requires + registers QolServiceProvider (no autoloading needed)
  Providers/QolServiceProvider.php      Registers routes/views/migrations + Eventy hooks
  Http/Controllers/PreferenceController.php
  Routes/web.php
  Resources/views/mailbox/topbar.blade.php
  Database/Migrations/...              user_preferences table
  Public/js/qol.js, Public/css/qol.css  Published to public/modules/qol/ on activation
  Tests/PreferenceControllerTest.php
```

Important: `module.json`'s `"providers"` array is intentionally **empty**. FreeScout/`nwidart/laravel-modules`
would otherwise try to instantiate `Modules\Qol\Providers\QolServiceProvider` directly via Composer's `Modules\`
PSR-4 autoloading, which fails with `Class "Modules\Qol\Providers\QolServiceProvider" not found` on hosts where
that autoload mapping is missing or stale (common on shared hosting without composer/SSH access). Instead,
`module.json`'s `"files"` entry runs `bootstrap.php`, which `require_once`s the class files directly and registers
the provider with `app()->register(...)` — this works regardless of Composer autoloading state.

## Installation (Composer package, top-level)

1. Copy or install this package into your Freescout application (e.g., composer require vendor/freescout-qol or place files in a modules/ folder)
2. Add `FreescoutQOL\\ServiceProvider` to your application providers (if not auto-discovered)
3. Publish migrations and assets:
   - `php artisan vendor:publish --provider="FreescoutQOL\\ServiceProvider" --tag="migrations"`
   - `php artisan vendor:publish --provider="FreescoutQOL\\ServiceProvider" --tag="public"`
4. Run migrations: `php artisan migrate`

## Installation (FreeScout module)

1. Copy the module folder (e.g. `Modules/Freescout-QoL`) into your FreeScout installation's `Modules/` directory
   (so you end up with `<freescout>/Modules/<folder>/module.json`). The folder name doesn't need to match `Qol`.
2. No `composer dump-autoload` is required — `bootstrap.php` loads the module's classes directly.
3. Run `php artisan migrate` (the migration is a no-op if `user_preferences` already exists) to create the table.
4. Go to **Manage → Modules** in FreeScout and activate **Qol**. Activating it creates the `public/modules/qol`
   symlink used to serve `qol.js` / `qol.css`.

Troubleshooting `Class "Modules\Qol\Providers\QolServiceProvider" not found`: this means `module.json` still lists
the provider under `"providers"` instead of using the `bootstrap.php`/`"files"` approach above — update
`module.json` to `"providers": []` and `"files": ["bootstrap.php"]`, then clear cached module data with
`php artisan optimize:clear` (or delete `bootstrap/cache/services.php` and any `*_module.php` file next to it).

Troubleshooting `require(.../Modules/<folder>): Failed to open stream`: FreeScout's bundled `nwidart/laravel-modules`
does **not** provide a `module_path($name, $subPath)` helper that appends a sub-path — `Module::getModulePath()`
only returns the module's root folder. `QolServiceProvider::boot()` therefore builds its `Routes/`, `Resources/views/`
and `Database/Migrations/` paths from `__DIR__` (relative to the provider file itself) instead of any module-path
helper, so it works regardless of the module's folder name or nwidart version.

Notes:
- All hooks are registered through FreeScout's `Eventy` action/filter system (`conversations.table_sorting`,
  `menu.append`, `conversation.merged`, `layout.head`, `layout.body_bottom`), so nothing needs to be manually pasted
  into FreeScout's own Blade templates.
- The reply badges (feature 5) are added client-side via `public/js/qol.js` (or the module's `Public/js/qol.js`)
  since thread markup is rendered by FreeScout core templates that this module does not own.

Next steps:
- Keep the top-level package and the `Modules/` folder in sync when making future changes to either.



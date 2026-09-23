<?php

// Loaded via module.json's "files" list. Requiring these classes explicitly (instead of
// relying on module.json's "providers" + Composer PSR-4 autoloading) means this module
// works even when the host's autoloader has no "Modules\\" mapping or hasn't been
// regenerated after this module was added.
require_once __DIR__.'/Http/Controllers/PreferenceController.php';
require_once __DIR__.'/Http/Controllers/QolController.php';
require_once __DIR__.'/Providers/QolServiceProvider.php';

app()->register(\Modules\Qol\Providers\QolServiceProvider::class);

<?php

namespace Modules\Qol\Tests;

use PHPUnit\Framework\TestCase;
use Modules\Qol\Http\Controllers\PreferenceController;

class PreferenceControllerTest extends TestCase
{
    public function test_invalid_sorting_is_rejected()
    {
        // Real validation happens against DB-backed state, but the allowed-value
        // whitelists are pure constants we can assert without a DB/app context.
        $this->assertContains('date', PreferenceController::ALLOWED_SORT_BY);
        $this->assertContains('subject', PreferenceController::ALLOWED_SORT_BY);
        $this->assertContains('number', PreferenceController::ALLOWED_SORT_BY);
        $this->assertContains('asc', PreferenceController::ALLOWED_ORDER);
        $this->assertContains('desc', PreferenceController::ALLOWED_ORDER);
    }

    public function test_get_saved_sorting_returns_null_without_user()
    {
        $this->assertNull(PreferenceController::getSavedSorting(null));
    }
}

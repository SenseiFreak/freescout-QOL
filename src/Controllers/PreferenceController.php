<?php

namespace FreescoutQOL\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PreferenceController
{
    const KEY_ARRANGE_BY = 'mailbox_arrange_by';
    const KEY_SORTING = 'mailbox_sorting';

    const ALLOWED_SORT_BY = ['subject', 'number', 'date'];
    const ALLOWED_ORDER = ['asc', 'desc'];

    public function getArrangeBy(Request $request)
    {
        $userId = Auth::id();

        return response()->json([
            'arrange_by' => self::getPreference($userId, self::KEY_ARRANGE_BY),
            'sorting' => self::getSavedSorting($userId),
        ]);
    }

    public function setArrangeBy(Request $request)
    {
        $userId = Auth::id();
        $value = $request->input('arrange_by');

        self::savePreference($userId, self::KEY_ARRANGE_BY, $value);

        // Also accept sort_by/order so the same endpoint can persist column sorting
        // used by the mailbox "Arrange by" / conversations table controls.
        $sortBy = $request->input('sort_by');
        $order = $request->input('order');

        if (in_array($sortBy, self::ALLOWED_SORT_BY, true) && in_array($order, self::ALLOWED_ORDER, true)) {
            self::savePreference($userId, self::KEY_SORTING, json_encode([
                'sort_by' => $sortBy,
                'order' => $order,
            ]));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Read the user's saved conversations table sorting (sort_by/order), validated
     * against the allowed values, or null if nothing/invalid is stored.
     */
    public static function getSavedSorting($userId)
    {
        if (!$userId) {
            return null;
        }

        $raw = self::getPreference($userId, self::KEY_SORTING);
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (empty($decoded['sort_by']) || empty($decoded['order'])) {
            return null;
        }

        if (!in_array($decoded['sort_by'], self::ALLOWED_SORT_BY, true)
            || !in_array($decoded['order'], self::ALLOWED_ORDER, true)
        ) {
            return null;
        }

        return [
            'sort_by' => $decoded['sort_by'],
            'order' => $decoded['order'],
        ];
    }

    protected static function getPreference($userId, $key)
    {
        if (!$userId) {
            return null;
        }

        $row = DB::table('user_preferences')
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        return $row ? $row->value : null;
    }

    protected static function savePreference($userId, $key, $value)
    {
        if (!$userId) {
            return;
        }

        DB::table('user_preferences')
            ->updateOrInsert(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
    }
}

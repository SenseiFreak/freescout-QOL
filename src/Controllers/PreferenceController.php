<?php

namespace FreescoutQOL\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PreferenceController
{
    public function getArrangeBy(Request $request)
    {
        $userId = Auth::id();
        $row = DB::table('user_preferences')
            ->where('user_id', $userId)
            ->where('key', 'mailbox_arrange_by')
            ->first();

        return response()->json([
            'arrange_by' => $row ? $row->value : null,
        ]);
    }

    public function setArrangeBy(Request $request)
    {
        $userId = Auth::id();
        $value = $request->input('arrange_by');

        DB::table('user_preferences')
            ->updateOrInsert(
                ['user_id' => $userId, 'key' => 'mailbox_arrange_by'],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );

        return response()->json(['ok' => true]);
    }
}

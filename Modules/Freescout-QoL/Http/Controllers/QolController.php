<?php

namespace Modules\Qol\Http\Controllers;

use App\Conversation;
use App\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QolController
{
    const SETTINGS_KEY = 'qol_settings';

    public static function defaultSettings()
    {
        return [
            'stay_on_ticket' => true,
            'gate_subject_editing' => true,
            'drag_drop_attachments' => true,
        ];
    }

    public static function getSettings()
    {
        $settings = self::defaultSettings();
        $raw = DB::table('user_preferences')
            ->where('user_id', 0)
            ->where('key', self::SETTINGS_KEY)
            ->value('value');

        $saved = $raw ? json_decode($raw, true) : null;
        if (is_array($saved)) {
            foreach ($settings as $key => $default) {
                if (array_key_exists($key, $saved)) {
                    $settings[$key] = (bool) $saved[$key];
                }
            }
        }

        return $settings;
    }
    public function createContact()
    {
        return view('qol::contact.create');
    }

    public function storeContact(Request $request)
    {
        $this->validateContact($request);

        $data = [
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
        ];
        $contact = $request->input('email')
            ? Customer::create($request->input('email'), $data)
            : Customer::createWithoutEmail($data);

        return redirect()->route('customers.update', ['id' => $contact->id])
            ->with('flash_success', __('Contact saved'));
    }

    public function bulkMerge(Request $request)
    {
        $ids = array_values(array_unique(array_filter((array) $request->input('conversation_ids'), 'is_numeric')));
        $primaryId = $request->input('primary_conversation_id');

        if (count($ids) < 2 || !in_array($primaryId, $ids)) {
            return response()->json(['message' => __('Select at least two conversations and choose the conversation to keep.')], 422);
        }

        $primary = Conversation::find($primaryId);
        $user = Auth::user();
        if (!$primary || !$user || !$user->can('view', $primary) || $primary->isChat()) {
            return response()->json(['message' => __('Not enough permissions')], 403);
        }

        foreach ($ids as $id) {
            if ((int) $id === (int) $primary->id) {
                continue;
            }

            $conversation = Conversation::find($id);
            if (!$conversation || !$user->can('view', $conversation) || $conversation->isChat()) {
                return response()->json(['message' => __('One or more selected conversations cannot be merged.')], 422);
            }
        }

        foreach ($ids as $id) {
            if ((int) $id !== (int) $primary->id) {
                $primary->mergeConversations(Conversation::find($id), $user);
            }
        }

        return response()->json(['ok' => true, 'message' => __('Conversations merged')]);
    }

    public function settings()
    {
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403);
        }

        return view('qol::settings', ['settings' => self::getSettings()]);
    }

    public function saveSettings(Request $request)
    {
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403);
        }

        $settings = [
            'stay_on_ticket' => $request->has('stay_on_ticket'),
            'gate_subject_editing' => $request->has('gate_subject_editing'),
            'drag_drop_attachments' => $request->has('drag_drop_attachments'),
        ];

        DB::table('user_preferences')->updateOrInsert(
            ['user_id' => 0, 'key' => self::SETTINGS_KEY],
            ['value' => json_encode($settings), 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect()->route('qol.settings')->with('flash_success', __('QoL settings saved'));
    }

    protected function validateContact(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'nullable|string|max:255|required_without:email',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:191|required_without:first_name',
        ]);
    }

    protected function validate(Request $request, array $rules)
    {
        app('validator')->make($request->all(), $rules)->validate();
    }
}

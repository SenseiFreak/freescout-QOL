<?php

namespace Modules\Qol\Calendar;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketCalendar
{
    public static function remoteKey($source, $calendar, $remoteId)
    {
        return hash('sha256', json_encode([Auth::id(), $source, $calendar, $remoteId]));
    }

    public static function ready()
    {
        return Schema::hasColumn('qol_calendar_events', 'note_thread_id');
    }

    public static function rows($ticket)
    {
        abort_unless(Auth::user()->can('view', $ticket), 403);
        if (!self::ready()) { return []; }
        return DB::table('qol_calendar_events')->where('conversation_id', $ticket->id)->orderBy('starts_at')->get()->map(function ($row) {
            return self::present($row);
        })->all();
    }

    public static function present($row)
    {
        $zone = new \DateTimeZone($row->timezone);
        $start = (new \DateTimeImmutable($row->starts_at, new \DateTimeZone('UTC')))->setTimezone($zone);
        $end = (new \DateTimeImmutable($row->ends_at, new \DateTimeZone('UTC')))->setTimezone($zone);
        $user = $row->creator_name ? null : \App\User::find($row->user_id);
        $url = $row->remote_url;
        if ($url && !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['https', 'http'], true)) { $url = null; }
        return ['id' => $row->id, 'title' => $row->title, 'start' => $start->format('Y-m-d H:i'), 'end' => $end->format('Y-m-d H:i'),
            'timezone' => $row->timezone, 'source' => $row->source, 'url' => $url,
            'creator' => $row->creator_name ?: ($user ? $user->getFullName() : __('Former user')),
            'created_at' => $row->created_at, 'imported' => (bool) $row->imported];
    }

    /** Called inside the event transaction. A native internal note, never a customer email. */
    public static function note($ticket, $eventId)
    {
        $row = DB::table('qol_calendar_events')->where('id', $eventId)->first();
        $event = self::present($row);
        $provider = ['local' => 'FreeScout', 'google' => 'Google Calendar', 'microsoft' => 'Microsoft 365'][$row->source];
        $created = (new \DateTimeImmutable('now', new \DateTimeZone($row->timezone)))->format('Y-m-d H:i:s');
        $text = ($row->imported ? __('Existing calendar event linked to ticket') : __('Calendar event created')).': '.$row->title."\n"
            .__('When').': '.$event['start'].' – '.$event['end'].' ('.$row->timezone.")\n"
            .__('Calendar').': '.$provider."\n"
            .($row->imported ? __('Linked by') : __('Created by')).': '.Auth::user()->getFullName()."\n"
            .__('Recorded at').': '.$created.' ('.$row->timezone.')';
        $body = nl2br(e($text));
        if ($event['url']) { $body .= '<br><a href="'.e($event['url']).'">'.e(__('Open calendar event')).'</a>'; }
        $thread = \App\Thread::create($ticket, \App\Thread::TYPE_NOTE, $body, [
            'created_by_user_id' => Auth::id(), 'customer_id' => $ticket->customer_id,
            'user_id' => $ticket->user_id, 'source_via' => \App\Thread::PERSON_USER, 'source_type' => \App\Thread::SOURCE_TYPE_WEB,
        ]);
        DB::table('qol_calendar_events')->where('id', $eventId)->update(['note_thread_id' => $thread->id]);
        return ['id' => $thread->id, 'text' => $text, 'author' => Auth::user()->getFullName(), 'recorded_at' => $created.' '.$row->timezone];
    }
}

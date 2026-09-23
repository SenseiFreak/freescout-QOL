<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUnresolvedMailboxFolders extends Migration
{
    const FOLDER_TYPE_UNRESOLVED = 90;

    /**
     * Add one public, live Unresolved folder to every mailbox that already exists.
     */
    public function up()
    {
        if (!Schema::hasTable('mailboxes') || !Schema::hasTable('folders')) {
            return;
        }

        DB::table('mailboxes')->orderBy('id')->chunkById(100, function ($mailboxes) {
            foreach ($mailboxes as $mailbox) {
                $exists = DB::table('folders')
                    ->where('mailbox_id', $mailbox->id)
                    ->where('type', self::FOLDER_TYPE_UNRESOLVED)
                    ->whereNull('user_id')
                    ->exists();

                if (!$exists) {
                    DB::table('folders')->insert([
                        'mailbox_id'  => $mailbox->id,
                        'user_id'     => null,
                        'type'        => self::FOLDER_TYPE_UNRESOLVED,
                        'active_count'=> 0,
                        'total_count' => 0,
                    ]);
                }
            }
        });
    }

    /**
     * Keep the folder on uninstall so conversations and mailbox navigation are untouched.
     */
    public function down()
    {
        // Intentionally left in place.
    }
}

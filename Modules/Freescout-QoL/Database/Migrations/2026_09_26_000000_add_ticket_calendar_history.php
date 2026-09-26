<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTicketCalendarHistory extends Migration
{
    public function up()
    {
        Schema::table('qol_calendar_events', function (Blueprint $table) {
            $table->string('source', 20)->default('local');
            $table->text('calendar_id')->nullable();
            $table->text('remote_id')->nullable();
            $table->text('remote_url')->nullable();
            $table->string('remote_key', 64)->nullable()->unique();
            $table->unsignedInteger('note_thread_id')->nullable();
            $table->string('creator_name')->nullable();
            $table->boolean('imported')->default(false);
            $table->index('conversation_id');
        });
    }

    public function down()
    {
        Schema::table('qol_calendar_events', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropUnique(['remote_key']);
            $table->dropColumn(['source', 'calendar_id', 'remote_id', 'remote_url', 'remote_key', 'note_thread_id', 'creator_name', 'imported']);
        });
    }
}

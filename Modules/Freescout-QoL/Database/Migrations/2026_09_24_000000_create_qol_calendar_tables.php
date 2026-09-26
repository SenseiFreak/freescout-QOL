<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQolCalendarTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('qol_calendar_connections')) {
            Schema::create('qol_calendar_connections', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('provider', 20);
                $table->text('tokens');
                $table->timestamps();
                $table->unique(['user_id', 'provider']);
            });
        }
        if (!Schema::hasTable('qol_calendar_events')) {
            Schema::create('qol_calendar_events', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('conversation_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('timezone', 64);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('qol_calendar_events');
        Schema::dropIfExists('qol_calendar_connections');
    }
}

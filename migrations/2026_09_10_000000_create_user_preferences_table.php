<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPreferencesTable extends Migration
{
    public function up()
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('key', 191);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_preferences');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('note_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->decimal('rate', 12, 4)->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('note_comments');
        Schema::dropIfExists('folders');
    }
};

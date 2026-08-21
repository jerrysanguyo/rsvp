<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsvp_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rsvp_link_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_key');
            $table->boolean('will_attend');
            $table->unsignedTinyInteger('participant_count');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['rsvp_link_id', 'submission_key']);
            $table->index(['rsvp_link_id', 'will_attend']);
        });

        Schema::create('rsvp_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rsvp_response_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->boolean('will_attend');
            $table->timestamps();

            $table->index(['will_attend', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvp_participants');
        Schema::dropIfExists('rsvp_responses');
    }
};

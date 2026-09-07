<?php

use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Device tokens for native push.
 *
 * Deliberately NOT the existing `devices` table: that one is a browser-session
 * audit log keyed on session_id, feeding the Sessions settings screen. Reusing
 * it would tie a push token's lifetime to session termination, so signing out of
 * one browser would silently stop notifications on the phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Table::PUSH_TOKENS, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on(Table::USERS)->onDelete('cascade');

            // Unique on the token, not on (user, token): a device handed to
            // another person re-registers the same token, and it must move to
            // the new owner rather than deliver their predecessor's notifications.
            $table->string('token', 512)->unique();

            $table->string('platform', 16);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::PUSH_TOKENS);
    }
};

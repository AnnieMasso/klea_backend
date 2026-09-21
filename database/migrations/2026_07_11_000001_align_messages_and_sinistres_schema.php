<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'sender_id')) {
                $table->foreignId('sender_id')
                    ->nullable()
                    ->after('conversation_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('sinistres', function (Blueprint $table): void {
            $table->string('img2')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasColumn('messages', 'sender_id')) {
                $table->dropConstrainedForeignId('sender_id');
            }
        });

        Schema::table('sinistres', function (Blueprint $table): void {
            $table->string('img2')->nullable(false)->change();
        });
    }
};

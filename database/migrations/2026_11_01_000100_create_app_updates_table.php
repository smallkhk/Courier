<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History of one-click updates and rollbacks (Admin → Software updates).
        Schema::create('app_updates', function (Blueprint $table) {
            $table->id();
            $table->string('action', 16); // update | rollback
            $table->string('from_version', 32);
            $table->string('to_version', 32);
            $table->string('status', 16); // completed | failed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('backup')->nullable();
            $table->text('log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_updates');
    }
};

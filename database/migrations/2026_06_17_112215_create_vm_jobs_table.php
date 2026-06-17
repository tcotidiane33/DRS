<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['vm', 'ct']);
            $table->string('node')->nullable();
            $table->integer('vmid')->nullable();
            $table->enum('status', ['queued', 'running', 'done', 'error'])->default('queued');
            $table->integer('progress')->default(0);
            $table->text('message')->nullable();
            $table->json('params');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_jobs');
    }
};

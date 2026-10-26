<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_audits', function (Blueprint $table) {
            $table->id();
            // Soft deletes never remove the patient row, so the history survives the trash. A hard delete
            // (e.g. a future LGPD erasure) removes the history too: it holds the same personal data.
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            // Encrypted JSON (`encrypted:array` cast): old/new values include CPF, RG and phone.
            $table->text('changes');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Always null while the API has no authentication; ready for a future login.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['patient_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_audits');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            // Soft-deleting a patient keeps the consultations (hidden from the agenda); a hard delete (e.g. a
            // future LGPD erasure) removes them, like the audit history.
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // Local time in America/Sao_Paulo (the app timezone), like every other datetime column.
            $table->dateTime('scheduled_at');
            // Derived (scheduled_at + duration_minutes), kept by the model: the overlap check becomes a plain
            // range comparison, with no database-specific date arithmetic.
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            // Plain string (not a database enum): the values and transition rules live in App\Enums\ConsultationStatus.
            $table->string('status', 20);
            $table->string('professional_name');
            // Derived: trimmed, lower-cased name without accents. Matching that ignores case and accents
            // (overlap, agenda filter) the same way on MySQL and SQLite, whose LOWER()/LIKE only fold ASCII.
            $table->string('professional_key');
            $table->string('professional_crm', 20)->nullable();
            // Encrypted at rest (`encrypted` cast).
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Agenda (date range), patient listing / timeline, and overlap lookup per professional.
            $table->index('scheduled_at');
            $table->index(['patient_id', 'scheduled_at']);
            $table->index(['professional_key', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};

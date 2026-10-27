<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Path on the private disk (random name, encrypted contents); null = no photo.
            $table->string('photo_path')->nullable()->after('description');
            // Bumped on every upload/removal: `photo_url` carries it as ?v= so browsers drop the cached image.
            $table->unsignedInteger('photo_version')->default(0)->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_version']);
        });
    }
};

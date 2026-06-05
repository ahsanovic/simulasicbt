<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->string('content_type', 10)->default('text')->after('label');
            $table->string('image_path')->nullable()->after('content');
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'image_path']);
            $table->text('content')->nullable(false)->change();
        });
    }
};

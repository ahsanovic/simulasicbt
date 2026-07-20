<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('public_code', 32)->nullable()->unique()->after('public_livescore');
        });

        // Backfill a unique public code for every existing event so their public
        // livescore URLs keep working under the new /livescore/{public_code} scheme.
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

        foreach (DB::table('events')->pluck('id') as $id) {
            do {
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= $chars[random_int(0, strlen($chars) - 1)];
                }
            } while (DB::table('events')->where('public_code', $code)->exists());

            DB::table('events')->where('id', $id)->update(['public_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['public_code']);
            $table->dropColumn('public_code');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * An account for the owner of the panel: registration is off, and the
     * passwords of the existing accounts are lost. Their accounts stay as
     * they are.
     */
    public function up(): void
    {
        if (DB::table('users')->where('email', 'calories365.diary@gmail.com')->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'owner',
            'email' => 'calories365.diary@gmail.com',
            'password' => '$2y$12$ED05zHNFyF9TsOn8VOlA.OzRwZWpEoH3sUtDIleEdGoPGmMC2aHda',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'calories365.diary@gmail.com')->delete();
    }
};

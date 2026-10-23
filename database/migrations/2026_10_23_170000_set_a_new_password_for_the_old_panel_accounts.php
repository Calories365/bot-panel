<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The passwords of the old accounts of the panel are lost: they get the
     * password of the owner account.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('email', '!=', 'calories365.diary@gmail.com')
            ->update([
                'password' => '$2y$12$ED05zHNFyF9TsOn8VOlA.OzRwZWpEoH3sUtDIleEdGoPGmMC2aHda',
                'remember_token' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};

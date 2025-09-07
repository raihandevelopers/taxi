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
        if (Schema::hasTable('users')) {
            if (!Schema::hasColumn('users', 'ride_otp')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->integer('ride_otp')->after('mobile')->nullable();    
                });
            }

        }   
   }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

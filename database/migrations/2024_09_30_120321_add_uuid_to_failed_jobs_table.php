<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUuidToFailedJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('failed_jobs')){
            if(!Schema::hasColumn('failed_jobs','uuid')){
                Schema::table('failed_jobs', function (Blueprint $table) {
                    $table->uuid('uuid')->after('id')->unique();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasTable('failed_jobs')){
            if(Schema::hasColumn('failed_jobs','uuid')){
                Schema::table('failed_jobs', function (Blueprint $table) {
                    $table->dropColumn('uuid');
                });
            }
        }
    }
}

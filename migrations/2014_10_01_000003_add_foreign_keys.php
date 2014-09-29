<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeys extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tenantColumn = Config::get('laravel-multitenant::tenant_column');

        Schema::table('users', function($table)
        {
            $table->foreign($tenantColumn)
                ->references('id')->on('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function($table)
        {
            $table->dropForeign('users_'.$tenantColumn.'_foreign');
        });
    }
}

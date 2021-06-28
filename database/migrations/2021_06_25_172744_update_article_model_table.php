<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateArticleModelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article_model', function (Blueprint $table) {
            $table->string('tpl_class', 250)->nullable()->after('name');
            $table->string('tpl_content', 250)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article_model', function (Blueprint $table) {
            $table->dropColumn('tpl_class');
            $table->dropColumn('tpl_content');
        });
    }
}

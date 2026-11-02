<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A job of the 1C helper bot may open a session of Claude on the 1C server
     * for the owner instead of analysing: "window" for a question gathered
     * just now, "resume" for the session of an analysed question. A resume
     * has no batch of its own and answers the message its button was under.
     * The worker gives back where the session is.
     */
    public function up(): void
    {
        Schema::table('onec_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->change();
            $table->bigInteger('reply_to_message_id')->nullable()->after('batch_id');
            $table->string('attach_id')->nullable()->after('session_id');
            $table->string('folder', 1024)->nullable()->after('attach_id');
            $table->string('project_dir', 1024)->nullable()->after('folder');
            $table->boolean('window_opened')->nullable()->after('project_dir');
        });
    }

    public function down(): void
    {
        DB::table('onec_jobs')->whereNull('batch_id')->delete();

        Schema::table('onec_jobs', function (Blueprint $table) {
            $table->dropColumn(['reply_to_message_id', 'attach_id', 'folder', 'project_dir', 'window_opened']);
            $table->unsignedBigInteger('batch_id')->nullable(false)->change();
        });
    }
};

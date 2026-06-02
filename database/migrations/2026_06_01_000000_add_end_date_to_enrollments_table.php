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
    if (!Schema::hasColumn('enrollments', 'end_date')) {
      Schema::table('enrollments', function (Blueprint $table) {
        $table->date('end_date')->nullable()->after('enrollment_date');
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    if (Schema::hasColumn('enrollments', 'end_date')) {
      Schema::table('enrollments', function (Blueprint $table) {
        $table->dropColumn('end_date');
      });
    }
  }
};

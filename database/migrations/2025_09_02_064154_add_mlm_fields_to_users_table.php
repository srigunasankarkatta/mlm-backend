// database/migrations/2025_09_02_000002_add_mlm_fields_to_users_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // sponsor references another user
            $table->foreignId('sponsor_id')->nullable()->constrained('users')->onDelete('cascade');
            // package references packages
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
            $table->dropForeign(['package_id']);
            $table->dropColumn(['sponsor_id','package_id']);
        });
    }
};

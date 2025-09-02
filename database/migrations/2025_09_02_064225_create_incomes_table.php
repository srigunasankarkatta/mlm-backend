// database/migrations/2025_09_02_000003_create_incomes_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['direct','level','club','auto_pool']);
            $table->decimal('amount', 8, 2);
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('incomes');
    }
};

<?php

use App\Support\LicensePolicy\FeatureCatalogSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Licence policies (payload v2 of the .atgl format): the global default, one default per plan,
 * custom policies per user, the feature catalog, and an audit log of admin changes. Issued
 * licences record the limits and policy they were issued with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_policies', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64)->unique();              // global | plan:<code>
            $table->boolean('inherit_global')->default(false);   // plan rows: use the global policy
            $table->unsignedInteger('max_nodes')->default(0);    // 0 = unlimited
            $table->unsignedInteger('max_users')->default(0);    // 0 = unlimited
            $table->unsignedSmallInteger('validity_value')->default(12);
            $table->string('validity_unit', 10)->default('months'); // months | years
            $table->unsignedSmallInteger('activation_window_minutes')->default(180);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_license_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('max_nodes')->default(0);
            $table->unsignedInteger('max_users')->default(0);
            $table->unsignedSmallInteger('validity_value')->default(12);
            $table->string('validity_unit', 10)->default('months');
            $table->date('expires_on')->nullable();              // explicit expiry, instead of validity
            $table->unsignedSmallInteger('activation_window_minutes')->default(180);
            $table->json('features')->nullable();
            $table->uuid('install_id')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('policy_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 40);                  // license_policy | custom_license_policy | feature_catalog
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label');                     // e.g. "Global default", "Custom: ops@acme.test"
            $table->string('action', 20);                        // created | updated | revoked
            $table->string('field', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->dateTime('created_at');

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('feature_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();                 // from contracts FEATURES.md, never edited
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('default_enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('seats')->nullable()->change();  // v1 only
            $table->unsignedTinyInteger('format_version')->default(1)->after('id');
            $table->unsignedInteger('max_nodes')->nullable()->after('seats');
            $table->unsignedInteger('max_users')->nullable()->after('max_nodes');
            $table->string('policy_id', 64)->nullable()->after('features');
            $table->string('policy_type', 10)->nullable()->after('policy_id');
            $table->unsignedSmallInteger('validity_months')->nullable()->after('policy_type');
            $table->string('issued_by', 10)->default('user')->after('validity_months'); // user | admin
            $table->index('policy_type');
        });

        FeatureCatalogSync::sync();

        $features = json_encode(DB::table('feature_catalog')->where('default_enabled', true)->orderBy('sort')->pluck('key')->all());
        $now = now();
        $defaults = ['max_nodes' => 0, 'max_users' => 0, 'validity_value' => 12, 'validity_unit' => 'months',
            'activation_window_minutes' => 180, 'features' => $features, 'created_at' => $now, 'updated_at' => $now];
        DB::table('license_policies')->insert(['scope' => 'global', 'inherit_global' => false] + $defaults);
        foreach (array_keys((array) config('atglance.plans')) as $plan) {
            DB::table('license_policies')->insert(['scope' => 'plan:'.$plan, 'inherit_global' => true] + $defaults);
        }
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['policy_type']);
            $table->dropColumn(['format_version', 'max_nodes', 'max_users', 'policy_id', 'policy_type', 'validity_months', 'issued_by']);
        });
        Schema::dropIfExists('feature_catalog');
        Schema::dropIfExists('policy_audit_log');
        Schema::dropIfExists('custom_license_policies');
        Schema::dropIfExists('license_policies');
    }
};

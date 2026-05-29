<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::connection('legacy')->table('users')->delete();
});

test('legacy users are imported with mapped fields', function () {
    DB::connection('legacy')->table('users')->insert([
        [
            'id' => 1,
            'email' => 'alice@example.com',
            'encrypted_password' => '$2y$12$hash_alice',
            'is_super_admin' => 1,
            'created_at' => '2024-01-10 09:00:00',
            'updated_at' => '2024-01-11 10:00:00',
            'last_sign_in_at' => '2024-02-01 12:34:56',
        ],
        [
            'id' => 2,
            'email' => 'bob@example.com',
            'encrypted_password' => '$2y$12$hash_bob',
            'is_super_admin' => 0,
            'created_at' => '2024-01-12 09:00:00',
            'updated_at' => '2024-01-12 09:00:00',
            'last_sign_in_at' => null,
        ],
    ]);

    $this->artisan('legacy:import-users')->assertExitCode(0);

    $alice = User::where('email', 'alice@example.com')->sole();
    expect($alice->name)->toBe('alice');
    expect($alice->role)->toBe('admin');
    expect($alice->password)->toBe('$2y$12$hash_alice');
    expect($alice->last_sign_in_at->format('Y-m-d H:i:s'))->toBe('2024-02-01 12:34:56');

    $bob = User::where('email', 'bob@example.com')->sole();
    expect($bob->role)->toBe('sales');
    expect($bob->last_sign_in_at)->toBeNull();
});

test('existing users are preserved and upserted by email', function () {
    $existing = User::factory()->create([
        'email' => 'keep@jungjin.test',
        'name' => '유지될 이름',
        'role' => 'admin',
    ]);

    DB::connection('legacy')->table('users')->insert([
        'id' => 10,
        'email' => 'newbie@example.com',
        'encrypted_password' => '$2y$12$hash_new',
            'is_super_admin' => 0,
        'created_at' => now(),
        'updated_at' => now(),
        'last_sign_in_at' => null,
    ]);

    $this->artisan('legacy:import-users')->assertExitCode(0);

    expect(User::where('email', 'keep@jungjin.test')->exists())->toBeTrue();
    expect(User::where('email', 'newbie@example.com')->exists())->toBeTrue();
    expect($existing->fresh()->name)->toBe('유지될 이름');
});

test('dry-run does not write to database', function () {
    DB::connection('legacy')->table('users')->insert([
        'id' => 1,
        'email' => 'ghost@example.com',
        'encrypted_password' => '$2y$12$hash',
        'is_super_admin' => 0,
        'created_at' => now(),
        'updated_at' => now(),
        'last_sign_in_at' => null,
    ]);

    $countBefore = User::count();

    $this->artisan('legacy:import-users', ['--dry-run' => true])->assertExitCode(0);

    expect(User::count())->toBe($countBefore);
    expect(User::where('email', 'ghost@example.com')->exists())->toBeFalse();
});

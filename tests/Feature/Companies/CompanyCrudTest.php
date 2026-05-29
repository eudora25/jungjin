<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
});

test('any authenticated user can list companies', function () {
    Company::factory()->count(3)->create();

    $this->actingAs($this->sales)
        ->get('/companies')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Companies/Index')
            ->has('companies.data', 3)
        );
});

test('admin can create a company', function () {
    $this->actingAs($this->admin)
        ->post('/companies', [
            'company_name' => '테스트 업체',
            'business_registration_number' => '1234567890',
            'representative_name' => '홍길동',
            'default_commission_grade' => 'B',
            'status' => 'active',
            'approval_status' => 'pending',
        ])
        ->assertRedirect();

    $company = Company::sole();
    expect($company->company_name)->toBe('테스트 업체');
    expect($company->created_by)->toBe($this->admin->id);
    expect($company->approval_status)->toBe('pending');
});

test('sales cannot create a company', function () {
    $this->actingAs($this->sales)
        ->post('/companies', ['company_name' => '막힐 업체'])
        ->assertForbidden();

    expect(Company::count())->toBe(0);
});

test('admin approving a company stamps approver and timestamp', function () {
    $company = Company::factory()->pending()->create();

    $this->actingAs($this->admin)
        ->put("/companies/{$company->id}", [
            'company_name' => $company->company_name,
            'status' => 'active',
            'approval_status' => 'approved',
        ])
        ->assertRedirect();

    $company->refresh();
    expect($company->approval_status)->toBe('approved');
    expect($company->approved_by)->toBe($this->admin->id);
    expect($company->approved_at)->not->toBeNull();
});

test('admin can soft-delete a company', function () {
    $company = Company::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/companies/{$company->id}")
        ->assertRedirect('/companies');

    expect(Company::count())->toBe(0);
    expect(Company::withTrashed()->count())->toBe(1);
});

test('list supports filters by status and approval_status', function () {
    Company::factory()->create(['status' => 'active', 'approval_status' => 'approved']);
    Company::factory()->create(['status' => 'inactive', 'approval_status' => 'approved']);
    Company::factory()->pending()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->get('/companies?status=active')
        ->assertInertia(fn ($page) => $page->has('companies.data', 2));

    $this->actingAs($this->admin)
        ->get('/companies?approval_status=pending')
        ->assertInertia(fn ($page) => $page->has('companies.data', 1));
});

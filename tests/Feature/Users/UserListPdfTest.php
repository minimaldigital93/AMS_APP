<?php

use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * User Management → download icon: the roster as a Khmer PDF under the company
 * letterhead. It must be the SAME list the page shows for the same role
 * dropdown / search box — the page rewrites the icon's href as they change.
 */
function captureUserListData(): ArrayObject
{
    $captured = new ArrayObject;
    View::composer('pdf.user_list', function ($view) use ($captured) {
        $captured->exchangeArray($view->getData());
    });

    return $captured;
}

function makeTenantLogin(User $admin, string $name, string $phone, array $tenantAttrs = []): User
{
    seedRoles();
    $user = User::factory()->create(['name' => $name, 'phone' => $phone]);
    $user->forceFill(['account_id' => $admin->id])->save();
    $user->assignRole('tenant');
    makeTenant(null, array_merge(['name' => $name, 'user_id' => $user->id], $tenantAttrs));

    return $user;
}

beforeEach(function () {
    $this->admin = makeAdmin(['name' => 'Owner', 'phone' => '0700000001']);
    $this->actingAs($this->admin);
    settings(['company_name' => 'Sunrise Apartments']);

    makeSupervisor(['name' => 'Sup Vanna', 'phone' => '0700000002', 'account_id' => $this->admin->id]);
    $sophea = makeTenantLogin($this->admin, 'Chan Sophea', '0700000003', ['id_card_number' => '010203040', 'address' => 'Phnom Penh']);
    makeRental($sophea->tenants()->first(), null, ['start_date' => '2026-01-05', 'end_date' => '2027-01-04']);
    makeTenantLogin($this->admin, 'Sok Dara', '0700000004');
});

it('downloads a PDF with the company header', function () {
    $data = captureUserListData();
    $response = $this->get(route('admin.users.pdf', ['role' => 'tenant']))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('tenant-list-')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($data['company']['name'])->toBe('Sunrise Apartments');
});

it('follows the role dropdown', function (?string $role, array $names) {
    $data = captureUserListData();
    $this->get(route('admin.users.pdf', array_filter(['role' => $role])))->assertOk();

    expect($data['rows']->pluck('name')->all())->toBe($names)
        ->and($data['role'])->toBe($role);
})->with([
    'all roles' => [null, ['Owner', 'Sup Vanna', 'Chan Sophea', 'Sok Dara']],
    'tenant' => ['tenant', ['Chan Sophea', 'Sok Dara']],
    'supervisor' => ['supervisor', ['Sup Vanna']],
    'admin' => ['admin', ['Owner']],
]);

it('follows the search box together with the role', function () {
    $data = captureUserListData();
    $this->get(route('admin.users.pdf', ['role' => 'tenant', 'search' => 'dara']))->assertOk();

    expect($data['rows']->pluck('name')->all())->toBe(['Sok Dara']);
});

it('lists the same users in the same order as the page', function () {
    $page = $this->get(route('admin.users.index', ['role' => 'tenant']))->viewData('users');

    $data = captureUserListData();
    $this->get(route('admin.users.pdf', ['role' => 'tenant']))->assertOk();

    expect($data['rows']->pluck('name')->all())->toBe($page->pluck('name')->all());
});

it('fills tenant rows from their tenancy', function () {
    $data = captureUserListData();
    $this->get(route('admin.users.pdf', ['role' => 'tenant']))->assertOk();

    $row = $data['rows']->firstWhere('name', 'Chan Sophea');
    expect($row['id_card_number'])->toBe('010203040')
        ->and($row['address'])->toBe('Phnom Penh')
        ->and($row['start_date']->toDateString())->toBe('2026-01-05')
        ->and($row['end_date']->toDateString())->toBe('2027-01-04');
});

it('drops suspended users when Active only is chosen', function () {
    User::where('name', 'Sok Dara')->first()->forceFill(['status' => 'suspended'])->save();

    $data = captureUserListData();
    $this->get(route('admin.users.pdf', ['role' => 'tenant']))->assertOk();
    expect($data['rows']->pluck('name')->all())->toBe(['Chan Sophea', 'Sok Dara']);

    $data = captureUserListData();
    $this->get(route('admin.users.pdf', ['role' => 'tenant', 'status' => 'active']))->assertOk();
    expect($data['rows']->pluck('name')->all())->toBe(['Chan Sophea'])
        ->and($data['activeOnly'])->toBeTrue();
});

it('marks rows so the page can hide suspended users', function () {
    User::where('name', 'Sok Dara')->first()->forceFill(['status' => 'suspended'])->save();

    $this->get(route('admin.users.index'))->assertOk()
        ->assertSee('id="statusFilter"', false)
        ->assertSee('data-suspended="1"', false)
        ->assertSee('data-suspended="0"', false);
});

it('reads a moved-out tenant from their archived tenancy', function () {
    // A move-out archives (soft-deletes) the tenant and suspends the login.
    $dara = User::where('name', 'Sok Dara')->first();
    $tenant = $dara->tenants()->first();
    $tenant->forceFill(['gender' => 'female', 'id_card_number' => '998877', 'move_in_date' => '2026-02-01'])->save();
    // A move-out closes the rental on the leave date (TenantLeaveProcessor::persist()).
    makeRental($tenant, null, ['start_date' => '2026-02-01', 'end_date' => '2026-08-15']);
    $tenant->delete();
    $dara->forceFill(['status' => 'suspended'])->save();

    $data = captureUserListData();
    $this->get(route('admin.users.pdf', ['role' => 'tenant']))->assertOk();

    $row = $data['rows']->firstWhere('name', 'Sok Dara');
    expect($row['suspended'])->toBeTrue()
        ->and($row['gender'])->toBe('female')
        ->and($row['id_card_number'])->toBe('998877')
        ->and($row['end_date']->toDateString())->toBe('2026-08-15');
});

it('prints a gender column and colours suspended rows red', function () {
    User::where('name', 'Sok Dara')->first()->forceFill(['status' => 'suspended'])->save();
    User::where('name', 'Chan Sophea')->first()->tenants()->first()->forceFill(['gender' => 'male'])->save();

    $html = view('pdf.user_list', [
        'rows' => collect([
            ['name' => 'A', 'gender' => 'male', 'suspended' => false, 'role' => 'tenant', 'phone' => null, 'id_card_number' => null, 'address' => null, 'start_date' => null, 'end_date' => null],
            ['name' => 'B', 'gender' => null, 'suspended' => true, 'role' => 'tenant', 'phone' => null, 'id_card_number' => null, 'address' => null, 'start_date' => null, 'end_date' => null],
        ]),
        'role' => null, 'search' => null, 'activeOnly' => false,
        'company' => ['name' => 'X', 'address' => null, 'phone' => null, 'email' => null],
        'generatedAt' => now(),
    ])->render();

    expect($html)->toContain('ភេទ')
        ->and(strpos($html, 'ភេទ'))->toBeLessThan(strpos($html, 'តួនាទី'))
        ->and($html)->toContain('ប្រុស')
        ->and($html)->toMatch('/<tr class="[^"]*suspended[^"]*">/');
});

it('rejects an unknown status', function () {
    $this->get(route('admin.users.pdf', ['status' => 'suspended']))->assertSessionHasErrors('status');
});

it('rejects an unknown role', function () {
    $this->get(route('admin.users.pdf', ['role' => 'superadmin']))->assertSessionHasErrors('role');
});

it('never lists another accounts users', function () {
    $other = makeAdmin(['phone' => '0700000099']);
    makeTenantLogin($other, 'Other Tenant', '0700000098');

    $data = captureUserListData();
    $this->get(route('admin.users.pdf'))->assertOk();

    expect($data['rows']->pluck('name'))->not->toContain('Other Tenant');
});

it('puts the download icon on the page with a filter-aware href', function () {
    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('id="userPdfLink"', false)
        ->assertSee('data-base="'.route('admin.users.pdf').'"', false);
});

it('is not reachable by a supervisor', function () {
    $this->actingAs(makeSupervisor(['phone' => '0700000097', 'account_id' => $this->admin->id]))
        ->get(route('admin.users.pdf'))
        ->assertForbidden();
});

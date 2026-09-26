<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveType;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manajemen user oleh Super Admin:
 * - Bisa menghapus user dengan role DI BAWAH role-nya (soft delete).
 * - Tidak bisa menghapus diri sendiri.
 * - User yang masih memiliki arsip tidak bisa dihapus (jejak audit).
 * - Role di bawah Super Admin tidak bisa menghapus siapa pun.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $superAdmin;

    private User $superUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::create(['name' => 'Unit Uji', 'code' => 'UUJ', 'is_active' => true]);

        $superAdminRole = Role::create(['name' => Role::SUPER_ADMIN]);
        $superUserRole = Role::create(['name' => Role::SUPER_USER]);
        $adminRole = Role::create(['name' => Role::ADMIN]);

        $this->superAdmin = User::factory()->create(['role_id' => $superAdminRole->id, 'unit_id' => $this->unit->id]);
        $this->superUser = User::factory()->create(['role_id' => $superUserRole->id, 'unit_id' => $this->unit->id]);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'unit_id' => $this->unit->id]);
    }

    public function test_super_admin_can_delete_lower_role_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $this->admin))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($this->admin);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $this->superAdmin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->superAdmin);
    }

    public function test_user_with_archives_cannot_be_deleted(): void
    {
        $category = ArchiveCategory::create(['name' => 'Keuangan', 'code' => 'KEU']);
        $type = ArchiveType::create(['category_id' => $category->id, 'name' => 'SPJ']);

        Archive::create([
            'archive_number' => 'ARS/TEST/001/'.now()->year,
            'title' => 'Arsip Milik Admin',
            'category_id' => $category->id,
            'type_id' => $type->id,
            'unit_id' => $this->unit->id,
            'year' => now()->year,
            'status' => Archive::STATUS_MENUNGGU_VERIFIKASI,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $this->admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_super_user_cannot_delete_super_admin(): void
    {
        $this->actingAs($this->superUser)
            ->delete(route('users.destroy', $this->superAdmin))
            ->assertForbidden();

        $this->assertNotSoftDeleted($this->superAdmin);
    }

    public function test_admin_cannot_delete_anyone(): void
    {
        $otherAdmin = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
            'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $otherAdmin))
            ->assertForbidden();

        $this->assertNotSoftDeleted($otherAdmin);
    }
}

<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_clients_index_requires_authentication(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_clients_index_lists_clients(): void
    {
        $admin = $this->admin();
        Client::factory()->create(['name' => 'IIT Mandi', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('IIT Mandi');
    }

    public function test_client_can_be_created(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('clients.store'), [
            'name' => 'ABC Technologies Pvt. Ltd.',
            'contact_person' => 'Rajesh Menon',
            'email' => 'rajesh@abctech.in',
            'phone' => '+91-98110-22334',
            'gstin' => '27AABCA1234F1Z6',
            'address' => 'Noida, Uttar Pradesh 201309',
            'is_active' => '1',
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'ABC Technologies Pvt. Ltd.',
            'gstin' => '27AABCA1234F1Z6',
            'created_by' => $admin->id,
        ]);
    }

    public function test_client_name_is_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('clients.store'), ['name' => '', 'is_active' => '1'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_invalid_gstin_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'name' => 'Bad GSTIN Ltd.',
                'gstin' => 'NOT-A-GSTIN',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('gstin');
    }

    public function test_gstin_is_normalised_to_uppercase(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('clients.store'), [
            'name' => 'Lowercase Gstin Ltd.',
            'gstin' => '27aabca1234f1z6',
            'is_active' => '1',
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['gstin' => '27AABCA1234F1Z6']);
    }

    public function test_client_can_be_updated(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Old Name Ltd.', 'created_by' => $admin->id]);

        $this->actingAs($admin)->put(route('clients.update', $client), [
            'name' => 'New Name Ltd.',
            'is_active' => '1',
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'New Name Ltd.']);
    }

    public function test_client_status_can_be_toggled(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->patch(route('clients.toggle-status', $client));

        $this->assertFalse($client->fresh()->is_active);

        $this->actingAs($admin)->patch(route('clients.toggle-status', $client));

        $this->assertTrue($client->fresh()->is_active);
    }

    public function test_client_can_be_searched(): void
    {
        $admin = $this->admin();
        Client::factory()->create(['name' => 'IIT Mandi', 'created_by' => $admin->id]);
        Client::factory()->create(['name' => 'Sharma Enterprises', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('clients.index', ['search' => 'Mandi']))
            ->assertOk()
            ->assertSee('IIT Mandi')
            ->assertDontSee('Sharma Enterprises');
    }

    public function test_inactive_filter_works(): void
    {
        $admin = $this->admin();
        Client::factory()->create(['name' => 'Active Co.', 'is_active' => true, 'created_by' => $admin->id]);
        Client::factory()->create(['name' => 'Retired Co.', 'is_active' => false, 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('clients.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Retired Co.')
            ->assertDontSee('Active Co.');
    }

    public function test_client_can_be_viewed(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Viewable Ltd.', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Viewable Ltd.');
    }

    public function test_delete_moves_client_to_trash_and_is_reversible(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Doomed Ltd.', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        // Soft deleted: gone from default scope, still in the table.
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertNull(Client::find($client->id));

        $this->actingAs($admin)
            ->post(route('clients.restore', ['client' => $client->id]))
            ->assertRedirect(route('clients.trash'));

        $this->assertNotNull(Client::find($client->id));
    }

    public function test_trashed_clients_are_hidden_from_the_index(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Hidden Ltd.', 'created_by' => $admin->id]);
        $client->delete();

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertDontSee('Hidden Ltd.');

        $this->actingAs($admin)
            ->get(route('clients.trash'))
            ->assertOk()
            ->assertSee('Hidden Ltd.');
    }

    public function test_client_can_be_permanently_deleted_from_trash(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['name' => 'Purged Ltd.', 'created_by' => $admin->id]);
        $client->delete();

        $this->actingAs($admin)
            ->delete(route('clients.force-destroy', ['client' => $client->id]))
            ->assertRedirect(route('clients.trash'));

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_restore_rejects_an_id_that_is_not_trashed(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('clients.restore', ['client' => $client->id]))
            ->assertNotFound();
    }

    public function test_guests_cannot_mutate_clients(): void
    {
        $client = Client::factory()->create();

        $this->post(route('clients.store'), ['name' => 'X Ltd.', 'is_active' => '1'])->assertRedirect(route('login'));
        $this->put(route('clients.update', $client), ['name' => 'Y Ltd.', 'is_active' => '1'])->assertRedirect(route('login'));
        $this->delete(route('clients.destroy', $client))->assertRedirect(route('login'));
    }
}

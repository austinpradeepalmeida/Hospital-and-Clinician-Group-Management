<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_groups_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/groups');

        $response->assertStatus(401);
    }

    public function test_can_get_groups_with_authentication(): void
    {
        Sanctum::actingAs($this->user);

        Group::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/groups');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'type',
                        'parent_id',
                        'level',
                        'path',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);
    }

    public function test_can_get_groups_as_tree(): void
    {
        Sanctum::actingAs($this->user);

        $root = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root->id]);

        $response = $this->getJson('/api/v1/groups?tree=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'descendants'
                    ]
                ]
            ]);
    }

    public function test_can_filter_groups_by_type(): void
    {
        Sanctum::actingAs($this->user);

        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'clinician_group']);

        $response = $this->getJson('/api/v1/groups?type=hospital');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('hospital', $data[0]['type']);
    }

    public function test_can_create_group(): void
    {
        Sanctum::actingAs($this->user);

        $groupData = [
            'name' => 'Test Hospital',
            'description' => 'A test hospital',
            'type' => 'hospital',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'parent_id',
                    'level',
                    'path',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('groups', $groupData);
    }

    public function test_can_create_group_with_parent(): void
    {
        Sanctum::actingAs($this->user);

        $parent = Group::factory()->active()->create();

        $groupData = [
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ];

        $response = $this->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals($parent->id, $data['parent_id']);
        $this->assertEquals(1, $data['level']);
    }

    public function test_cannot_create_group_with_invalid_parent(): void
    {
        Sanctum::actingAs($this->user);

        $groupData = [
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => 999,
        ];

        $response = $this->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_can_get_specific_group(): void
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create();

        $response = $this->getJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'parent_id',
                    'level',
                    'path',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    public function test_cannot_get_non_existent_group(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/groups/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Group not found'
            ]);
    }

    public function test_can_update_group(): void
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create();

        $updateData = [
            'name' => 'Updated Hospital Name',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/v1/groups/{$group->id}", $updateData);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Updated Hospital Name', $data['name']);
        $this->assertEquals('Updated description', $data['description']);
    }

    public function test_can_delete_group_without_children(): void
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create();

        $response = $this->deleteJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Group deleted successfully'
            ]);

        $this->assertSoftDeleted('groups', ['id' => $group->id]);
    }

    public function test_cannot_delete_group_with_children(): void
    {
        Sanctum::actingAs($this->user);

        $parent = Group::factory()->create();
        Group::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/v1/groups/{$parent->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete group: it has child groups. Delete children first or use force delete.'
            ]);
    }

    public function test_can_force_delete_group_with_children(): void
    {
        Sanctum::actingAs($this->user);

        $parent = Group::factory()->create();
        $child = Group::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/v1/groups/{$parent->id}?force_delete_children=true");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Group deleted successfully'
            ]);

        $this->assertSoftDeleted('groups', ['id' => $parent->id]);
        $this->assertSoftDeleted('groups', ['id' => $child->id]);
    }



    public function test_validation_works_for_group_creation(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/groups', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_validation_works_for_group_update(): void
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create();

        $response = $this->putJson("/api/v1/groups/{$group->id}", [
            'name' => '', // Empty name should fail
            'type' => 'invalid_type', // Invalid type should fail
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }
}

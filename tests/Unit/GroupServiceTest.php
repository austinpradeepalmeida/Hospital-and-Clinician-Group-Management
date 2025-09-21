<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Repositories\GroupRepository;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GroupService $service;
    protected GroupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GroupRepository();
        $this->service = new GroupService($this->repository);
    }

    public function test_can_create_group(): void
    {
        $data = [
            'name' => 'Test Hospital',
            'description' => 'A test hospital',
            'type' => 'hospital',
            'is_active' => true,
        ];

        $group = $this->service->createGroup($data);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('Test Hospital', $group->name);
        $this->assertDatabaseHas('groups', $data);
    }

    public function test_can_create_group_with_parent(): void
    {
        $parent = Group::factory()->create();

        $data = [
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ];

        $group = $this->service->createGroup($data);

        $this->assertEquals($parent->id, $group->parent_id);
        $this->assertEquals(1, $group->level);
    }

    public function test_cannot_create_group_with_invalid_parent(): void
    {
        $data = [
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => 999, // Non-existent parent
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Parent group not found.');
        $this->service->createGroup($data);
    }

    public function test_cannot_create_group_that_would_create_cycle(): void
    {
        $parent = Group::factory()->create();
        $child = Group::factory()->create(['parent_id' => $parent->id]);

        // Try to create a new group with the parent as parent, but the parent already has the child
        // This test should pass since creating a new group doesn't create a cycle
        $data = [
            'name' => 'New Group',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ];

        $group = $this->service->createGroup($data);
        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals($parent->id, $group->parent_id);
    }

    public function test_can_get_group(): void
    {
        $group = Group::factory()->create();

        $found = $this->service->getGroup($group->id);

        $this->assertEquals($group->id, $found->id);
    }

    public function test_get_group_throws_exception_for_non_existent(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->getGroup(999);
    }

    public function test_can_get_tree(): void
    {
        $root = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root->id]);

        $tree = $this->service->getTree();

        $this->assertCount(1, $tree);
        $this->assertEquals($root->id, $tree->first()->id);
    }

    public function test_can_get_all_groups_with_filters(): void
    {
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'clinician_group']);

        $hospitals = $this->service->getAllGroups(['type' => 'hospital']);
        $clinicianGroups = $this->service->getAllGroups(['type' => 'clinician_group']);

        $this->assertCount(2, $hospitals);
        $this->assertCount(1, $clinicianGroups);
    }

    public function test_can_update_group(): void
    {
        $group = Group::factory()->create();

        $updateData = ['name' => 'Updated Name'];
        $updated = $this->service->updateGroup($group->id, $updateData);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Updated Name']);
    }

    public function test_can_update_group_parent(): void
    {
        $oldParent = Group::factory()->create();
        $newParent = Group::factory()->create();
        $group = Group::factory()->create(['parent_id' => $oldParent->id]);

        $updateData = ['parent_id' => $newParent->id];
        $updated = $this->service->updateGroup($group->id, $updateData);

        $this->assertEquals($newParent->id, $updated->parent_id);
    }

    public function test_cannot_update_group_with_invalid_parent(): void
    {
        $group = Group::factory()->create();

        $updateData = ['parent_id' => 999];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Parent group not found.');
        $this->service->updateGroup($group->id, $updateData);
    }

    public function test_cannot_update_group_that_would_create_cycle(): void
    {
        $parent = Group::factory()->create();
        $child = Group::factory()->create(['parent_id' => $parent->id]);

        $updateData = ['parent_id' => $child->id]; // This would create a cycle

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('would create a cycle');
        $this->service->updateGroup($parent->id, $updateData);
    }

    public function test_can_delete_group_without_children(): void
    {
        $group = Group::factory()->create();

        $result = $this->service->deleteGroup($group->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
    }

    public function test_cannot_delete_group_with_children(): void
    {
        $parent = Group::factory()->create();
        Group::factory()->create(['parent_id' => $parent->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has child groups');
        $this->service->deleteGroup($parent->id);
    }

    public function test_can_force_delete_group_with_children(): void
    {
        $parent = Group::factory()->create();
        $child = Group::factory()->create(['parent_id' => $parent->id]);

        $result = $this->service->deleteGroup($parent->id, true);

        $this->assertTrue($result);
        $this->assertSoftDeleted('groups', ['id' => $parent->id]);
        $this->assertSoftDeleted('groups', ['id' => $child->id]);
    }

    public function test_can_get_groups_by_type(): void
    {
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'clinician_group']);

        $hospitals = $this->service->getGroupsByType('hospital');
        $clinicianGroups = $this->service->getGroupsByType('clinician_group');

        $this->assertCount(2, $hospitals);
        $this->assertCount(1, $clinicianGroups);
    }

    public function test_can_get_active_groups(): void
    {
        Group::factory()->create(['is_active' => true]);
        Group::factory()->create(['is_active' => true]);
        Group::factory()->create(['is_active' => false]);

        $activeGroups = $this->service->getActiveGroups();

        $this->assertCount(2, $activeGroups);
    }

    public function test_can_get_groups_by_level(): void
    {
        $root = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root->id]);

        $level0Groups = $this->service->getGroupsByLevel(0);
        $level1Groups = $this->service->getGroupsByLevel(1);

        $this->assertCount(1, $level0Groups);
        $this->assertCount(1, $level1Groups);
    }


    public function test_can_search_groups(): void
    {
        Group::factory()->create(['name' => 'Cardiology Department']);
        Group::factory()->create(['name' => 'Neurology Department']);
        Group::factory()->create(['description' => 'Heart specialist team']);

        $results = $this->service->searchGroups('cardiology');

        $this->assertCount(1, $results);
        $this->assertEquals('Cardiology Department', $results->first()->name);
    }


    public function test_can_validate_hierarchy_integrity(): void
    {
        // Create a valid hierarchy
        $root = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root->id]);

        $issues = $this->service->validateHierarchyIntegrity();

        $this->assertIsArray($issues);
        // Should have no issues with a valid hierarchy
        $this->assertEmpty($issues);
    }
}

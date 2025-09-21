<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Repositories\GroupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected GroupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GroupRepository();
    }

    public function test_can_create_group(): void
    {
        $data = [
            'name' => 'Test Hospital',
            'description' => 'A test hospital',
            'type' => 'hospital',
            'is_active' => true,
        ];

        $group = $this->repository->create($data);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('Test Hospital', $group->name);
        $this->assertDatabaseHas('groups', $data);
    }

    public function test_can_find_group_by_id(): void
    {
        $group = Group::factory()->create();

        $found = $this->repository->findById($group->id);

        $this->assertInstanceOf(Group::class, $found);
        $this->assertEquals($group->id, $found->id);
    }

    public function test_can_find_group_by_id_or_fail(): void
    {
        $group = Group::factory()->create();

        $found = $this->repository->findByIdOrFail($group->id);

        $this->assertInstanceOf(Group::class, $found);
        $this->assertEquals($group->id, $found->id);
    }

    public function test_find_by_id_or_fail_throws_exception_for_non_existent(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->repository->findByIdOrFail(999);
    }

    public function test_can_update_group(): void
    {
        $group = Group::factory()->create();
        $updateData = ['name' => 'Updated Name'];

        $result = $this->repository->update($group, $updateData);

        $this->assertTrue($result);
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_group(): void
    {
        $group = Group::factory()->create();

        $result = $this->repository->delete($group);

        $this->assertTrue($result);
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
    }

    public function test_can_get_all_groups(): void
    {
        Group::factory()->count(3)->create();

        $groups = $this->repository->getAll();

        $this->assertCount(3, $groups);
    }

    public function test_can_get_root_groups(): void
    {
        $root1 = Group::factory()->create(['parent_id' => null]);
        $root2 = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root1->id]);

        $roots = $this->repository->getRootGroups();

        $this->assertCount(2, $roots);
        $this->assertTrue($roots->contains($root1));
        $this->assertTrue($roots->contains($root2));
        $this->assertFalse($roots->contains($child));
    }

    public function test_can_get_tree(): void
    {
        $root = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root->id]);

        $tree = $this->repository->getTree();

        $this->assertCount(1, $tree);
        $this->assertEquals($root->id, $tree->first()->id);
    }

    public function test_can_get_groups_by_type(): void
    {
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'hospital']);
        Group::factory()->create(['type' => 'clinician_group']);

        $hospitals = $this->repository->getByType('hospital');
        $clinicianGroups = $this->repository->getByType('clinician_group');

        $this->assertCount(2, $hospitals);
        $this->assertCount(1, $clinicianGroups);
    }

    public function test_can_get_active_groups(): void
    {
        Group::factory()->create(['is_active' => true]);
        Group::factory()->create(['is_active' => true]);
        Group::factory()->create(['is_active' => false]);

        $activeGroups = $this->repository->getActiveGroups();

        $this->assertCount(2, $activeGroups);
    }

    public function test_can_get_groups_by_level(): void
    {
        $root = Group::factory()->create(['parent_id' => null]);
        $child = Group::factory()->create(['parent_id' => $root->id]);

        $level0Groups = $this->repository->getByLevel(0);
        $level1Groups = $this->repository->getByLevel(1);

        $this->assertCount(1, $level0Groups);
        $this->assertCount(1, $level1Groups);
    }

    public function test_can_get_children(): void
    {
        $parent = Group::factory()->create();
        $child1 = Group::factory()->create(['parent_id' => $parent->id]);
        $child2 = Group::factory()->create(['parent_id' => $parent->id]);

        $children = $this->repository->getChildren($parent->id);

        $this->assertCount(2, $children);
        $this->assertTrue($children->contains($child1));
        $this->assertTrue($children->contains($child2));
    }

    public function test_can_check_if_has_children(): void
    {
        $parent = Group::factory()->create();
        $child = Group::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($this->repository->hasChildren($parent->id));
        $this->assertFalse($this->repository->hasChildren($child->id));
    }

    public function test_can_count_children(): void
    {
        $parent = Group::factory()->create();
        Group::factory()->count(3)->create(['parent_id' => $parent->id]);

        $count = $this->repository->countChildren($parent->id);

        $this->assertEquals(3, $count);
    }

    public function test_can_check_if_exists(): void
    {
        $group = Group::factory()->create();

        $this->assertTrue($this->repository->exists($group->id));
        $this->assertFalse($this->repository->exists(999));
    }

    public function test_can_detect_cycle_creation(): void
    {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create(['parent_id' => $group1->id]);

        // group1 cannot be parent of group2 (would create cycle)
        $this->assertTrue($this->repository->wouldCreateCycle($group1->id, $group2->id));

        // group2 can be parent of group1 (no cycle)
        $this->assertFalse($this->repository->wouldCreateCycle($group2->id, $group1->id));

        // group cannot be parent of itself
        $this->assertTrue($this->repository->wouldCreateCycle($group1->id, $group1->id));
    }

    public function test_can_search_groups(): void
    {
        Group::factory()->create(['name' => 'Cardiology Department']);
        Group::factory()->create(['name' => 'Neurology Department']);
        Group::factory()->create(['description' => 'Heart specialist team']);

        $results = $this->repository->search('cardiology');

        $this->assertCount(1, $results);
        $this->assertEquals('Cardiology Department', $results->first()->name);
    }

    public function test_can_get_full_path(): void
    {
        $root = Group::factory()->create(['name' => 'Hospital A']);
        $child = Group::factory()->create(['name' => 'Department B', 'parent_id' => $root->id]);

        $path = $this->repository->getFullPath($child);

        $this->assertEquals('Hospital A > Department B', $path);
    }
}

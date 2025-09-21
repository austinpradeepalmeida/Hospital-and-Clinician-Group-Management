<?php

namespace Tests\Unit;

use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_group(): void
    {
        $group = Group::create([
            'name' => 'Test Hospital',
            'description' => 'A test hospital',
            'type' => 'hospital',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('Test Hospital', $group->name);
        $this->assertEquals('hospital', $group->type);
        $this->assertTrue($group->is_active);
        $this->assertTrue($group->isRoot());
    }

    public function test_can_create_group_with_parent(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals(1, $child->level);
        $this->assertFalse($child->isRoot());
        $this->assertTrue($child->isDescendantOf($parent));
    }

    public function test_can_get_parent_relationship(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_can_get_children_relationship(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child1 = Group::create([
            'name' => 'Child Department 1',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $child2 = Group::create([
            'name' => 'Child Department 2',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $children = $parent->children;
        $this->assertCount(2, $children);
        $this->assertTrue($children->contains($child1));
        $this->assertTrue($children->contains($child2));
    }

    public function test_can_get_ancestors(): void
    {
        $grandparent = Group::create([
            'name' => 'Grandparent Hospital',
            'type' => 'hospital',
        ]);

        $parent = Group::create([
            'name' => 'Parent Department',
            'type' => 'clinician_group',
            'parent_id' => $grandparent->id,
        ]);

        $child = Group::create([
            'name' => 'Child Team',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $child = $child->load('parent.parent');
        $ancestors = $child->ancestors();
        $this->assertCount(2, $ancestors);
        $this->assertTrue($ancestors->contains('id', $parent->id));
        $this->assertTrue($ancestors->contains('id', $grandparent->id));
    }

    public function test_can_check_if_ancestor(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($parent->isAncestorOf($child));
        $this->assertFalse($child->isAncestorOf($parent));
    }

    public function test_can_check_if_descendant(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($child->isDescendantOf($parent));
        $this->assertFalse($parent->isDescendantOf($child));
    }

    public function test_can_get_root(): void
    {
        $root = Group::create([
            'name' => 'Root Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $root->id,
        ]);

        $this->assertEquals($root->id, $child->getRoot()->id);
        $this->assertEquals($root->id, $root->getRoot()->id);
    }

    public function test_can_check_if_leaf(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $this->assertFalse($parent->isLeaf());
        $this->assertTrue($child->isLeaf());
    }

    public function test_scopes_work_correctly(): void
    {
        Group::create([
            'name' => 'Hospital 1',
            'type' => 'hospital',
        ]);

        Group::create([
            'name' => 'Hospital 2',
            'type' => 'hospital',
        ]);

        Group::create([
            'name' => 'Department 1',
            'type' => 'clinician_group',
        ]);

        $this->assertCount(2, Group::ofType('hospital')->get());
        $this->assertCount(1, Group::ofType('clinician_group')->get());
        $this->assertCount(3, Group::roots()->get());
        $this->assertCount(3, Group::active()->get());
    }

    public function test_path_is_set_correctly(): void
    {
        $parent = Group::create([
            'name' => 'Parent Hospital',
            'type' => 'hospital',
        ]);

        $child = Group::create([
            'name' => 'Child Department',
            'type' => 'clinician_group',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals((string) $parent->id, $parent->path);
        $this->assertEquals($parent->id . '/' . $child->id, $child->path);
    }
}

<?php

namespace App\Services;

use App\Models\Group;
use App\Repositories\GroupRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupService
 * 
 * Service class for handling Group business logic.
 * Implements the Service pattern to separate business logic from controllers.
 */
class GroupService
{
    /**
     * The group repository instance.
     *
     * @var GroupRepository
     */
    protected GroupRepository $groupRepository;

    /**
     * Create a new service instance.
     *
     * @param GroupRepository $groupRepository
     */
    public function __construct(GroupRepository $groupRepository)
    {
        $this->groupRepository = $groupRepository;
    }

    /**
     * Create a new group.
     *
     * @param array $data
     * @return Group
     * @throws \Exception
     */
    public function createGroup(array $data): Group
    {
        try {
            DB::beginTransaction();

            // Validate parent exists if provided
            if (isset($data['parent_id']) && $data['parent_id']) {
                $parent = $this->groupRepository->findById($data['parent_id']);
                if (!$parent) {
                    throw new \Exception('Parent group not found.');
                }

                // Check for cycle creation
                if ($this->groupRepository->wouldCreateCycle(0, $data['parent_id'])) {
                    throw new \Exception('Cannot create group: would create a cycle in the hierarchy.');
                }
            }

            // Set default values
            $data['is_active'] = $data['is_active'] ?? true;
            $data['type'] = $data['type'] ?? 'clinician_group';

            $group = $this->groupRepository->create($data);

            DB::commit();

            Log::info('Group created successfully', ['group_id' => $group->id, 'name' => $group->name]);

            return $group->load(['parent', 'children']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create group', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Get a group by ID.
     *
     * @param int $id
     * @return Group
     * @throws ModelNotFoundException
     */
    public function getGroup(int $id): Group
    {
        return $this->groupRepository->findByIdOrFail($id)->load(['parent', 'children']);
    }

    /**
     * Get all groups as a tree structure.
     *
     * @return Collection
     */
    public function getTree(): Collection
    {
        return $this->groupRepository->getTree();
    }

    /**
     * Get all groups.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllGroups(array $filters = []): Collection
    {
        $query = Group::query();

        // Apply filters
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['level'])) {
            $query->atLevel($filters['level']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        return $query->with(['parent', 'children'])->get();
    }

    /**
     * Update a group.
     *
     * @param int $id
     * @param array $data
     * @return Group
     * @throws \Exception
     */
    public function updateGroup(int $id, array $data): Group
    {
        try {
            DB::beginTransaction();

            $group = $this->groupRepository->findByIdOrFail($id);

            // Validate parent exists if being changed
            if (isset($data['parent_id']) && $data['parent_id'] !== $group->parent_id) {
                if ($data['parent_id']) {
                    $parent = $this->groupRepository->findById($data['parent_id']);
                    if (!$parent) {
                        throw new \Exception('Parent group not found.');
                    }

                    // Check for cycle creation
                    if ($this->groupRepository->wouldCreateCycle($id, $data['parent_id'])) {
                        throw new \Exception('Cannot update group: would create a cycle in the hierarchy.');
                    }
                }
            }

            $this->groupRepository->update($group, $data);

            // Refresh the model to get updated data
            $group->refresh();

            DB::commit();

            Log::info('Group updated successfully', ['group_id' => $group->id, 'name' => $group->name]);

            return $group->load(['parent', 'children']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update group', ['group_id' => $id, 'error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Delete a group.
     *
     * @param int $id
     * @param bool $forceDeleteChildren
     * @return bool
     * @throws \Exception
     */
    public function deleteGroup(int $id, bool $forceDeleteChildren = false): bool
    {
        try {
            DB::beginTransaction();

            $group = $this->groupRepository->findByIdOrFail($id);

            // Check if group has children
            if ($this->groupRepository->hasChildren($id)) {
                if (!$forceDeleteChildren) {
                    throw new \Exception('Cannot delete group: it has child groups. Delete children first or use force delete.');
                }

                // Recursively delete all children
                $this->deleteGroupChildren($id);
            }

            $deleted = $this->groupRepository->delete($group);

            DB::commit();

            Log::info('Group deleted successfully', ['group_id' => $id, 'name' => $group->name]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete group', ['group_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Recursively delete all children of a group.
     *
     * @param int $parentId
     * @return void
     */
    protected function deleteGroupChildren(int $parentId): void
    {
        $children = $this->groupRepository->getChildren($parentId);

        foreach ($children as $child) {
            if ($this->groupRepository->hasChildren($child->id)) {
                $this->deleteGroupChildren($child->id);
            }
            $this->groupRepository->delete($child);
        }
    }

    /**
     * Get groups by type.
     *
     * @param string $type
     * @return Collection
     */
    public function getGroupsByType(string $type): Collection
    {
        return $this->groupRepository->getByType($type, ['parent', 'children']);
    }

    /**
     * Get active groups.
     *
     * @return Collection
     */
    public function getActiveGroups(): Collection
    {
        return $this->groupRepository->getActiveGroups(['parent', 'children']);
    }

    /**
     * Get groups at a specific level.
     *
     * @param int $level
     * @return Collection
     */
    public function getGroupsByLevel(int $level): Collection
    {
        return $this->groupRepository->getByLevel($level, ['parent', 'children']);
    }


    /**
     * Search groups.
     *
     * @param string $search
     * @return Collection
     */
    public function searchGroups(string $search): Collection
    {
        return $this->groupRepository->search($search, ['parent', 'children']);
    }

    /**
     * Get paginated groups.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedGroups(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Group::query();

        // Apply filters
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['level'])) {
            $query->atLevel($filters['level']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        return $query->with(['parent', 'children'])->paginate($perPage);
    }


    /**
     * Validate group hierarchy integrity.
     *
     * @return array
     */
    public function validateHierarchyIntegrity(): array
    {
        $issues = [];

        // Check for orphaned groups (groups with parent_id that doesn't exist)
        $orphanedGroups = Group::whereNotNull('parent_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('groups as parent')
                    ->whereColumn('parent.id', 'groups.parent_id');
            })
            ->get();

        if ($orphanedGroups->count() > 0) {
            $issues[] = [
                'type' => 'orphaned_groups',
                'message' => 'Found groups with invalid parent references',
                'count' => $orphanedGroups->count(),
                'groups' => $orphanedGroups->pluck('id')->toArray(),
            ];
        }

        // Check for incorrect level calculations
        $incorrectLevels = Group::whereNotNull('groups.parent_id')
            ->join('groups as parent', 'groups.parent_id', '=', 'parent.id')
            ->whereRaw('groups.level != parent.level + 1')
            ->select('groups.*')
            ->get();

        if ($incorrectLevels->count() > 0) {
            $issues[] = [
                'type' => 'incorrect_levels',
                'message' => 'Found groups with incorrect level calculations',
                'count' => $incorrectLevels->count(),
                'groups' => $incorrectLevels->pluck('id')->toArray(),
            ];
        }

        return $issues;
    }
}

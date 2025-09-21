<?php

namespace App\Repositories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * GroupRepository
 * 
 * Repository class for handling Group model data access operations.
 * Implements the Repository pattern to separate data access logic from business logic.
 */
class GroupRepository
{
    /**
     * Create a new group.
     *
     * @param array $data
     * @return Group
     */
    public function create(array $data): Group
    {
        return Group::create($data);
    }

    /**
     * Find a group by ID.
     *
     * @param int $id
     * @return Group|null
     */
    public function findById(int $id): ?Group
    {
        return Group::find($id);
    }

    /**
     * Find a group by ID or throw an exception.
     *
     * @param int $id
     * @return Group
     * @throws ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Group
    {
        return Group::findOrFail($id);
    }

    /**
     * Update a group.
     *
     * @param Group $group
     * @param array $data
     * @return bool
     */
    public function update(Group $group, array $data): bool
    {
        return $group->update($data);
    }

    /**
     * Delete a group.
     *
     * @param Group $group
     * @return bool|null
     */
    public function delete(Group $group): ?bool
    {
        return $group->delete();
    }

    /**
     * Get all groups with their relationships.
     *
     * @param array $with
     * @return Collection
     */
    public function getAll(array $with = []): Collection
    {
        $query = Group::query();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get all root groups (groups without parents).
     *
     * @param array $with
     * @return Collection
     */
    public function getRootGroups(array $with = []): Collection
    {
        $query = Group::roots();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get the complete tree structure.
     *
     * @return Collection
     */
    public function getTree(): Collection
    {
        return Group::roots()->with('descendants')->get();
    }

    /**
     * Get groups by type.
     *
     * @param string $type
     * @param array $with
     * @return Collection
     */
    public function getByType(string $type, array $with = []): Collection
    {
        $query = Group::ofType($type);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get active groups.
     *
     * @param array $with
     * @return Collection
     */
    public function getActiveGroups(array $with = []): Collection
    {
        $query = Group::active();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get groups at a specific level.
     *
     * @param int $level
     * @param array $with
     * @return Collection
     */
    public function getByLevel(int $level, array $with = []): Collection
    {
        $query = Group::atLevel($level);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get children of a specific group.
     *
     * @param int $parentId
     * @param array $with
     * @return Collection
     */
    public function getChildren(int $parentId, array $with = []): Collection
    {
        $query = Group::where('parent_id', $parentId);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }


    /**
     * Check if a group has children.
     *
     * @param int $groupId
     * @return bool
     */
    public function hasChildren(int $groupId): bool
    {
        return Group::where('parent_id', $groupId)->exists();
    }

    /**
     * Count children of a specific group.
     *
     * @param int $groupId
     * @return int
     */
    public function countChildren(int $groupId): int
    {
        return Group::where('parent_id', $groupId)->count();
    }

    /**
     * Check if a group exists.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return Group::where('id', $id)->exists();
    }

    /**
     * Check if a parent-child relationship would create a cycle.
     *
     * @param int $groupId
     * @param int $parentId
     * @return bool
     */
    public function wouldCreateCycle(int $groupId, int $parentId): bool
    {
        // A group cannot be its own parent
        if ($groupId === $parentId) {
            return true;
        }

        // Check if the proposed parent is a descendant of the group
        $group = $this->findById($groupId);
        if (!$group) {
            // For new groups (groupId = 0), we can't create a cycle
            return false;
        }

        $parent = $this->findById($parentId);
        if (!$parent) {
            return false;
        }

        return $group->isAncestorOf($parent);
    }

    /**
     * Get paginated groups.
     *
     * @param int $perPage
     * @param array $with
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        $query = Group::query();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->paginate($perPage);
    }

    /**
     * Search groups by name or description.
     *
     * @param string $search
     * @param array $with
     * @return Collection
     */
    public function search(string $search, array $with = []): Collection
    {
        $query = Group::where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get groups with their full path.
     *
     * @param array $with
     * @return Collection
     */
    public function getWithPath(array $with = []): Collection
    {
        $query = Group::query();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get()->map(function ($group) {
            $group->full_path = $this->getFullPath($group);
            return $group;
        });
    }

    /**
     * Get the full path of a group (e.g., "Hospital A > Department B > Team C").
     *
     * @param Group $group
     * @return string
     */
    public function getFullPath(Group $group): string
    {
        $path = collect([$group->name]);
        $current = $group->parent;

        while ($current) {
            $path->prepend($current->name);
            $current = $current->parent;
        }

        return $path->implode(' > ');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Group Model
 * 
 * Represents a hierarchical tree structure of hospitals and clinician groups.
 * Each group can have a parent group and multiple child groups.
 * 
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property int|null $parent_id
 * @property int $level
 * @property string|null $path
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Group|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|Group[] $children
 * @property-read \Illuminate\Database\Eloquent\Collection|Group[] $descendants
 * @property-read \Illuminate\Database\Eloquent\Collection|Group[] $ancestors
 */
class Group extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'parent_id',
        'level',
        'path',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * Convert the model instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Ensure parent_id is always included, even if null
        if (!array_key_exists('parent_id', $array)) {
            $array['parent_id'] = $this->parent_id;
        }
        
        return $array;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the parent group.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    /**
     * Get the child groups.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    /**
     * Get all descendant groups (children, grandchildren, etc.).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestor groups (parent, grandparent, etc.).
     */
    public function ancestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Check if this group is a root group (has no parent).
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this group is a leaf group (has no children).
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Get the depth of this group in the tree.
     */
    public function getDepth(): int
    {
        return $this->level;
    }

    /**
     * Check if this group is an ancestor of the given group.
     */
    public function isAncestorOf(Group $group): bool
    {
        return $group->ancestors()->contains('id', $this->id);
    }

    /**
     * Check if this group is a descendant of the given group.
     */
    public function isDescendantOf(Group $group): bool
    {
        return $group->isAncestorOf($this);
    }

    /**
     * Get the root group of this group's tree.
     */
    public function getRoot(): Group
    {
        $current = $this;
        while ($current->parent) {
            $current = $current->parent;
        }
        return $current;
    }

    /**
     * Scope to get only root groups.
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only active groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get groups by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get groups at a specific level.
     */
    public function scopeAtLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($group) {
            if ($group->parent_id) {
                $parent = static::find($group->parent_id);
                $group->level = $parent->level + 1;
                $group->path = $parent->path ? $parent->path . '/' . $group->id : (string) $group->id;
            } else {
                $group->level = 0;
                $group->path = (string) $group->id;
            }
            $group->saveQuietly();
        });

        static::updating(function ($group) {
            if ($group->isDirty('parent_id')) {
                if ($group->parent_id) {
                    $parent = static::find($group->parent_id);
                    $group->level = $parent->level + 1;
                    $group->path = $parent->path ? $parent->path . '/' . $group->id : (string) $group->id;
                } else {
                    $group->level = 0;
                    $group->path = (string) $group->id;
                }
            }
        });
    }
}

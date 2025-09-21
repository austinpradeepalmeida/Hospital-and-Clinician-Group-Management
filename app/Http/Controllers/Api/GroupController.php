<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Services\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Groups",
 *     description="API endpoints for managing hierarchical groups (hospitals and clinician groups)"
 * )
 */
class GroupController extends Controller
{
    protected GroupService $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    /**
     * @OA\Get(
     *     path="/groups",
     *     summary="Get all groups",
     *     tags={"Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by group type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"hospital", "clinician_group"}, example="hospital")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="level",
     *         in="query",
     *         description="Filter by hierarchy level",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="parent_id",
     *         in="query",
     *         description="Filter by parent group ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in group name and description",
     *         required=false,
     *         @OA\Schema(type="string", example="Shoulder")
     *     ),
     *     @OA\Parameter(
     *         name="tree",
     *         in="query",
     *         description="Return groups as hierarchical tree structure",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Groups retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Groups retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Group"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['type', 'is_active', 'level', 'parent_id', 'search']);

            // Validate and process type filter
            if (isset($filters['type'])) {
                $validTypes = ['hospital', 'clinician_group'];
                if (!in_array($filters['type'], $validTypes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid type parameter. Must be either "hospital" or "clinician_group".',
                        'error' => 'Invalid type parameter'
                    ], 422);
                }
            }

            if (isset($filters['is_active'])) {
                $filters['is_active'] = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            }

            if (isset($filters['level'])) {
                $filters['level'] = (int) $filters['level'];
            }

            if (isset($filters['parent_id']) && $filters['parent_id'] !== 'null') {
                $filters['parent_id'] = (int) $filters['parent_id'];
            }

            if ($request->boolean('tree')) {
                $groups = $this->groupService->getTree();
            } else {
                $groups = $this->groupService->getAllGroups($filters);
            }

            return response()->json([
                'success' => true,
                'message' => 'Groups retrieved successfully',
                'data' => $groups,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve groups', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve groups',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/groups",
     *     summary="Create a new group",
     *     tags={"Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type"},
     *             @OA\Property(property="name", type="string", example="Shoulder", description="Name of the group"),
     *             @OA\Property(property="description", type="string", example="Department specializing in heart conditions", description="Description of the group"),
     *             @OA\Property(property="type", type="string", enum={"hospital", "clinician_group"}, example="clinician_group", description="Type of the group"),
     *             @OA\Property(property="parent_id", type="integer", example=1, description="ID of the parent group (optional)"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether the group is active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Group created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Group created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Group")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        try {
            $group = $this->groupService->createGroup($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'data' => $group,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/groups/{id}",
     *     summary="Get a specific group",
     *     tags={"Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Group retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Group retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Group")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $group = $this->groupService->getGroup($id);

            return response()->json([
                'success' => true,
                'message' => 'Group retrieved successfully',
                'data' => $group,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve group', ['group_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/groups/{id}",
     *     summary="Update a group",
     *     tags={"Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Shoulder", description="Name of the group"),
     *             @OA\Property(property="description", type="string", example="Updated description", description="Description of the group"),
     *             @OA\Property(property="type", type="string", enum={"hospital", "clinician_group"}, example="clinician_group", description="Type of the group"),
     *             @OA\Property(property="parent_id", type="integer", example=1, description="ID of the parent group (optional)"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether the group is active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Group updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Group updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Group")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function update(UpdateGroupRequest $request, int $id): JsonResponse
    {
        try {
            $group = $this->groupService->updateGroup($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Group updated successfully',
                'data' => $group,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update group', ['group_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/groups/{id}",
     *     summary="Delete a group",
     *     tags={"Groups"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="force_delete_children",
     *         in="query",
     *         description="Force delete group even if it has children",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Group deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Group deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete group with children",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $forceDeleteChildren = $request->boolean('force_delete_children', false);
            $deleted = $this->groupService->deleteGroup($id, $forceDeleteChildren);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Group deleted successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group',
            ], 500);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete group', ['group_id' => $id, 'error' => $e->getMessage()]);

            if (str_contains($e->getMessage(), 'has child groups')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



}

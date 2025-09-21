<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Hospital and Clinician Group Management API",
 *     version="1.0.0",
 *     description="A streamlined REST API for managing hierarchical hospital and clinician group structures",
 *     @OA\Contact(
 *         email="admin@hospital-api.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api/v1",
 *     description="Development server - API Version 1"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your API token"
 * )
 * 
 * @OA\Components(
 *     @OA\Schema(
 *         schema="Group",
 *         type="object",
 *         required={"id", "name", "type", "level", "is_active", "created_at", "updated_at"},
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Cardiology Department"),
 *         @OA\Property(property="description", type="string", example="Department specializing in heart conditions"),
 *         @OA\Property(property="type", type="string", enum={"hospital", "clinician_group"}, example="clinician_group"),
 *         @OA\Property(property="parent_id", type="integer", nullable=true, example=1),
 *         @OA\Property(property="level", type="integer", example=1),
 *         @OA\Property(property="path", type="string", example="1/2"),
 *         @OA\Property(property="is_active", type="boolean", example=true),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time"),
 *         @OA\Property(property="parent", ref="#/components/schemas/Group"),
 *         @OA\Property(property="children", type="array", @OA\Items(ref="#/components/schemas/Group"))
 *     ),
 *     @OA\Schema(
 *         schema="User",
 *         type="object",
 *         required={"id", "name", "email", "created_at", "updated_at"},
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time")
 *     ),
 *     @OA\Schema(
 *         schema="Error",
 *         type="object",
 *         @OA\Property(property="success", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Error message"),
 *         @OA\Property(property="errors", type="object", description="Validation errors")
 *     ),
 *     @OA\Schema(
 *         schema="Success",
 *         type="object",
 *         @OA\Property(property="success", type="boolean", example=true),
 *         @OA\Property(property="message", type="string", example="Success message"),
 *         @OA\Property(property="data", type="object", description="Response data")
 *     )
 * )
 */
abstract class Controller
{
    //
}
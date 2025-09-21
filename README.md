# Hospital and Clinician Group Management API

A streamlined Laravel REST API for managing hierarchical hospital and clinician group structures. This API provides essential CRUD operations for groups, ensures data integrity, and includes robust authentication and authorization.

## Features

- **Hierarchical Group Management**: Create and manage hospitals and clinician groups in a tree structure
- **Data Integrity**: Prevents cycles, ensures parent/child consistency, and handles safe deletes
- **Authentication & Authorization**: Secure API endpoints with Laravel Sanctum
- **Streamlined API**: Focused on core CRUD operations for optimal performance
- **Comprehensive Testing**: Unit and integration tests with high coverage
- **API Documentation**: Complete Swagger/OpenAPI documentation
- **SOLID Principles**: Clean architecture with Repository and Service patterns
- **Error Handling**: Robust error handling and validation

## Requirements

- PHP 8.2 or higher
- Composer
- MySQL/SQLite database
- Laravel 11.x

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd hospital-clinic-management-api
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database configuration**
   
   For SQLite (default):
   ```bash
   touch database/database.sqlite
   ```
   
   For MySQL, update your `.env` file:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hospital_api
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Generate API documentation**
   ```bash
   php artisan l5-swagger:generate
   ```

7. **Start the development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000`

## API Documentation

Once the server is running, you can access the interactive API documentation at:
- **Swagger UI**: `http://localhost:8000/api/documentation`

## API Endpoints

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/v1/register` | Register a new user | No |
| POST | `/api/v1/login` | Login user | No |
| POST | `/api/v1/logout` | Logout user | Yes |
| GET | `/api/v1/user` | Get authenticated user | Yes |

### Groups

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/groups` | Get all groups | Yes |
| POST | `/api/v1/groups` | Create a new group | Yes |
| GET | `/api/v1/groups/{id}` | Get specific group | Yes |
| PUT | `/api/v1/groups/{id}` | Update group | Yes |
| DELETE | `/api/v1/groups/{id}` | Delete group | Yes |

### Query Parameters

#### GET /api/v1/groups
- `type`: Filter by group type (`hospital` or `clinician_group`)
- `is_active`: Filter by active status (`true` or `false`)
- `level`: Filter by hierarchy level (integer)
- `parent_id`: Filter by parent ID (use `null` for root groups)
- `search`: Search in name and description
- `tree`: Return as tree structure (`true` or `false`)

#### DELETE /api/v1/groups/{id}
- `force_delete_children`: Force delete all children recursively (`true` or `false`)

## Usage Examples

### 1. Register and Login

```bash
# Register a new user
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### 2. Create a Hospital

```bash
curl -X POST http://localhost:8000/api/v1/groups \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "General Hospital",
    "description": "A general hospital providing comprehensive healthcare",
    "type": "hospital",
    "is_active": true
  }'
```

### 3. Create a Department under the Hospital

```bash
curl -X POST http://localhost:8000/api/v1/groups \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Cardiology Department",
    "description": "Department specializing in heart conditions",
    "type": "clinician_group",
    "parent_id": 1,
    "is_active": true
  }'
```

### 4. Get the Complete Tree Structure

```bash
curl -X GET "http://localhost:8000/api/v1/groups?tree=true" \
  -H "Authorization: Bearer YOUR_TOKEN"
```


## Data Structure

### Group Model

```json
{
  "id": 1,
  "name": "Cardiology Department",
  "description": "Department specializing in heart conditions",
  "type": "clinician_group",
  "parent_id": 1,
  "level": 1,
  "path": "1/2",
  "is_active": true,
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z",
  "parent": {
    "id": 1,
    "name": "General Hospital",
    "type": "hospital"
  },
  "children": []
}
```

### Response Format

All API responses follow a consistent format:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

## Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites
```bash
# Unit tests
php artisan test --testsuite=Unit

# Feature tests
php artisan test --testsuite=Feature

# Specific test class
php artisan test tests/Unit/GroupServiceTest.php
```

### Test Coverage
```bash
php artisan test --coverage
```

## Architecture

The application follows SOLID principles and clean architecture:

### Layers

1. **Controllers** (`app/Http/Controllers/Api/`)
   - Handle HTTP requests and responses
   - Validate input using Form Requests
   - Delegate business logic to Services

2. **Services** (`app/Services/`)
   - Contain business logic
   - Handle transactions
   - Coordinate between Repositories

3. **Repositories** (`app/Repositories/`)
   - Handle data access
   - Abstract database operations
   - Provide clean interface for data manipulation

4. **Models** (`app/Models/`)
   - Define data structure and relationships
   - Handle model events and scopes
   - Provide business logic methods

5. **Form Requests** (`app/Http/Requests/`)
   - Validate incoming data
   - Provide custom validation rules
   - Handle authorization

### Key Features

- **Hierarchical Structure**: Self-referencing foreign key with materialized path
- **Cycle Prevention**: Validates parent-child relationships to prevent cycles
- **Soft Deletes**: Groups are soft deleted to maintain referential integrity
- **Data Integrity**: Foreign key constraints and validation rules
- **Performance**: Indexed columns for efficient queries

## Security

- **Authentication**: Laravel Sanctum for API token authentication
- **Authorization**: Middleware-based route protection
- **Validation**: Comprehensive input validation
- **SQL Injection Prevention**: Eloquent ORM with parameterized queries
- **CSRF Protection**: Built-in Laravel CSRF protection

## Error Handling

The API provides comprehensive error handling:

- **Validation Errors**: 422 status with detailed validation messages
- **Authentication Errors**: 401 status for unauthorized access
- **Not Found Errors**: 404 status for missing resources
- **Business Logic Errors**: 409 status for constraint violations
- **Server Errors**: 500 status with error logging


## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.


## Changelog

### Version 1.0.0
- Initial release
- Complete CRUD operations for groups
- Hierarchical structure support
- Authentication and authorization
- Comprehensive testing
- API documentation
- Streamlined API with core functionality only
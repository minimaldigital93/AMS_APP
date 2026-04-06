# Laravel API - Mobile Application Integration Guide

## Status Assessment
Your Laravel API has a **good foundation** but needs **5 essential improvements** before production use with mobile apps.

---

## 🔴 Critical Issues & Solutions

### Issue 1: CORS Configuration
**Problem:** Mobile apps from different origins will receive CORS errors

**Solution:** Create CORS middleware

1. Create `app/Http/Middleware/HandleCors.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400');
    }
}
```

2. Add to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => RoleMiddleware::class,
    ]);
    
    $middleware->api(append: [
        \App\Http\Middleware\HandleCors::class,
    ]);
    
    $middleware->statefulApi();
})
```

3. Add to `.env`:
```
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://yourmobileapp.com,https://yourdomain.com
```

---

### Issue 2: Rate Limiting
**Problem:** No protection against brute force attacks

**Solution:** Apply to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    // ... existing code ...
    
    // Rate limiting
    $middleware->throttleApi(limit: '60,1'); // 60 requests per minute
})
```

Add specific auth throttling in `routes/api.php`:
```php
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1'); // 5 per minute
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');     // 5 per minute
});
```

---

### Issue 3: Standardized Error Responses
**Problem:** Inconsistent error handling will confuse mobile apps

**Solution:** Modify `bootstrap/app.php`:
```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => $e instanceof HttpException ? $e->getStatusCode() : 500,
                'errors' => config('app.debug') ? $e->getTrace() : null,
            ], $e instanceof HttpException ? $e->getStatusCode() : 500);
        }
    });
})
```

---

### Issue 4: API Versioning
**Problem:** Future changes could break existing mobile apps

**Solution:** Update `routes/api.php`:
```php
Route::prefix('v1')->group(function () {
    // PUBLIC ROUTES
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // AUTHENTICATED ROUTES
    Route::middleware('auth:sanctum')->group(function () {
        // ... all existing routes ...
    });
});
```

---

### Issue 5: Pagination with Metadata
**Problem:** Mobile apps need to know total items, current page, etc.

**Solution:** Create `app/Http/Resources/PaginatedResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginatedResource extends ResourceCollection
{
    public $collects;

    public function __construct($resource, $collects = null)
    {
        parent::__construct($resource);
        $this->collects = $collects;
    }

    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'pagination' => [
                'total' => $this->resource->total(),
                'count' => $this->resource->count(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'total_pages' => $this->resource->lastPage(),
                'has_more' => $this->resource->hasMorePages(),
            ],
        ];
    }
}
```

Usage in controllers:
```php
public function index(Request $request)
{
    $apartments = Apartments::paginate(15);
    return new PaginatedResource($apartments, ApartmentResource::class);
}
```

---

## 🟡 Recommended Enhancements

### 1. Add Refresh Token Support
```php
// In AuthController@login
$token = $user->createToken('auth_token', ['*'], now()->addHours(1));
$refreshToken = $user->createToken('refresh_token', ['*'], now()->addDays(7));

return response()->json([
    'access_token' => $token->plainTextToken,
    'refresh_token' => $refreshToken->plainTextToken,
    'token_type' => 'Bearer',
    'expires_in' => 3600,
]);
```

### 2. Add Request Validation Resources
```php
// Create app/Http/Resources/ValidationErrorResource.php
public function toArray($request)
{
    return [
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $this->resource,
    ];
}
```

### 3. Add API Documentation
Use Laravel API Resource Documentation or OpenAPI/Swagger for mobile developers.

### 4. Add Logging for API Calls
Track API usage and errors for debugging.

```php
// In middleware
Log::info('API Request', [
    'method' => $request->method(),
    'path' => $request->path(),
    'user_id' => auth('sanctum')->id(),
]);
```

### 5. MongoDB or Query Optimization
- Add database indexing on frequently queried columns
- Use `select()` to limit fields returned to mobile apps

---

## ✅ Best Practices Checklist

- [ ] Enable CORS with specific origins (not `*` in production)
- [ ] Implement rate limiting (5-10 req/min for auth, 60-100 for general)
- [ ] Return consistent JSON format: `{ success, message, data, errors }`
- [ ] Use timestamps in responses (`created_at`, `updated_at`)
- [ ] Include pagination metadata in list endpoints
- [ ] Return proper HTTP status codes (200, 201, 400, 401, 403, 404, 500)
- [ ] Use short, descriptive error messages for mobile users
- [ ] Hide sensitive data (passwords, tokens) from responses
- [ ] Implement token expiration and refresh logic
- [ ] Test with Postman/Insomnia before mobile app integration

---

## 📱 Testing the API

Use these commands to test:

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Protected route
curl -X GET http://localhost:8000/api/v1/admin/dashboard/stats \
  -H "Authorization: Bearer YOUR_TOKEN"

# Check CORS
curl -X OPTIONS http://localhost:8000/api/v1/auth/login \
  -H "Origin: http://localhost:3000"
```

---

## Summary

| Issue | Severity | Time to Fix |
|-------|----------|-----------|
| CORS Configuration | 🔴 Critical | 10 min |
| Rate Limiting | 🔴 Critical | 5 min |
| Error Standardization | 🟠 Important | 15 min |
| API Versioning | 🟠 Important | 5 min |
| Pagination Metadata | 🟠 Important | 20 min |
| Refresh Tokens | 🟡 Recommended | 15 min |

**Total Estimated Implementation Time: 1-2 hours**

Once these changes are made, your API will be **production-ready for mobile applications**! 🚀

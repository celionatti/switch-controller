# Switch Controller (`switch/controller`)

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/celionatti/switch-controller)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://php.net)

**Switch Controller** provides the base controller architecture, high-speed request validation, middleware management, JSON response builders, and Switch Live integration helpers for the **Switch Framework**.

---

## ⚡ Features

- 🎮 **Base Controller (`Controller`)**: View rendering, JSON responses, redirects, and Switch Live helpers.
- 🛡️ **Built-in Fast Validator**: Rules for `required`, `email`, `min`, `max`, `numeric`, `integer`, `in`, `confirmed`, `url`, `regex`.
- 🧩 **Controller-Level Middleware**: Register middleware with `only` / `except` filters.
- 🚀 **Switch Live Helpers**: Direct access to `toast()`, `emit()`, `liveRedirect()`, `target()`, `title()`, and `preserveScroll()`.
- 📦 **ResourceController Interface**: Standardized CRUD contract for API and web controllers.

---

## 📦 Installation

```bash
composer require switch/controller
```

---

## 🚀 Quick Usage

### 1. Extending the Base Controller

```php
namespace App\Controllers;

use Switch\Controller\Controller;
use Psr\Http\Message\ServerRequestInterface;

class UserController extends Controller
{
    public function __construct()
    {
        // Register middleware on specific actions
        $this->middleware('AuthMiddleware', ['except' => ['index', 'show']]);
    }

    public function index()
    {
        return $this->view('users.index', [
            'users' => User::all()
        ]);
    }

    public function store(ServerRequestInterface $request)
    {
        // Fast Request Validation
        $validated = $this->validate($request, [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = User::create($validated);

        // Switch Live Toast Notification
        $this->toast("User {$user->name} created successfully!", 'success');

        return $this->redirect('/users');
    }

    public function apiIndex()
    {
        // Fast JSON Response
        return $this->json([
            'status' => 'success',
            'data' => User::all()
        ]);
    }
}
```

---

## 🛡️ Validation Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Value must be non-empty | `'name' => 'required'` |
| `email` | Must be a valid email | `'email' => 'required\|email'` |
| `min:val` | Minimum string length / number / array count | `'password' => 'min:6'` |
| `max:val` | Maximum string length / number / array count | `'bio' => 'max:500'` |
| `numeric` | Must be numeric | `'price' => 'required\|numeric'` |
| `integer` | Must be an integer | `'age' => 'required\|integer'` |
| `in:a,b,c` | Must match one of the allowed values | `'role' => 'in:admin,user'` |
| `confirmed` | Must match `{field}_confirmation` | `'password' => 'confirmed'` |
| `url` | Must be a valid URL | `'website' => 'url'` |
| `regex:/.../` | Must match regex pattern | `'code' => 'regex:/^[A-Z0-9]+$/'` |

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The Switch Controller package is open-source software licensed under the [MIT license](LICENSE).

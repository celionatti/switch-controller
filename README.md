# Switch Controller (`switch/controller`)

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/celionatti/switch-controller)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://php.net)

**Switch Controller** provides the base controller architecture, high-speed request validation (with database `unique` / `exists` support), middleware management, JSON response builders, and Switch Live integration helpers for the **Switch Framework**.

---

## ⚡ Features

- 🎮 **Base Controller (`Controller`)**: View rendering, JSON responses, redirects, and Switch Live helpers.
- 🛡️ **Built-in Fast Validator**: 30+ rules including `unique`, `exists`, `email`, `between`, `digits`, `date`, `uuid`, `alpha_dash`, `nullable`, and custom closures.
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
use App\Models\User;

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
        // Fast Request Validation with Unique check
        $validated = $this->validate($request, [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'role_id' => 'required|exists:roles,id',
            'bio' => 'nullable|max:500'
        ]);

        $user = User::create($validated);

        // Switch Live Toast Notification
        $this->toast("User {$user->name} created successfully!", 'success');

        return $this->redirect('/users');
    }

    public function update(ServerRequestInterface $request, int $id)
    {
        // Unique check ignoring current user ID
        $validated = $this->validate($request, [
            'email' => "required|email|unique:users,email,{$id},id",
            'name' => 'required|min:2'
        ]);

        User::findOrFail($id)->update($validated);
        $this->toast('User updated successfully!', 'success');

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

## 🛡️ Validation Rules Reference

### 🗄️ Database Rules

| Rule | Description | Example |
|------|-------------|---------|
| `unique:table,column,exceptId,idColumn` | Checks value is unique in table | `'email' => 'unique:users,email'`<br/>`'email' => 'unique:users,email,42,id'` |
| `exists:table,column` | Checks value exists in table | `'category_id' => 'exists:categories,id'` |

### 🔤 String & Format Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Must be present and non-empty | `'name' => 'required'` |
| `nullable` | Allows null/empty values | `'bio' => 'nullable\|max:500'` |
| `email` | Must be a valid email | `'email' => 'email'` |
| `url` | Must be a valid URL | `'website' => 'url'` |
| `ip` / `ipv4` / `ipv6` | Valid IP address | `'ip_address' => 'ipv4'` |
| `uuid` | Valid UUID format | `'device_id' => 'uuid'` |
| `json` | Must be a valid JSON string | `'payload' => 'json'` |
| `alpha` | Letters only | `'first_name' => 'alpha'` |
| `alpha_num` | Letters and numbers only | `'username' => 'alpha_num'` |
| `alpha_dash` | Letters, numbers, dashes, underscores | `'slug' => 'alpha_dash'` |
| `string` | Must be a string | `'title' => 'string'` |
| `boolean` | `true`, `false`, `1`, `0`, `'yes'`, `'no'` | `'is_active' => 'boolean'` |
| `accepted` | Must be accepted (`yes`, `on`, `1`, `true`) | `'terms' => 'accepted'` |
| `declined` | Must be declined (`no`, `off`, `0`, `false`) | `'opt_out' => 'declined'` |

### 📏 Size & Range Rules

| Rule | Description | Example |
|------|-------------|---------|
| `min:val` | Minimum string length / number / array count | `'password' => 'min:8'` |
| `max:val` | Maximum string length / number / array count | `'summary' => 'max:200'` |
| `between:min,max` | Value/length/count between min and max | `'age' => 'between:18,65'` |
| `digits:N` | Exact number of digits | `'pin' => 'digits:4'` |
| `digits_between:min,max` | Digits count in range | `'card' => 'digits_between:13,19'` |
| `size:val` | Exact length / value / count | `'code' => 'size:6'` |
| `numeric` | Must be numeric | `'price' => 'numeric'` |
| `integer` | Must be an integer | `'quantity' => 'integer'` |
| `array` | Must be a PHP array | `'tags' => 'array'` |

### 📅 Date & Time Rules

| Rule | Description | Example |
|------|-------------|---------|
| `date` | Valid date string | `'published_at' => 'date'` |
| `date_format:format` | Exact date format | `'dob' => 'date_format:Y-m-d'` |
| `before:date_or_field` | Date must be before date/field | `'start_date' => 'before:end_date'` |
| `after:date_or_field` | Date must be after date/field | `'end_date' => 'after:start_date'` |

### ⚖️ Comparison & Equality Rules

| Rule | Description | Example |
|------|-------------|---------|
| `in:a,b,c` | Must match one of allowed values | `'role' => 'in:admin,editor,user'` |
| `not_in:a,b,c` | Must not match disallowed values | `'tier' => 'not_in:banned,suspended'` |
| `confirmed` | Must match `{field}_confirmation` | `'password' => 'confirmed'` |
| `same:field` | Must match another field | `'new_email' => 'same:email_confirm'` |
| `different:field` | Must differ from another field | `'new_password' => 'different:old_password'` |
| `starts_with:a,b` | Must start with one of prefixes | `'url' => 'starts_with:http://,https://'` |
| `ends_with:a,b` | Must end with one of suffixes | `'file' => 'ends_with:.jpg,.png'` |
| `regex:/.../` | Must match regex pattern | `'code' => 'regex:/^[A-Z0-9]+$/'` |
| `not_regex:/.../` | Must not match regex pattern | `'username' => 'not_regex:/admin/i'` |

---

## 💡 Custom Rules & Extensibility

### Using Closures:

```php
$validated = $this->validate($request, [
    'promo_code' => [
        'required',
        fn($val) => $val === 'SWITCH2026' ? true : 'The promo code is expired or invalid.'
    ]
]);
```

### Global Custom Rules:

```php
use Switch\Controller\Validation\Validator;

Validator::extend('phone', function ($field, $value) {
    return preg_match('/^\+?[1-9]\d{1,14}$/', (string) $value);
});

// Now available everywhere:
$this->validate($request, ['mobile' => 'required|phone']);
```

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The Switch Controller package is open-source software licensed under the [MIT license](LICENSE).

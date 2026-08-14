<?php

declare(strict_types=1);

namespace Switch\Controller\Validation;

use DateTime;
use Closure;
use PDO;

class Validator
{
    /**
     * @var array<string, callable> Registered custom validation rules
     */
    private static array $customRules = [];

    /**
     * @var callable|null Database resolver callback returning a PDO or Connection instance
     */
    private static $databaseResolver = null;

    /**
     * Register a custom validation rule globally.
     *
     * @param string $name Rule name (e.g. 'phone')
     * @param callable $callback fn($attribute, $value, $parameters, $validator): bool|string
     */
    public static function extend(string $name, callable $callback): void
    {
        self::$customRules[strtolower($name)] = $callback;
    }

    /**
     * Set custom database connection resolver for unique/exists rules.
     *
     * @param callable $resolver fn(): \PDO|\Switch\Database\Connection\Connection
     */
    public static function setDatabaseResolver(callable $resolver): void
    {
        self::$databaseResolver = $resolver;
    }

    /**
     * Validate data against specified rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string|Closure>> $rules
     * @param array<string, string> $customMessages
     * @return array<string, mixed> Validated data subset
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, array $customMessages = []): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $value = $data[$field] ?? null;
            $hasValue = $value !== null && $value !== '';

            // Check for nullable rule
            $isNullable = false;
            foreach ($fieldRules as $r) {
                if (is_string($r) && strtolower(trim($r)) === 'nullable') {
                    $isNullable = true;
                    break;
                }
            }

            if (!$hasValue && $isNullable) {
                $validated[$field] = null;
                continue;
            }

            foreach ($fieldRules as $ruleItem) {
                if ($ruleItem instanceof Closure) {
                    $result = $ruleItem($value, $field, $data);
                    if ($result === false) {
                        $errors[$field][] = $customMessages["{$field}.custom"] ?? "The {$field} field is invalid.";
                    } elseif (is_string($result)) {
                        $errors[$field][] = $result;
                    }
                    continue;
                }

                $parts = explode(':', (string) $ruleItem, 2);
                $rule = strtolower(trim($parts[0]));
                $param = $parts[1] ?? null;

                if ($rule === 'nullable') {
                    continue;
                }

                if ($rule === 'required') {
                    if (!$hasValue) {
                        $errors[$field][] = $customMessages["{$field}.required"] ?? "The {$field} field is required.";
                        continue 2; // Stop further validation for this field
                    }
                    continue;
                }

                if (!$hasValue) {
                    continue; // Skip remaining rules if optional and empty
                }

                // Check custom registered rules first
                if (isset(self::$customRules[$rule])) {
                    $callback = self::$customRules[$rule];
                    $params = $param !== null ? explode(',', $param) : [];
                    $res = $callback($field, $value, $params, $data);
                    if ($res === false) {
                        $errors[$field][] = $customMessages["{$field}.{$rule}"] ?? "The {$field} field is invalid.";
                    } elseif (is_string($res)) {
                        $errors[$field][] = $res;
                    }
                    continue;
                }

                switch ($rule) {
                    // -------------------------------------------------------------
                    // Database Rules
                    // -------------------------------------------------------------
                    case 'unique':
                        // Format: unique:table,column,exceptId,idColumn
                        if (!self::validateUnique($field, $value, $param)) {
                            $errors[$field][] = $customMessages["{$field}.unique"] ?? "The {$field} has already been taken.";
                        }
                        break;

                    case 'exists':
                        // Format: exists:table,column
                        if (!self::validateExists($field, $value, $param)) {
                            $errors[$field][] = $customMessages["{$field}.exists"] ?? "The selected {$field} is invalid.";
                        }
                        break;

                    // -------------------------------------------------------------
                    // String & Format Rules
                    // -------------------------------------------------------------
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = $customMessages["{$field}.email"] ?? "The {$field} field must be a valid email address.";
                        }
                        break;

                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field][] = $customMessages["{$field}.url"] ?? "The {$field} field must be a valid URL.";
                        }
                        break;

                    case 'ip':
                        if (!filter_var($value, FILTER_VALIDATE_IP)) {
                            $errors[$field][] = $customMessages["{$field}.ip"] ?? "The {$field} field must be a valid IP address.";
                        }
                        break;

                    case 'ipv4':
                        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            $errors[$field][] = $customMessages["{$field}.ipv4"] ?? "The {$field} field must be a valid IPv4 address.";
                        }
                        break;

                    case 'ipv6':
                        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                            $errors[$field][] = $customMessages["{$field}.ipv6"] ?? "The {$field} field must be a valid IPv6 address.";
                        }
                        break;

                    case 'uuid':
                        if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                            $errors[$field][] = $customMessages["{$field}.uuid"] ?? "The {$field} field must be a valid UUID.";
                        }
                        break;

                    case 'json':
                        if (!is_string($value) || json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
                            $errors[$field][] = $customMessages["{$field}.json"] ?? "The {$field} field must be a valid JSON string.";
                        }
                        break;

                    case 'alpha':
                        if (!is_string($value) || !preg_match('/^[\pL\pM]+$/u', $value)) {
                            $errors[$field][] = $customMessages["{$field}.alpha"] ?? "The {$field} field must only contain letters.";
                        }
                        break;

                    case 'alpha_num':
                    case 'alphanumeric':
                        if (!is_string($value) || !preg_match('/^[\pL\pM\pN]+$/u', $value)) {
                            $errors[$field][] = $customMessages["{$field}.alpha_num"] ?? "The {$field} field must only contain letters and numbers.";
                        }
                        break;

                    case 'alpha_dash':
                        if (!is_string($value) || !preg_match('/^[\pL\pM\pN_-]+$/u', $value)) {
                            $errors[$field][] = $customMessages["{$field}.alpha_dash"] ?? "The {$field} field may only contain letters, numbers, dashes, and underscores.";
                        }
                        break;

                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field][] = $customMessages["{$field}.string"] ?? "The {$field} field must be a string.";
                        }
                        break;

                    case 'boolean':
                    case 'bool':
                        $acceptable = [true, false, 0, 1, '0', '1', 'true', 'false', 'yes', 'no'];
                        if (!in_array($value, $acceptable, true)) {
                            $errors[$field][] = $customMessages["{$field}.boolean"] ?? "The {$field} field must be true or false.";
                        }
                        break;

                    case 'accepted':
                        $acceptable = ['yes', 'on', '1', 1, true, 'true'];
                        if (!in_array($value, $acceptable, true)) {
                            $errors[$field][] = $customMessages["{$field}.accepted"] ?? "The {$field} must be accepted.";
                        }
                        break;

                    case 'declined':
                        $acceptable = ['no', 'off', '0', 0, false, 'false'];
                        if (!in_array($value, $acceptable, true)) {
                            $errors[$field][] = $customMessages["{$field}.declined"] ?? "The {$field} must be declined.";
                        }
                        break;

                    // -------------------------------------------------------------
                    // Size & Range Rules
                    // -------------------------------------------------------------
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field][] = $customMessages["{$field}.numeric"] ?? "The {$field} field must be a number.";
                        }
                        break;

                    case 'integer':
                    case 'int':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $errors[$field][] = $customMessages["{$field}.integer"] ?? "The {$field} field must be an integer.";
                        }
                        break;

                    case 'array':
                        if (!is_array($value)) {
                            $errors[$field][] = $customMessages["{$field}.array"] ?? "The {$field} field must be an array.";
                        }
                        break;

                    case 'min':
                        $min = (float) $param;
                        if (is_numeric($value)) {
                            if ((float) $value < $min) {
                                $errors[$field][] = $customMessages["{$field}.min"] ?? "The {$field} field must be at least {$min}.";
                            }
                        } elseif (is_string($value) && mb_strlen($value) < (int) $min) {
                            $errors[$field][] = $customMessages["{$field}.min"] ?? "The {$field} field must be at least " . (int) $min . " characters.";
                        } elseif (is_array($value) && count($value) < (int) $min) {
                            $errors[$field][] = $customMessages["{$field}.min"] ?? "The {$field} field must have at least " . (int) $min . " items.";
                        }
                        break;

                    case 'max':
                        $max = (float) $param;
                        if (is_numeric($value)) {
                            if ((float) $value > $max) {
                                $errors[$field][] = $customMessages["{$field}.max"] ?? "The {$field} field must not exceed {$max}.";
                            }
                        } elseif (is_string($value) && mb_strlen($value) > (int) $max) {
                            $errors[$field][] = $customMessages["{$field}.max"] ?? "The {$field} field must not exceed " . (int) $max . " characters.";
                        } elseif (is_array($value) && count($value) > (int) $max) {
                            $errors[$field][] = $customMessages["{$field}.max"] ?? "The {$field} field must not have more than " . (int) $max . " items.";
                        }
                        break;

                    case 'between':
                        $range = $param !== null ? explode(',', $param) : [0, 0];
                        $min = (float) ($range[0] ?? 0);
                        $max = (float) ($range[1] ?? 0);

                        if (is_numeric($value)) {
                            $num = (float) $value;
                            if ($num < $min || $num > $max) {
                                $errors[$field][] = $customMessages["{$field}.between"] ?? "The {$field} field must be between {$min} and {$max}.";
                            }
                        } elseif (is_string($value)) {
                            $len = mb_strlen($value);
                            if ($len < (int) $min || $len > (int) $max) {
                                $errors[$field][] = $customMessages["{$field}.between"] ?? "The {$field} field must be between " . (int) $min . " and " . (int) $max . " characters.";
                            }
                        } elseif (is_array($value)) {
                            $cnt = count($value);
                            if ($cnt < (int) $min || $cnt > (int) $max) {
                                $errors[$field][] = $customMessages["{$field}.between"] ?? "The {$field} field must have between " . (int) $min . " and " . (int) $max . " items.";
                            }
                        }
                        break;

                    case 'digits':
                        $len = (int) $param;
                        if (!is_numeric($value) || strlen((string) $value) !== $len) {
                            $errors[$field][] = $customMessages["{$field}.digits"] ?? "The {$field} field must be exactly {$len} digits.";
                        }
                        break;

                    case 'digits_between':
                        $range = $param !== null ? explode(',', $param) : [0, 0];
                        $min = (int) ($range[0] ?? 0);
                        $max = (int) ($range[1] ?? 0);
                        $len = strlen((string) $value);
                        if (!is_numeric($value) || $len < $min || $len > $max) {
                            $errors[$field][] = $customMessages["{$field}.digits_between"] ?? "The {$field} field must be between {$min} and {$max} digits.";
                        }
                        break;

                    case 'size':
                        $size = (int) $param;
                        if (is_numeric($value)) {
                            if ((float) $value !== (float) $size) {
                                $errors[$field][] = $customMessages["{$field}.size"] ?? "The {$field} field must be {$size}.";
                            }
                        } elseif (is_string($value) && mb_strlen($value) !== $size) {
                            $errors[$field][] = $customMessages["{$field}.size"] ?? "The {$field} field must be exactly {$size} characters.";
                        } elseif (is_array($value) && count($value) !== $size) {
                            $errors[$field][] = $customMessages["{$field}.size"] ?? "The {$field} field must contain exactly {$size} items.";
                        }
                        break;

                    // -------------------------------------------------------------
                    // Date & Time Rules
                    // -------------------------------------------------------------
                    case 'date':
                        if (!is_string($value) || strtotime($value) === false) {
                            $errors[$field][] = $customMessages["{$field}.date"] ?? "The {$field} field must be a valid date.";
                        }
                        break;

                    case 'date_format':
                        $d = DateTime::createFromFormat((string) $param, (string) $value);
                        if (!$d || $d->format((string) $param) !== (string) $value) {
                            $errors[$field][] = $customMessages["{$field}.date_format"] ?? "The {$field} does not match the format {$param}.";
                        }
                        break;

                    case 'before':
                        $time = strtotime((string) $value);
                        $target = isset($data[$param]) ? strtotime((string) $data[$param]) : strtotime((string) $param);
                        if ($time === false || $target === false || $time >= $target) {
                            $errors[$field][] = $customMessages["{$field}.before"] ?? "The {$field} must be a date before {$param}.";
                        }
                        break;

                    case 'after':
                        $time = strtotime((string) $value);
                        $target = isset($data[$param]) ? strtotime((string) $data[$param]) : strtotime((string) $param);
                        if ($time === false || $target === false || $time <= $target) {
                            $errors[$field][] = $customMessages["{$field}.after"] ?? "The {$field} must be a date after {$param}.";
                        }
                        break;

                    // -------------------------------------------------------------
                    // Comparison Rules
                    // -------------------------------------------------------------
                    case 'in':
                        $allowed = $param !== null ? explode(',', $param) : [];
                        if (!in_array((string) $value, $allowed, true)) {
                            $errors[$field][] = $customMessages["{$field}.in"] ?? "The selected {$field} is invalid.";
                        }
                        break;

                    case 'not_in':
                        $disallowed = $param !== null ? explode(',', $param) : [];
                        if (in_array((string) $value, $disallowed, true)) {
                            $errors[$field][] = $customMessages["{$field}.not_in"] ?? "The selected {$field} is invalid.";
                        }
                        break;

                    case 'confirmed':
                        $confirmationKey = $field . '_confirmation';
                        $confirmationVal = $data[$confirmationKey] ?? null;
                        if ($value !== $confirmationVal) {
                            $errors[$field][] = $customMessages["{$field}.confirmed"] ?? "The {$field} confirmation does not match.";
                        }
                        break;

                    case 'same':
                        $otherVal = $data[$param] ?? null;
                        if ($value !== $otherVal) {
                            $errors[$field][] = $customMessages["{$field}.same"] ?? "The {$field} and {$param} must match.";
                        }
                        break;

                    case 'different':
                        $otherVal = $data[$param] ?? null;
                        if ($value === $otherVal) {
                            $errors[$field][] = $customMessages["{$field}.different"] ?? "The {$field} and {$param} must be different.";
                        }
                        break;

                    case 'starts_with':
                        $prefixes = $param !== null ? explode(',', $param) : [];
                        $matched = false;
                        foreach ($prefixes as $p) {
                            if (str_starts_with((string) $value, $p)) {
                                $matched = true;
                                break;
                            }
                        }
                        if (!$matched) {
                            $errors[$field][] = $customMessages["{$field}.starts_with"] ?? "The {$field} must start with one of: {$param}.";
                        }
                        break;

                    case 'ends_with':
                        $suffixes = $param !== null ? explode(',', $param) : [];
                        $matched = false;
                        foreach ($suffixes as $s) {
                            if (str_ends_with((string) $value, $s)) {
                                $matched = true;
                                break;
                            }
                        }
                        if (!$matched) {
                            $errors[$field][] = $customMessages["{$field}.ends_with"] ?? "The {$field} must end with one of: {$param}.";
                        }
                        break;

                    case 'regex':
                        if ($param !== null && !preg_match($param, (string) $value)) {
                            $errors[$field][] = $customMessages["{$field}.regex"] ?? "The {$field} format is invalid.";
                        }
                        break;

                    case 'not_regex':
                        if ($param !== null && preg_match($param, (string) $value)) {
                            $errors[$field][] = $customMessages["{$field}.not_regex"] ?? "The {$field} format is invalid.";
                        }
                        break;
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $validated;
    }

    /**
     * Validate database uniqueness.
     * Format: unique:table,column,exceptId,idColumn
     */
    private static function validateUnique(string $field, mixed $value, ?string $param): bool
    {
        if ($param === null) {
            return true;
        }

        $parts = explode(',', $param);
        $table = trim($parts[0] ?? '');
        $column = trim($parts[1] ?? $field);
        $exceptId = isset($parts[2]) && trim($parts[2]) !== '' ? trim($parts[2]) : null;
        $idColumn = trim($parts[3] ?? 'id');

        $pdo = self::resolvePdo();
        if ($pdo === null) {
            return true; // If no database connection available, skip database check
        }

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
        $bindings = [':value' => $value];

        if ($exceptId !== null) {
            $sql .= " AND `{$idColumn}` != :exceptId";
            $bindings[':exceptId'] = $exceptId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Validate database existence.
     * Format: exists:table,column
     */
    private static function validateExists(string $field, mixed $value, ?string $param): bool
    {
        if ($param === null) {
            return true;
        }

        $parts = explode(',', $param);
        $table = trim($parts[0] ?? '');
        $column = trim($parts[1] ?? $field);

        $pdo = self::resolvePdo();
        if ($pdo === null) {
            return true;
        }

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':value' => $value]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Resolve active PDO connection from resolver, Model, or ConnectionManager.
     */
    private static function resolvePdo(): ?PDO
    {
        if (self::$databaseResolver !== null) {
            $res = (self::$databaseResolver)();
            if ($res instanceof PDO) {
                return $res;
            }
            if (is_object($res) && method_exists($res, 'getPdo')) {
                return $res->getPdo();
            }
        }

        if (class_exists(\Switch\Database\DB::class)) {
            try {
                return \Switch\Database\DB::getPdo();
            } catch (\Throwable) {
                // Continue to next fallback
            }
        }

        if (class_exists(\Switch\Database\ORM\Model::class) && \Switch\Database\ORM\Model::hasConnection()) {
            return \Switch\Database\ORM\Model::getConnection()->getPdo();
        }

        if (class_exists(\Switch\Database\Connection\ConnectionManager::class)) {
            try {
                if (method_exists(\Switch\Database\Connection\ConnectionManager::class, 'getInstance')) {
                    return \Switch\Database\Connection\ConnectionManager::getInstance()->connection()->getPdo();
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}

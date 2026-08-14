<?php

declare(strict_types=1);

namespace Switch\Controller\Validation;

class Validator
{
    /**
     * Validate data against specified rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string>> $rules
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

            foreach ($fieldRules as $ruleStr) {
                $parts = explode(':', $ruleStr, 2);
                $rule = strtolower(trim($parts[0]));
                $param = $parts[1] ?? null;

                if ($rule === 'required' && !$hasValue) {
                    $errors[$field][] = $customMessages["{$field}.required"] ?? "The {$field} field is required.";
                    continue 2; // Stop further validation on empty required field
                }

                if (!$hasValue) {
                    continue; // Skip optional rules if empty and not required
                }

                switch ($rule) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = $customMessages["{$field}.email"] ?? "The {$field} field must be a valid email address.";
                        }
                        break;

                    case 'min':
                        $min = (int) $param;
                        if (is_numeric($value)) {
                            if ((float) $value < $min) {
                                $errors[$field][] = $customMessages["{$field}.min"] ?? "The {$field} field must be at least {$min}.";
                            }
                        } elseif (is_string($value) && mb_strlen($value) < $min) {
                            $errors[$field][] = $customMessages["{$field}.min"] ?? "The {$field} field must be at least {$min} characters.";
                        } elseif (is_array($value) && count($value) < $min) {
                            $errors[$field][] = $customMessages["{$field}.min"] ?? "The {$field} field must have at least {$min} items.";
                        }
                        break;

                    case 'max':
                        $max = (int) $param;
                        if (is_numeric($value)) {
                            if ((float) $value > $max) {
                                $errors[$field][] = $customMessages["{$field}.max"] ?? "The {$field} field must not exceed {$max}.";
                            }
                        } elseif (is_string($value) && mb_strlen($value) > $max) {
                            $errors[$field][] = $customMessages["{$field}.max"] ?? "The {$field} field must not exceed {$max} characters.";
                        } elseif (is_array($value) && count($value) > $max) {
                            $errors[$field][] = $customMessages["{$field}.max"] ?? "The {$field} field must not have more than {$max} items.";
                        }
                        break;

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

                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field][] = $customMessages["{$field}.string"] ?? "The {$field} field must be a string.";
                        }
                        break;

                    case 'in':
                        $allowed = $param !== null ? explode(',', $param) : [];
                        if (!in_array((string) $value, $allowed, true)) {
                            $errors[$field][] = $customMessages["{$field}.in"] ?? "The selected {$field} is invalid.";
                        }
                        break;

                    case 'confirmed':
                        $confirmationKey = $field . '_confirmation';
                        $confirmationVal = $data[$confirmationKey] ?? null;
                        if ($value !== $confirmationVal) {
                            $errors[$field][] = $customMessages["{$field}.confirmed"] ?? "The {$field} confirmation does not match.";
                        }
                        break;

                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field][] = $customMessages["{$field}.url"] ?? "The {$field} field must be a valid URL.";
                        }
                        break;

                    case 'regex':
                        if ($param !== null && !preg_match($param, (string) $value)) {
                            $errors[$field][] = $customMessages["{$field}.regex"] ?? "The {$field} format is invalid.";
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
}

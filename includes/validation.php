<?php
/**
 * ShopSphere - Validation Engine
 * Phase 3: Backend & API Foundation
 */

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Check that required fields are present and not empty.
     */
    public function required(array $fields): self {
        foreach ($fields as $field => $label) {
            $name = is_numeric($field) ? $label : $field;
            $display = is_numeric($field) ? ucfirst(str_replace('_', ' ', $name)) : $label;

            if (!isset($this->data[$name]) || trim((string)$this->data[$name]) === '') {
                $this->addError($name, "{$display} is required.");
            }
        }
        return $this;
    }

    /**
     * Validate standard RFC email format.
     */
    public function email(string $field, string $label = 'Email address'): self {
        if (!empty($this->data[$field])) {
            $val = trim((string)$this->data[$field]);
            if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, "{$label} must be a valid email format.");
            }
        }
        return $this;
    }

    /**
     * Validate string length range.
     */
    public function length(string $field, int $min, int $max = 0, string $label = 'Field'): self {
        if (!empty($this->data[$field])) {
            $len = mb_strlen(trim((string)$this->data[$field]), 'UTF-8');
            if ($len < $min) {
                $this->addError($field, "{$label} must be at least {$min} characters.");
            } elseif ($max > 0 && $len > $max) {
                $this->addError($field, "{$label} must not exceed {$max} characters.");
            }
        }
        return $this;
    }

    /**
     * Validate numeric value with optional bounds.
     */
    public function numeric(string $field, ?float $min = null, ?float $max = null, string $label = 'Field'): self {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $val = $this->data[$field];
            if (!is_numeric($val)) {
                $this->addError($field, "{$label} must be a valid number.");
            } else {
                $num = (float)$val;
                if ($min !== null && $num < $min) {
                    $this->addError($field, "{$label} must be at least {$min}.");
                }
                if ($max !== null && $num > $max) {
                    $this->addError($field, "{$label} must not exceed {$max}.");
                }
            }
        }
        return $this;
    }

    /**
     * Check if validation passed.
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Check if validation failed.
     */
    public function fails(): bool {
        return !$this->passes();
    }

    /**
     * Retrieve all errors.
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Add an error message.
     */
    public function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}


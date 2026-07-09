<?php
declare(strict_types=1);

class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function validate(array $rules): bool {
        $this->errors = [];
        foreach ($rules as $field => $ruleSet) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleSet) as $rule) {
                $this->applyRule($field, $value, trim($rule));
            }
        }
        return $this->errors === [];
    }

    private function applyRule(string $field, mixed $value, string $rule): void {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        match ($ruleName) {
            'required' => $this->required($field, $value),
            'email'    => $this->email($field, $value),
            'phone'    => $this->phone($field, $value),
            'numeric'  => $this->numeric($field, $value),
            'integer'  => $this->integer($field, $value),
            'pattern'  => $this->pattern($field, $value, $param),
            'max'      => $this->max($field, $value, (int)$param),
            'min'      => $this->min($field, $value, (int)$param),
            'in'       => $this->in($field, $value, $param),
            default    => null,
        };
    }

    private function addError(string $field, string $message): void {
        $this->errors[$field] = $message;
    }

    private function required(string $field, mixed $value): void {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
            $this->addError($field, "$field is required");
        }
    }

    private function email(string $field, mixed $value): void {
        if ($value !== null && $value !== '' && !filter_var(trim((string)$value), FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "Invalid email format");
        }
    }

    private function phone(string $field, mixed $value): void {
        if ($value === null || $value === '') return;
        $cleaned = preg_replace('/[^0-9]/', '', (string)$value);
        if (!preg_match('/^[6-9][0-9]{9}$/', $cleaned)) {
            $this->addError($field, "Invalid phone number");
        }
    }

    private function numeric(string $field, mixed $value): void {
        if ($value !== null && $value !== '' && !is_numeric(trim((string)$value))) {
            $this->addError($field, "$field must be numeric");
        }
    }

    private function integer(string $field, mixed $value): void {
        if ($value === null || $value === '') return;
        if (!preg_match('/^-?\d+$/', trim((string)$value))) {
            $this->addError($field, "$field must be an integer");
        }
    }

    private function pattern(string $field, mixed $value, ?string $pattern): void {
        if ($value === null || $value === '' || $pattern === null) return;
        if (@preg_match('/^(?:' . $pattern . ')$/', (string)$value) !== 1) {
            $this->addError($field, "$field is invalid");
        }
    }

    private function max(string $field, mixed $value, int $max): void {
        if ($value === null || $value === '') return;
        if (is_numeric($value)) {
            if ((float)$value > $max) {
                $this->addError($field, "$field cannot be greater than $max");
            }
            return;
        }
        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, "$field cannot exceed $max characters");
        }
        if (is_array($value) && count($value) > $max) {
            $this->addError($field, "$field cannot have more than $max items");
        }
    }

    private function min(string $field, mixed $value, int $min): void {
        if ($value === null || $value === '') return;
        if (is_numeric($value)) {
            if ((float)$value < $min) {
                $this->addError($field, "$field must be at least $min");
            }
            return;
        }
        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, "$field must be at least $min characters");
        }
        if (is_array($value) && count($value) < $min) {
            $this->addError($field, "$field must have at least $min items");
        }
    }

    private function in(string $field, mixed $value, ?string $options): void {
        if ($value === null || $value === '' || $options === null) return;
        $allowed = array_map('trim', explode(',', $options));
        $value = trim((string)$value);
        if (!in_array($value, $allowed, true)) {
            $this->addError($field, "Invalid value for $field");
        }
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): ?string {
        return $this->errors ? reset($this->errors) : null;
    }
}
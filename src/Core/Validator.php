<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Простой валидатор. Правила: required, email, min:N, max:N, in:a,b,c, date, same:field.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, string> $data
     * @param array<string, string[]> $rules
     */
    public function __construct(private array $data, array $rules, private array $labels = [])
    {
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                if (isset($this->errors[$field])) {
                    break; // одна ошибка на поле
                }
                $this->apply($field, $rule);
            }
        }
    }

    private function apply(string $field, string $rule): void
    {
        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
        $value = $this->data[$field] ?? '';
        $label = $this->labels[$field] ?? $field;

        if ($name !== 'required' && $value === '') {
            return; // необязательное пустое поле
        }

        switch ($name) {
            case 'required':
                if ($value === '') {
                    $this->errors[$field] = "Заполните поле «{$label}».";
                }
                break;
            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->errors[$field] = 'Введите корректный email.';
                }
                break;
            case 'min':
                if (mb_strlen($value) < (int) $arg) {
                    $this->errors[$field] = "Минимум {$arg} " . plural((int) $arg, 'символ', 'символа', 'символов') . '.';
                }
                break;
            case 'max':
                if (mb_strlen($value) > (int) $arg) {
                    $this->errors[$field] = "Максимум {$arg} " . plural((int) $arg, 'символ', 'символа', 'символов') . '.';
                }
                break;
            case 'in':
                if (!in_array($value, explode(',', (string) $arg), true)) {
                    $this->errors[$field] = 'Выберите значение из списка.';
                }
                break;
            case 'date':
                $d = \DateTime::createFromFormat('!Y-m-d', $value);
                if ($d === false || $d->format('Y-m-d') !== $value) {
                    $this->errors[$field] = 'Введите дату в формате дд.мм.гггг.';
                }
                break;
            case 'same':
                if ($value !== ($this->data[(string) $arg] ?? null)) {
                    $this->errors[$field] = 'Пароли не совпадают.';
                }
                break;
        }
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}

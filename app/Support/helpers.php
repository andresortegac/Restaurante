<?php

if (! function_exists('money_value')) {
    function money_value(mixed $value): int
    {
        return (int) round((float) ($value ?? 0), 0);
    }
}

if (! function_exists('money')) {
    function money(mixed $value): string
    {
        return number_format(money_value($value), 0, '.', ',');
    }
}

if (! function_exists('money_input')) {
    function money_input(mixed $value): string
    {
        return (string) money_value($value);
    }
}

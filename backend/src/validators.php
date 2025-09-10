<?php
declare(strict_types=1);

final class Validators
{
    public static function s(string $v, int $max = 255): string
    {
        $v = trim($v);
        $v = preg_replace('/\s+/', ' ', $v) ?? '';
        if ($v === '') return '';
        return mb_substr($v, 0, $max);
    }

    public static function email(string $v): string
    {
        $v = strtolower(trim($v));
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return '';
        return $v;
    }

    public static function intId($v): int
    {
        $i = (int)$v;
        return $i > 0 ? $i : 0;
    }
}

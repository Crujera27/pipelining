<?php
function hexImm(int $v): string
{
    $v &= 0xFFFFFFFF;
    if ($v < 10) {
        return (string)$v;
    }
    $h = strtoupper(dechex($v));
    return (ctype_alpha($h[0]) ? '0' : '') . $h . 'h';
}

function immText(int $v): string
{
    $h = hexImm($v);
    return ($v >= 0 && $v < 10) ? $h : $h . ' (' . $v . ')';
}

function varName(int $n): string
{
    return 'var_' . strtoupper(dechex($n));
}

function memText(int $n): string
{
    return '[ebp+' . varName($n) . ']';
}

function addrText(int $a, string $seg = '.text'): string
{
    return sprintf('%s:%08X', $seg, $a);
}

function fits8(int $v): bool
{
    return $v >= -128 && $v <= 127;
}

function dispSize(int $n): int
{
    return $n <= 128 ? 1 : 4;
}

function opReg(string $r): array
{
    return ['t' => 'r', 'r' => $r];
}

function opImm(int $v): array
{
    return ['t' => 'i', 'v' => $v];
}

function opMem(int $n): array
{
    return ['t' => 'm', 'o' => -$n];
}


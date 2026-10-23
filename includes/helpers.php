<?php
// Copyright (c) 2026 Ángel Crujera <me@crujera.net>
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.


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


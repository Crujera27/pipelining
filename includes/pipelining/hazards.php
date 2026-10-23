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


function opReads(?array $o, array &$reads): bool
{
    if ($o === null) {
        return false;
    }
    if ($o['t'] === 'r') {
        $reads[$o['r']] = true;
        return false;
    }
    if ($o['t'] === 'm') {
        $reads['ebp'] = true;
        return true;
    }
    return false;
}

function hazardInfo(array $ex): array
{
    $reads = [];
    $writes = [];
    $kind = 'alu';
    $a = $ex['a'] ?? null;
    $b = $ex['b'] ?? null;
    $c = $ex['c'] ?? null;

    switch ($ex['op']) {
        case 'push':
            opReads($a, $reads);
            $reads['esp'] = true;
            $writes['esp'] = 'alu';
            $kind = 'store';
            break;
        case 'pop':
            $reads['esp'] = true;
            $writes[$a['r']] = 'mem';
            $writes['esp'] = 'alu';
            $kind = 'load';
            break;
        case 'mov':
            $m = opReads($b, $reads);
            if ($a['t'] === 'r') {
                $writes[$a['r']] = $m ? 'mem' : 'alu';
                $kind = $m ? 'load' : 'alu';
            } else {
                $reads['ebp'] = true;
                $kind = 'store';
            }
            break;
        case 'add':
        case 'sub':
            opReads($a, $reads);
            $m = opReads($b, $reads);
            $writes[$a['r']] = $m ? 'mem' : 'alu';
            break;
        case 'imul':
            opReads($b, $reads);
            $m = opReads($c, $reads);
            $writes[$a['r']] = $m ? 'mem' : 'alu';
            break;
        case 'xor':
            $zeroing = $a['t'] === 'r' && $b['t'] === 'r' && $a['r'] === $b['r'];
            if (!$zeroing) {
                opReads($a, $reads);
                opReads($b, $reads);
            }
            $writes[$a['r']] = 'alu';
            break;
        case 'cdq':
            $reads['eax'] = true;
            $writes['edx'] = 'alu';
            break;
        case 'idiv':
            $m = opReads($a, $reads);
            $reads['eax'] = true;
            $reads['edx'] = true;
            $writes['eax'] = $m ? 'mem' : 'alu';
            $writes['edx'] = $m ? 'mem' : 'alu';
            break;
        case 'call':
            $reads['esp'] = true;
            $writes['eax'] = 'mem';
            $kind = 'ctrl';
            break;
        case 'printf_scan':
            $reads['eax'] = true;
            $reads['ebp'] = true;
            $writes['ecx'] = 'mem';
            break;
        case 'printf_format':
            $reads['ecx'] = true;
            $writes['edx'] = 'alu';
            break;
        case 'printf_write':
            $reads['edx'] = true;
            $writes['eax'] = 'alu';
            break;
        case 'ret':
            $reads['esp'] = true;
            $kind = 'ctrl';
            break;
        case 'retn':
            $reads['esp'] = true;
            $kind = 'ctrl';
            break;
    }

    $w = [];
    foreach ($writes as $reg => $cls) {
        $w[] = ['r' => $reg, 'c' => $cls];
    }
    return ['r' => array_keys($reads), 'w' => $w, 'k' => $kind];
}
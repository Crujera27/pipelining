<?php
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
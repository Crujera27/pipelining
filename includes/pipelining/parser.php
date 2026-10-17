<?php
function parseOperand(string $tok, array $defined, int $line): array
{
    if (preg_match('/^-?\d+$/', $tok)) {
        $v = (int)$tok;
        if ($v > 2147483647 || $v < -2147483648) {
            throw new InvalidArgumentException("Línea $line: el número $tok no cabe en 32 bits");
        }
        return ['k' => 'imm', 'v' => $v];
    }
    if (strtolower($tok) === 'print') {
        throw new InvalidArgumentException("Línea $line: 'print' es una palabra reservada");
    }
    if (!isset($defined[$tok])) {
        throw new InvalidArgumentException("Línea $line: la variable '$tok' se usa antes de recibir un valor");
    }
    return ['k' => 'var', 'name' => $tok];
}

function parseExpr(string $s, array $defined, int $line): array
{
    $s = trim($s);
    $tok = '(-?\d+|[A-Za-z_]\w*)';
    if (preg_match('/^' . $tok . '$/', $s, $m)) {
        return ['l' => parseOperand($m[1], $defined, $line), 'op' => null, 'r' => null];
    }
    if (preg_match('/^' . $tok . '\s*([+\-*\/])\s*' . $tok . '$/', $s, $m)) {
        return [
            'l' => parseOperand($m[1], $defined, $line),
            'op' => $m[2],
            'r' => parseOperand($m[3], $defined, $line),
        ];
    }
    throw new InvalidArgumentException("Línea $line: expresión no válida '$s'. Se admite un valor o una operación con + - * /");
}
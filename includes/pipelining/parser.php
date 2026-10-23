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

function parseFormatLiteral(string $literal, int $line): string
{
    $body = substr($literal, 1, -1);
    $out = '';
    for ($i = 0, $len = strlen($body); $i < $len; $i++) {
        if ($body[$i] !== '\\') {
            $out .= $body[$i];
            continue;
        }
        if (++$i >= $len) {
            throw new InvalidArgumentException("Línea $line: escape incompleto en la cadena de formato");
        }
        $escapes = ['n' => "\n", 't' => "\t", '\\' => '\\', '"' => '"'];
        if (!array_key_exists($body[$i], $escapes)) {
            throw new InvalidArgumentException("Línea $line: escape no válido '\\{$body[$i]}'");
        }
        $out .= $escapes[$body[$i]];
    }
    return $out;
}

function parseFormatSpecifiers(string $format, int $line): array
{
    $specs = [];
    for ($i = 0, $len = strlen($format); $i < $len; $i++) {
        if ($format[$i] !== '%') {
            continue;
        }
        if (++$i >= $len) {
            throw new InvalidArgumentException("Línea $line: '%' sin especificador al final del formato");
        }
        if ($format[$i] === '%') {
            continue;
        }
        if (!in_array($format[$i], ['d', 'i', 'u', 'x', 'X', 'c'], true)) {
            throw new InvalidArgumentException("Línea $line: especificador '%' no compatible");
        }
        $specs[] = $format[$i];
    }
    return $specs;
}

function parsePrintf(string $rest, array $defined, int $line): array
{
    if (!preg_match('/^("(?:\\\\.|[^"\\\\])*")(?:\s*,\s*(.*))?$/', trim($rest), $m)) {
        throw new InvalidArgumentException("Línea $line: formato de printf no válido");
    }
    $format = parseFormatLiteral($m[1], $line);
    $specs = parseFormatSpecifiers($format, $line);
    $args = [];
    $argText = trim($m[2] ?? '');
    if ($argText === '' && str_ends_with(trim($rest), ',')) {
        throw new InvalidArgumentException("Línea $line: argumento vacío en printf");
    }
    if ($argText !== '') {
        foreach (explode(',', $argText) as $part) {
            if (trim($part) === '') {
                throw new InvalidArgumentException("Línea $line: argumento vacío en printf");
            }
            $args[] = parseExpr($part, $defined, $line);
        }
    }
    if (count($specs) !== count($args)) {
        throw new InvalidArgumentException("Línea $line: el formato requiere " . count($specs) . ' argumento(s) y se recibieron ' . count($args));
    }
    return ['format' => $format, 'args' => $args];
}
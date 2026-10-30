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


function parseSimpleStatement(string $line, int $n, array &$vars): array
{
    if (preg_match('/^([A-Za-z_]\w*)\s*=\s*(.+)$/', $line, $m)) {
        $name = $m[1];
        if (strtolower($name) === 'print') {
            throw new InvalidArgumentException("Línea $n: 'print' es una palabra reservada");
        }
        $e = parseExpr($m[2], $vars, $n);
        if (!isset($vars[$name])) {
            if (count($vars) >= MAX_VARS) {
                throw new InvalidArgumentException("Línea $n: se admiten como máximo " . MAX_VARS . ' variables');
            }
            $vars[$name] = 4 * (count($vars) + 1);
        }
        return ['k' => 'assign', 'name' => $name, 'e' => $e, 'text' => $line, 'n' => $n];
    }
    if (preg_match('/^print\s+(.+)$/i', $line, $m)) {
        $e = parseExpr($m[1], $vars, $n);
        return ['k' => 'printf', 'format' => "%d\n", 'args' => [$e], 'text' => $line, 'n' => $n];
    }
    if (preg_match('/^printf\s+(.+)$/i', $line, $m)) {
        $p = parsePrintf($m[1], $vars, $n);
        return ['k' => 'printf', ...$p, 'text' => $line, 'n' => $n];
    }
    throw new InvalidArgumentException("Línea $n: instrucción no reconocida '$line'");
}

function parseBlock(array $lines, int &$index, array &$vars, bool $nested = false): array
{
    $stmts = [];
    while ($index < count($lines)) {
        $line = trim($lines[$index]);
        $n = $index + 1;
        $index++;
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if ($line === '}') {
            if (!$nested) {
                throw new InvalidArgumentException("Línea $n: '}' no tiene un bloque abierto");
            }
            return $stmts;
        }
        if (preg_match('/^(if|while)\s+(.+)\s*\{$/i', $line, $m)) {
            $condition = parseCondition($m[2], $vars, $n);
            $body = parseBlock($lines, $index, $vars, true);
            $node = ['k' => strtolower($m[1]), 'condition' => $condition, 'body' => $body, 'text' => $line, 'n' => $n];
            if (strtolower($m[1]) === 'if' && $index < count($lines) && preg_match('/^else\s*\{$/i', trim($lines[$index]))) {
                $index++;
                $node['else'] = parseBlock($lines, $index, $vars, true);
            } elseif ($index < count($lines) && preg_match('/^else\b/i', trim($lines[$index]))) {
                throw new InvalidArgumentException('Línea ' . ($index + 1) . ": 'else' solo puede seguir a un bloque if");
            }
            $stmts[] = $node;
            continue;
        }
        if (preg_match('/^else\b/i', $line)) {
            throw new InvalidArgumentException("Línea $n: 'else' solo puede seguir a un bloque if");
        }
        $stmts[] = parseSimpleStatement($line, $n, $vars);
    }
    if ($nested) {
        throw new InvalidArgumentException('Falta cerrar un bloque con }');
    }
    return $stmts;
}

function conditionVariables(array $condition): array
{
    $vars = [];
    foreach ([$condition['l'], $condition['r']] as $operand) {
        if ($operand['k'] === 'var') {
            $vars[$operand['name']] = true;
        }
    }
    return array_keys($vars);
}

function assignedVariables(array $stmts): array
{
    $assigned = [];
    foreach ($stmts as $stmt) {
        if ($stmt['k'] === 'assign') {
            $assigned[$stmt['name']] = true;
            continue;
        }
        if (!in_array($stmt['k'], ['if', 'while'], true)) {
            continue;
        }
        foreach (assignedVariables($stmt['body']) as $name) {
            $assigned[$name] = true;
        }
        if (isset($stmt['else'])) {
            foreach (assignedVariables($stmt['else']) as $name) {
                $assigned[$name] = true;
            }
        }
    }
    return array_keys($assigned);
}

function validateControlFlow(array $stmts): array
{
    $errors = [];
    foreach ($stmts as $stmt) {
        if (!in_array($stmt['k'], ['if', 'while'], true)) {
            continue;
        }
        if ($stmt['k'] === 'while') {
            $conditionVars = conditionVariables($stmt['condition']);
            $assigned = assignedVariables($stmt['body']);
            if (!array_intersect($conditionVars, $assigned)) {
                $errors[] = "Línea {$stmt['n']}: Ejecución detenida: se alcanzó el límite de instrucciones dinámicas.";
            }
        }
        $errors = [...$errors, ...validateControlFlow($stmt['body'])];
        if (isset($stmt['else'])) {
            $errors = [...$errors, ...validateControlFlow($stmt['else'])];
        }
    }
    return $errors;
}

function translate(string $code): array
{
    if (strlen($code) > 4000) {
           return ['ok' => false, 'errors' => ['El código supera el límite de 4000 caracteres']];
    }

    $errors = [];
    $stmts = [];
    $vars = [];
    $index = 0;

    try {
        $normalizedCode = preg_replace('/}\s*else\s*{/', "}\nelse {", $code);
        $stmts = parseBlock(preg_split('/\R/', $normalizedCode), $index, $vars);
        $errors = [...$errors, ...validateControlFlow($stmts)];
    } catch (InvalidArgumentException $ex) {
        $errors[] = $ex->getMessage();
    }

    if (!$errors && !$stmts) {
        $errors[] = 'No hay instrucciones que compilar';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    $frame = 4 * count($vars);
    $hasPrint = false;
    $formats = [];
    $formatAddresses = [];
    $nextFormatAddress = FMT_ADDR;
    $maxPrintfArgs = 0;
    $prepareStatements = function (array &$items) use (&$prepareStatements, &$hasPrint, &$formats, &$formatAddresses, &$nextFormatAddress, &$maxPrintfArgs): void {
        foreach ($items as &$s) {
            if ($s['k'] === 'printf') {
                $hasPrint = true;
                $maxPrintfArgs = max($maxPrintfArgs, count($s['args']));
                if (!isset($formatAddresses[$s['format']])) {
                    $formatAddresses[$s['format']] = $nextFormatAddress;
                    $formats[(string)$nextFormatAddress] = $s['format'];
                    $nextFormatAddress += 0x100;
                }
                $s['formatAddr'] = $formatAddresses[$s['format']];
            } elseif (in_array($s['k'], ['if', 'while'], true)) {
                $prepareStatements($s['body']);
                if (isset($s['else'])) {
                    $prepareStatements($s['else']);
                }
            }
        }
        unset($s);
    };
    $prepareStatements($stmts);

    $g = new Gen($vars);
    $g->line('comment', '; int __cdecl main()');
    $g->line('label', '_main proc near');
    $g->line('blank');
    foreach (array_reverse($vars, true) as $n) {
        $g->line('var', varName($n) . '= dword ptr -' . hexImm($n));
    }
    if ($vars) {
        $g->line('blank');
    }

    $pro = 'Prólogo de la función';
    $g->ins('push', 'ebp', 1, ['op' => 'push', 'a' => opReg('ebp')],
        'Guarda en la pila el EBP del llamador. ESP baja 4 bytes y [ESP] recibe el valor de EBP.', $pro);
    $g->ins('mov', 'ebp, esp', 2, ['op' => 'mov', 'a' => opReg('ebp'), 'b' => opReg('esp')],
        'EBP pasa a apuntar a la base del marco de pila de esta función. Las variables locales se direccionan desde EBP.', $pro);
    if ($frame > 0) {
        $g->ins('sub', 'esp, ' . hexImm($frame), 3, ['op' => 'sub', 'a' => opReg('esp'), 'b' => opImm($frame)],
            "Reserva $frame bytes (4 por variable) en la pila moviendo ESP hacia direcciones menores.", $pro);
    }

    $g->emitBlockStatements($stmts);

    $epi = 'Epílogo de la función';
    $g->line('comment', '; return 0');
    $g->ins('xor', 'eax, eax', 2, ['op' => 'xor', 'a' => opReg('eax'), 'b' => opReg('eax')],
        'Pone EAX a 0, que es el valor de retorno de main.', $epi);
    $g->ins('mov', 'esp, ebp', 2, ['op' => 'mov', 'a' => opReg('esp'), 'b' => opReg('ebp')],
        'Libera las variables locales: ESP vuelve a la base del marco de pila.', $epi);
    $g->ins('pop', 'ebp', 1, ['op' => 'pop', 'a' => opReg('ebp')],
        'Restaura el EBP del llamador y ESP sube 4 bytes.', $epi);
    $g->ins('retn', '', 1, ['op' => 'retn'],
        'Retorna al llamador: saca la dirección de retorno de la pila y salta a ella.', $epi);
    $g->line('label', '_main endp');
    foreach ($formatAddresses as $format => $address) {
        $label = 'a' . strtoupper(dechex($address));
        $display = addcslashes($format, "\\\"'\n\r\t");
        $g->line('data', $label . " db '" . $display . "',0", '.rdata', $address);
    }

    $varList = [];
    foreach ($vars as $name => $n) {
        $varList[] = ['n' => $n, 'name' => $name];
    }

    return [
        'ok' => true,
        'rows' => $g->rows,
        'meta' => [
            'esp0' => STACK_ESP0,
            'callerEbp' => CALLER_EBP,
            'retAddr' => RET_ADDR,
            'frame' => $frame,
            'hasPrint' => $hasPrint,
            'maxPrintfArgs' => $maxPrintfArgs,
            'maxDynamicInstructions' => constant('MAX_DYNAMIC_INSTRUCTIONS'),
            'formats' => $formats,
            'vars' => $varList,
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'translate') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(translate((string)($_POST['code'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
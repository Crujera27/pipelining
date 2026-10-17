<?php
function translate(string $code): array
{
    if (strlen($code) > 4000) {
        return ['ok' => false, 'errors' => ['El código supera el límite de 4000 caracteres']];
    }

    $errors = [];
    $stmts = [];
    $vars = [];

    foreach (preg_split('/\R/', $code) as $i => $raw) {
        $line = trim($raw);
        $n = $i + 1;
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        try {
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
                $stmts[] = ['k' => 'assign', 'name' => $name, 'e' => $e, 'text' => $line, 'n' => $n];
            } elseif (preg_match('/^print\s+(.+)$/i', $line, $m)) {
                $e = parseExpr($m[1], $vars, $n);
                $stmts[] = ['k' => 'print', 'e' => $e, 'text' => $line, 'n' => $n];
            } else {
                throw new InvalidArgumentException("Línea $n: instrucción no reconocida '$line'");
            }
        } catch (InvalidArgumentException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors && !$stmts) {
        $errors[] = 'No hay instrucciones que compilar';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    $frame = 4 * count($vars);
    $hasPrint = false;
    foreach ($stmts as $s) {
        $s['k'] === 'print' ? $hasPrint = true : null;
    }

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

    foreach ($stmts as $s) {
        if ($s['k'] === 'assign') {
            $g->emitAssign($s);
        } else {
            $g->emitPrint($s);
        }
    }

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
    if ($hasPrint) {
        $g->line('data', "aD db '%d',0Ah,0", '.rdata', FMT_ADDR);
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
            'vars' => $varList,
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'translate') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(translate((string)($_POST['code'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
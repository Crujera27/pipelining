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


final class Gen
{
    public array $rows = [];
    public int $addr = TEXT_BASE;
    private array $byOff;

    public function __construct(private array $vars)
    {
        $this->byOff = array_flip($vars);
    }

    public function line(string $t, string $x = '', string $seg = '.text', ?int $at = null): void
    {
        $this->rows[] = ['t' => $t, 'a' => addrText($at ?? $this->addr, $seg), 'x' => $x];
    }

    public function ins(string $mn, string $ops, int $size, array $ex, string $desc, string $src, string $cmt = ''): void
    {
        $this->rows[] = [
            't' => 'ins',
            'a' => addrText($this->addr),
            'mn' => $mn,
            'ops' => $ops,
            'c' => $cmt,
            'ex' => $ex,
            'hz' => hazardInfo($ex),
            'd' => $desc,
            's' => $src,
        ];
        $this->addr += $size;
    }

    private function nameOf(int $n): string
    {
        return varName($n) . " (variable '" . $this->byOff[$n] . "')";
    }

    private function describe(array $o): string
    {
        if ($o['k'] === 'imm') {
            return 'el valor inmediato ' . immText($o['v']);
        }
        return 'el contenido de ' . $this->nameOf($this->vars[$o['name']]);
    }

    public function loadEax(array $o, string $src): void
    {
        if ($o['k'] === 'imm') {
            $this->ins(
                'mov',
                'eax, ' . hexImm($o['v']),
                5,
                ['op' => 'mov', 'a' => opReg('eax'), 'b' => opImm($o['v'])],
                'Carga el valor inmediato ' . immText($o['v']) . ' en EAX.',
                $src
            );
            return;
        }
        $n = $this->vars[$o['name']];
        $this->ins(
            'mov',
            'eax, ' . memText($n),
            2 + dispSize($n),
            ['op' => 'mov', 'a' => opReg('eax'), 'b' => opMem($n)],
            'Copia ' . $this->describe($o) . ' al registro EAX.',
            $src
        );
    }

    public function compute(array $e, string $src): void
    {
        $this->loadEax($e['l'], $src);
        if ($e['op'] === null) {
            return;
        }
        $r = $e['r'];
        $isImm = $r['k'] === 'imm';
        $n = $isImm ? 0 : $this->vars[$r['name']];
        $text = $isImm ? hexImm($r['v']) : memText($n);
        $operand = $isImm ? opImm($r['v']) : opMem($n);
        $what = $this->describe($r);

        switch ($e['op']) {
            case '+':
            case '-':
                $mn = $e['op'] === '+' ? 'add' : 'sub';
                $size = $isImm ? (fits8($r['v']) ? 3 : 5) : 2 + dispSize($n);
                $desc = ($mn === 'add' ? "Suma $what a EAX" : "Resta $what de EAX") . '. El resultado queda en EAX.';
                $this->ins($mn, 'eax, ' . $text, $size, ['op' => $mn, 'a' => opReg('eax'), 'b' => $operand], $desc, $src);
                break;
            case '*':
                $size = $isImm ? (fits8($r['v']) ? 3 : 6) : 3 + dispSize($n);
                $ops = $isImm ? 'eax, eax, ' . $text : 'eax, ' . $text;
                $this->ins(
                    'imul',
                    $ops,
                    $size,
                    ['op' => 'imul', 'a' => opReg('eax'), 'b' => opReg('eax'), 'c' => $operand],
                    "Multiplica EAX por $what, con signo. El resultado queda en EAX.",
                    $src
                );
                break;
            case '/':
                if ($isImm) {
                    $this->ins(
                        'mov',
                        'ecx, ' . $text,
                        5,
                        ['op' => 'mov', 'a' => opReg('ecx'), 'b' => $operand],
                        'Carga el divisor ' . immText($r['v']) . ' en ECX, porque idiv no admite un valor inmediato.',
                        $src
                    );
                }
                $this->ins(
                    'cdq',
                    '',
                    1,
                    ['op' => 'cdq'],
                    'Extiende el signo de EAX hacia EDX para formar el dividendo EDX:EAX de 64 bits.',
                    $src
                );
                $this->ins(
                    'idiv',
                    $isImm ? 'ecx' : $text,
                    $isImm ? 2 : 2 + dispSize($n),
                    ['op' => 'idiv', 'a' => $isImm ? opReg('ecx') : $operand],
                    'Divide EDX:EAX entre ' . ($isImm ? 'ECX' : $what) . '. El cociente queda en EAX y el resto en EDX.',
                    $src
                );
                break;
        }
    }

    public function emitAssign(array $s): void
    {
        $src = 'Línea ' . $s['n'] . ': ' . $s['text'];
        $this->line('comment', '; ' . $s['text']);
        $n = $this->vars[$s['name']];
        $e = $s['e'];
        if ($e['op'] === null && $e['l']['k'] === 'imm') {
            $v = $e['l']['v'];
            $this->ins(
                'mov',
                memText($n) . ', ' . hexImm($v),
                6 + dispSize($n),
                ['op' => 'mov', 'a' => opMem($n), 'b' => opImm($v)],
                'Escribe el valor inmediato ' . immText($v) . ' en ' . $this->nameOf($n) . '.',
                $src
            );
            return;
        }
        $this->compute($e, $src);
        $this->ins(
            'mov',
            memText($n) . ', eax',
            2 + dispSize($n),
            ['op' => 'mov', 'a' => opMem($n), 'b' => opReg('eax')],
            'Guarda el contenido de EAX en ' . $this->nameOf($n) . '.',
            $src
        );
    }

    public function emitPrintf(array $s): void
    {
        $src = 'Línea ' . $s['n'] . ': ' . $s['text'];
        $this->line('comment', '; ' . $s['text']);
        $args = $s['args'];
        for ($i = count($args) - 1; $i >= 0; $i--) {
            $this->compute($args[$i], $src);
            $position = $i + 1;
            $this->ins(
                'push',
                'eax',
                1,
                ['op' => 'push', 'a' => opReg('eax')],
                "Apila el argumento $position de printf. ESP baja 4 bytes.",
                $src
            );
        }
        $formatLabel = 'a' . strtoupper(dechex($s['formatAddr']));
        $this->ins(
            'push',
            'offset ' . $formatLabel,
            5,
            ['op' => 'push', 'a' => opImm($s['formatAddr'])],
            'Apila la dirección de la cadena de formato. ESP baja 4 bytes.',
            $src,
            json_encode($s['format'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->ins(
            'call',
            '_printf',
            5,
            [
                'op' => 'call',
                'target' => '_printf',
                'format' => $s['formatAddr'],
                'argc' => count($args),
                'runtimeLength' => 9,
            ],
            'Llama a la rutina interna de printf. La dirección de retorno se apila y la rutina continúa con su propio marco.',
            $src
        );
        $this->ins(
            'push',
            'ebp',
            1,
            ['op' => 'push', 'a' => opReg('ebp')],
            'Prólogo de printf: guarda el EBP del llamador.',
            $src
        );
        $this->ins(
            'mov',
            'ebp, esp',
            2,
            ['op' => 'mov', 'a' => opReg('ebp'), 'b' => opReg('esp')],
            'printf establece su propio marco para acceder al formato y a los argumentos.',
            $src
        );
        $this->ins(
            'mov',
            'eax, [ebp+8]',
            3,
            ['op' => 'mov', 'a' => opReg('eax'), 'b' => ['t' => 'm', 'o' => 8]],
            'Lee desde la pila la dirección del formato recibida por printf.',
            $src
        );
        $this->ins(
            'scan',
            'printf',
            2,
            ['op' => 'printf_scan', 'format' => $s['formatAddr'], 'argc' => count($args)],
            'Recorre el formato y lee los argumentos variables desde la pila.',
            $src
        );
        $this->ins(
            'format',
            'printf',
            2,
            ['op' => 'printf_format'],
            'Convierte cada argumento según su especificador y prepara la cadena de salida.',
            $src
        );
        $this->ins(
            'write',
            'stdout',
            2,
            ['op' => 'printf_write'],
            'Escribe la cadena resultante en la consola y devuelve su longitud en EAX.',
            $src
        );
        $this->ins(
            'mov',
            'esp, ebp',
            2,
            ['op' => 'mov', 'a' => opReg('esp'), 'b' => opReg('ebp')],
            'printf libera su marco de pila.',
            $src
        );
        $this->ins(
            'pop',
            'ebp',
            1,
            ['op' => 'pop', 'a' => opReg('ebp')],
            'Restaura el EBP del llamador de printf.',
            $src
        );
        $this->ins(
            'ret',
            '',
            1,
            ['op' => 'ret'],
            'Retorna al código que llamó a printf y recupera la dirección de continuación.',
            $src
        );
        $argBytes = 4 * (count($args) + 1);
        $this->ins(
            'add',
            'esp, ' . $argBytes,
            3,
            ['op' => 'add', 'a' => opReg('esp'), 'b' => opImm($argBytes)],
            'Retira los argumentos de la pila. En la convención cdecl limpia el llamador.',
            $src
        );
    }
}
<?php
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

    public function emitPrint(array $s): void
    {
        $src = 'Línea ' . $s['n'] . ': ' . $s['text'];
        $this->line('comment', '; ' . $s['text']);
        $this->compute($s['e'], $src);
        $this->ins(
            'push',
            'eax',
            1,
            ['op' => 'push', 'a' => opReg('eax')],
            'Apila el valor a mostrar como último argumento de printf. ESP baja 4 bytes.',
            $src
        );
        $this->ins(
            'push',
            'offset aD',
            5,
            ['op' => 'push', 'a' => opImm(FMT_ADDR)],
            'Apila la dirección de la cadena de formato "%d". ESP baja otros 4 bytes.',
            $src,
            '"%d\n"'
        );
        $this->ins(
            'call',
            '_printf',
            5,
            ['op' => 'call'],
            'Llama a printf, que lee sus argumentos de la pila e imprime el valor. El simulador no detalla el interior de la función. EAX recibe el número de caracteres escritos.',
            $src
        );
        $this->ins(
            'add',
            'esp, 8',
            3,
            ['op' => 'add', 'a' => opReg('esp'), 'b' => opImm(8)],
            'Retira los dos argumentos de la pila. En la convención cdecl limpia el llamador.',
            $src
        );
    }
}
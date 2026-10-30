# Traducción a ensamblador

El simulador transforma cada programa escrito en el lenguaje del proyecto en una secuencia de instrucciones x86 simplificadas. Después, esa secuencia se utiliza para construir el calendario del pipeline y mostrar sus etapas IF, ID, EX, MEM y WB.

## Recorrido de traducción

1. `index.php` recibe el código fuente desde el editor.
2. `translate.php` normaliza bloques, ignora comentarios y coordina la validación del programa.
3. `parser.php` convierte operandos, expresiones, condiciones y formatos de `printf` en estructuras internas.
4. `Gen` genera filas de ensamblador con texto visible, descripción, operación ejecutable y datos de riesgos.
5. `hazards.php` determina los registros leídos y escritos por cada instrucción.
6. El navegador recibe las filas y la metadata en JSON.
7. `sim.js` calcula el calendario y ejecuta los efectos cuando las instrucciones llegan a WB.
8. `render.js` muestra ensamblador, registros, pila, consola y diagramas.

## Marco de función

El traductor genera un marco equivalente a una función `main`:

```asm
push ebp
mov ebp, esp
sub esp, <espacio-local>
; cuerpo del programa
xor eax, eax
mov esp, ebp
pop ebp
retn
```

Cada variable ocupa cuatro bytes y se referencia mediante un desplazamiento negativo desde `EBP`.

## Traducción de expresiones

Las expresiones se calculan normalmente en `EAX`:

```text
a = 15
b = a * 2
```

Puede producir una secuencia equivalente a:

```asm
mov eax, 15
mov [ebp+var_4], eax
mov eax, [ebp+var_4]
imul eax, eax, 2
mov [ebp+var_8], eax
```

La representación visible es educativa y está limitada a las instrucciones que el simulador sabe ejecutar.

## Traducción de `printf`

`printf` utiliza la convención de llamada `cdecl`. Los argumentos se apilan de derecha a izquierda y la dirección del formato se apila al final:

```text
printf "a=%d b=%x", a, b
```

Se traduce conceptualmente como:

```asm
; calcular b
push eax
; calcular a
push eax
push offset formato
call _printf
add esp, 12
```

El número usado en `add esp, 12` corresponde a tres valores de cuatro bytes: el formato y los dos argumentos.

El simulador utiliza una rutina interna simplificada para `printf`: crea su marco, lee el formato y los argumentos, recorre los especificadores, prepara la salida, escribe en la consola y retorna al llamador. La limpieza de los argumentos continúa siendo responsabilidad del `add esp, ...` generado por el llamador.

## Traducción de control de flujo

Los bloques se convierten en etiquetas simbólicas y saltos Intel-like. Una comparación carga el operando izquierdo en `EAX` y genera `cmp eax, ...`. El salto condicional se invierte para saltar al bloque falso o a la salida del bucle:

```text
if x < 3 { ... }
```

```asm
cmp eax, 3
jge .Lif_false_1
; cuerpo del if
.Lif_false_1:
```

`while` coloca una etiqueta al principio, compara en cada visita y vuelve a saltar a esa etiqueta después del cuerpo. Las etiquetas visibles permanecen simbólicas; la simulación mantiene además el índice interno de la instrucción destino.

El simulador construye una traza dinámica de las visitas a instrucciones. Por eso una misma fila estática del visor puede aparecer varias veces en el calendario del pipeline. Si se alcanza el límite de instrucciones dinámicas, se descartan las instrucciones posteriores y se muestra un error de simulación.

## Metadata generada

Además de las filas de ensamblador, la traducción devuelve metadata para la interfaz:

- posiciones iniciales de `ESP`, `EBP` y la dirección de retorno;
- desplazamiento y nombre de cada variable;
- direcciones de las cadenas de formato;
- número máximo de argumentos de `printf`;
- información necesaria para interpretar la llamada durante la simulación.

## Riesgos y pipeline

Cada instrucción incluye información sobre registros leídos y escritos. Esa información permite detectar dependencias, insertar esperas y comparar el funcionamiento con y sin adelantamiento de operandos.

Las llamadas `call` provocan el coste de control correspondiente en el pipeline. El efecto de `printf` solo aparece cuando la llamada se retira en WB, por lo que avanzar ciclo a ciclo y avanzar hasta la siguiente retirada producen el mismo estado final.

## Archivos principales

- `includes/pipelining/parser.php`: análisis de operandos, expresiones y formatos.
- `includes/pipelining/translate.php`: validación, metadata y coordinación de la traducción.
- `includes/pipelining/gen.php`: generación de filas de ensamblador.
- `includes/pipelining/hazards.php`: cálculo de riesgos.
- `assets/js/sim.js`: planificación y ejecución de instrucciones.
- `assets/js/render.js`: representación de los resultados.

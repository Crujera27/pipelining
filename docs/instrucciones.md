| Instrucción | Significado |
| --- | --- |
| `add` | Suma el segundo operando al primero y guarda el resultado en el primero. |
| `call` | Llama a una subrutina; guarda la dirección de retorno en la pila y salta al destino. |
| `cdq` | Extiende con signo el valor de EAX hacia EDX para formar el dividendo EDX:EAX. |
| `cmp` | Compara dos operandos restando el segundo del primero y actualiza las banderas. |
| `idiv` | Divide con signo el dividendo entre el operando; deja el cociente en EAX y el resto en EDX. |
| `imul` | Multiplica operandos con signo y guarda el resultado en el registro destino. |
| `je` | Salta al destino si la comparación anterior indica igualdad. |
| `jg` | Salta al destino si el primer valor es mayor que el segundo con signo. |
| `jge` | Salta al destino si el primer valor es mayor o igual que el segundo con signo. |
| `jl` | Salta al destino si el primer valor es menor que el segundo con signo. |
| `jle` | Salta al destino si el primer valor es menor o igual que el segundo con signo. |
| `jmp` | Salta siempre al destino indicado. |
| `jne` | Salta al destino si la comparación anterior indica desigualdad. |
| `mov` | Copia el valor del segundo operando en el primero. |
| `pop` | Extrae el valor de la cima de la pila y lo guarda en el operando destino. |
| `push` | Guarda un valor en la cima de la pila y decrementa ESP. |
| `retn` | Retorna de una subrutina extrayendo de la pila la dirección de retorno y saltando a ella. |
| `sub` | Resta el segundo operando del primero y guarda el resultado en el primero. |
| `xor` | Realiza una disyunción exclusiva bit a bit entre los operandos y guarda el resultado en el primero. |
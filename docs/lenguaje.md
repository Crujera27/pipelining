# Lenguaje del simulador

El simulador utiliza un lenguaje pequeño de alto nivel diseñado para practicar expresiones, memoria, ensamblador x86 y segmentación de cauce.

## Comentarios y líneas vacías

Las líneas vacías se ignoran. Las líneas que comienzan por `#` también se consideran comentarios.

```text
# Este cálculo se guarda en memoria
valor = 15

resultado = valor * 2
```

## Variables y asignaciones

Una asignación tiene la forma:

```text
nombre = expresión
```

Los nombres deben comenzar por una letra o guion bajo y pueden continuar con letras, números o guiones bajos. Las variables se almacenan como valores enteros de 32 bits.

Se admiten:

- números enteros positivos y negativos;
- variables definidas previamente;
- operaciones binarias con `+`, `-`, `*` y `/`.

```text
a = 15
b = -3
c = a + b
producto = a * 2
cociente = producto / 3
```

Las expresiones son intencionadamente simples: cada operación combina dos operandos y no incluye paréntesis.

## Salida con `printf`

La forma general es:

```text
printf "texto de formato", argumento1, argumento2
```

Cada especificador del formato consume un argumento en el mismo orden:

```text
a = 15
b = 5
printf "a=%d, b=%x\\n", a, b
```

Se admiten estos especificadores:

| Especificador | Resultado |
| --- | --- |
| `%d`, `%i` | Entero decimal con signo |
| `%u` | Entero decimal sin signo de 32 bits |
| `%x` | Hexadecimal en minúsculas |
| `%X` | Hexadecimal en mayúsculas |
| `%c` | Carácter correspondiente al byte menos significativo |
| `%%` | Símbolo `%` literal, sin consumir un argumento |

Las secuencias de escape disponibles dentro del formato son `\\n`, `\\t`, `\\\\` y `\\"`.

```text
printf "100%% completado\\n"
printf "letra=%c\\n", 65
printf "hex=%X\\n", 255
```

La instrucción antigua también sigue disponible:

```text
print resultado
```

Es equivalente a:

```text
printf "%d\\n", resultado
```

## Límites actuales

El lenguaje no incluye cadenas como valores de variables, `%s`, paréntesis, funciones definidas por el usuario ni control de flujo. `printf` recibe formatos constantes y argumentos enteros calculados por el programa.

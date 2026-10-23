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


require('includes/constantes.php');
require('includes/helpers.php');
require('includes/pipelining/pipelining.php');

?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pipelining</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="assets/css/overrides.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">

</head>

<body>
    <section class="section pt-4 pb-0">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">
                    <span class="icon mr-1"><i class="fa-solid fa-bars-staggered"></i></span>Etapas del pipeline en el
                    ciclo actual
                </p>
            </header>
            <div class="card-content p-3">
                <div id="strip" class="has-text-grey is-size-7">Traduce el código para ver qué instrucción ocupa cada
                    etapa.</div>
            </div>
        </div>
    </section>

    <section class="section py-4">
        <div class="columns">

            <div class="column is-3">
                <div class="card mb-4">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-code"></i></span>Código fuente
                        </p>
                    </header>
                    <div class="card-content">
                        <div class="field">
                            <div class="control">
                                <textarea id="src" class="textarea mono" rows="9" spellcheck="false">a = 15
b = 5
c = a * b
printf "resultado=%d\n", c</textarea>
                            </div>
                            <p class="help">Asignación de un valor u operación (+ - * /), y printf con formatos %d, %i, %u, %x, %X, %c y %%. También se admite print como alias de %d. Las líneas que empiezan por # se ignoran.</p>
                            <div class="buttons are-small mt-2 mb-0">
                                <a class="button is-light" href="https://github.com/Crujera27/pipelining/blob/main/docs/lenguaje.md" target="_blank" rel="noopener">
                                    <span class="icon"><i class="fa-solid fa-book-open"></i></span>
                                    <span>Guía del lenguaje</span>
                                </a>
                                <a class="button is-light" href="https://github.com/Crujera27/pipelining/blob/main/docs/traduccion.md" target="_blank" rel="noopener">
                                    <span class="icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
                                    <span>Guía de traducción</span>
                                </a>
                            </div>
                        </div>
                        <button id="btnTranslate" class="button is-link is-fullwidth">
                            <span class="icon"><i class="fa-solid fa-gears"></i></span>
                            <span>Compilar</span>
                        </button>
                        <div id="errors" class="notification is-danger is-light mt-3 is-hidden"></div>
                    </div>
                </div>

                <div class="card mb-4">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-sliders"></i></span>Simulación
                        </p>
                    </header>
                    <div class="card-content">
                        <div class="buttons">
                            <button id="btnCycle" class="button is-success" disabled title="Atajo: flecha derecha">
                                <span class="icon"><i class="fa-solid fa-clock"></i></span>
                                <span>Siguiente ciclo</span>
                            </button>
                            <button id="btnInstr" class="button is-info" disabled title="Atajo: Mayús + flecha derecha">
                                <span class="icon"><i class="fa-solid fa-forward-fast"></i></span>
                                <span>Hasta la siguiente retirada</span>
                            </button>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="fwd">
                                Adelantamiento de operandos
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-table-list"></i></span>Mapa de variables
                        </p>
                    </header>
                    <div class="card-content p-3">
                        <table class="table is-narrow is-fullwidth is-size-7 mb-0">
                            <thead>
                                <tr>
                                    <th>Variable</th>
                                    <th>IDA</th>
                                    <th>Posición</th>
                                    <th>Dirección</th>
                                </tr>
                            </thead>
                            <tbody id="varmap">
                                <tr>
                                    <td colspan="4" class="has-text-grey">Sin datos</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="help mt-2">La dirección de cada variable es EBP menos su desplazamiento. EBP no cambia
                            durante la función.</p>
                    </div>
                </div>
            </div>

            <div class="column is-5">
                <div class="card">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-file-code"></i></span>Ensamblador
                        </p>
                    </header>
                    <div id="asm" class="asm"><span class="ph">El desensamblado aparecerá al compilar el código.</span>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="card mb-4">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-microchip"></i></span>Registros de la CPU
                        </p>
                    </header>
                    <div class="card-content p-3">
                        <table class="table is-narrow is-fullwidth is-size-7 mb-0">
                            <thead>
                                <tr>
                                    <th>Registro</th>
                                    <th>Hexadecimal</th>
                                    <th>Decimal</th>
                                </tr>
                            </thead>
                            <tbody id="regs">
                                <tr>
                                    <td colspan="3" class="has-text-grey">Sin datos</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="help mt-2">EIP muestra la dirección que está en la etapa IF.</p>
                    </div>
                </div>

                <div class="card mb-4">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-memory"></i></span>Pila de memoria
                        </p>
                    </header>
                    <div class="card-content p-3">
                        <div class="stack-wrap">
                            <table class="table is-narrow is-fullwidth is-size-7 mb-0 drawers">
                                <thead>
                                    <tr>
                                        <th>Dirección</th>
                                        <th>Puntero</th>
                                        <th>Desde EBP</th>
                                        <th>Contenido</th>
                                        <th>Valor</th>
                                        <th>Decimal</th>
                                    </tr>
                                </thead>
                                <tbody id="stack">
                                    <tr>
                                        <td colspan="6" class="has-text-grey">Sin datos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="legend">
                            <span><i class="lg-ret"></i>Retorno</span>
                            <span><i class="lg-ebp"></i>EBP guardado</span>
                            <span><i class="lg-var"></i>Variables locales</span>
                            <span><i class="lg-arg"></i>Argumentos de printf</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-terminal"></i></span>Consola de salida
                        </p>
                    </header>
                    <div class="card-content p-3">
                        <pre id="console"
                            class="console"><span class="ph">La salida de print aparecerá aquí.</span></pre>
                    </div>
                </div>
            </div>

        </div>

        <div class="card mb-4">
            <header class="card-header">
                <p class="card-header-title">
                    <span class="icon mr-1"><i class="fa-solid fa-table-cells"></i></span>Diagrama de segmentación</p>
            </header>
            <div class="card-content p-3">
                <div class="is-size-7 mb-2">
                    <span class="mr-3"><i class="gl" style="background:#e6d6f5"></i>IF</span>
                    <span class="mr-3"><i class="gl" style="background:#cfe0fb"></i>ID</span>
                    <span class="mr-3"><i class="gl" style="background:#fbdcb0"></i>EX</span>
                    <span class="mr-3"><i class="gl" style="background:#f6e9a0"></i>MEM</span>
                    <span class="mr-3"><i class="gl" style="background:#c6ecd2"></i>WB</span>
                    <span class="mr-3"><i class="gl" style="background:#f6b8b8"></i>instrucción detenida</span>
                    <span><i class="gl" style="background:#e4e4e4"></i>B: burbuja de control, IF sin instrucción
                        útil</span>
                </div>
                <div id="gantt" class="gantt-wrap"><span class="has-text-grey is-size-7">El diagrama aparecerá al
                        compilar el código.</span></div>
            </div>
        </div>

        <div class="columns">
            <div class="column is-8">
                <div class="card mb-4">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-triangle-exclamation"></i></span>Riesgos
                            detectados hasta el ciclo actual
                        </p>
                    </header>
                    <div class="card-content p-3">
                        <div id="haz" class="is-size-7 has-text-grey">Sin datos</div>
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="card">
                    <header class="card-header">
                        <p class="card-header-title">
                            <span class="icon mr-1"><i class="fa-solid fa-gauge-high"></i></span>Rendimiento
                        </p>
                    </header>
                    <div class="card-content p-3">
                        <table class="table is-narrow is-fullwidth is-size-7 mb-0">
                            <tbody id="stats">
                                <tr>
                                    <td class="has-text-grey">Sin datos</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <footer class="footer py-5 mt-6 has-background-light border-top">
        <div class="content has-text-centered is-size-7">
            <p>
                <a href="https://bulma.io" target="_blank">
                    <img src="https://bulma.io/assets/images/made-with-bulma--black.png" alt="Made with Bulma" width="128" height="24">
                </a>
            </p>
            <p class="mb-3">
                <a href="https://github.com/crujera27/pipelining" target="_blank" class="has-text-weight-semibold">
                    <span class="icon"><i class="fa-brands fa-github"></i></span>
                    <span>Código Fuente</span>.
                </a>
            </p>
            <p class="mb-3">
                
            </p>
            <p class="mb-2">
                &copy; <?= date('Y') ?> Ángel Crujera y contribuidores.
            </p>
        </div>
    </footer>
    <script type="module" src="assets/js/main.js"></script>
</body>

</html>
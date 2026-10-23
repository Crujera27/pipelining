/**
 * Copyright (c) 2026 Ángel Crujera <me@crujera.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import { state } from "./state.js";
import { $, esc } from "./utils.js";
import { tick, resetSim } from "./sim.js";
import { render, renderAsm, renderVarMap } from "./render.js";

export function stepCycle() {
  if (!state.prog || !state.st || state.st.done) return;
  state.st.chReg.clear();
  state.st.chMem.clear();
  tick();
  render();
}

export function stepInstr() {
  if (!state.prog || !state.st || state.st.done) return;
  state.st.chReg.clear();
  state.st.chMem.clear();
  do {
    tick();
  } while (!state.st.done && state.st.retired < 0);
  render();
}

export async function translate() {
  const btn = $("btnTranslate");
  const box = $("errors");
  btn.classList.add("is-loading");
  let data;

  try {
    const fd = new FormData();
    fd.append("action", "translate");
    fd.append("code", $("src").value);
    const resp = await fetch(location.pathname, { method: "POST", body: fd });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    data = await resp.json();
  } catch (err) {
    console.error("Error al solicitar la traducción:", err);
    box.textContent = "No se pudo obtener una respuesta válida del servidor.";
    box.classList.remove("is-hidden");
    btn.classList.remove("is-loading");
    return;
  }

  if (!data.ok) {
    box.innerHTML =
      "<ul>" +
      data.errors.map((e) => "<li>" + esc(e) + "</li>").join("") +
      "</ul>";
    box.classList.remove("is-hidden");
    btn.classList.remove("is-loading");
    return;
  }

  try {
    box.classList.add("is-hidden");
    state.prog = data;
    renderAsm();
    renderVarMap();
    resetSim($("fwd").checked);
    render();
  } catch (err) {
    console.error("Error al mostrar la traducción:", err);
    box.textContent = "La traducción llegó, pero no se pudo mostrar en la interfaz.";
    box.classList.remove("is-hidden");
  } finally {
    btn.classList.remove("is-loading");
  }
}
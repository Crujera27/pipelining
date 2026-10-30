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
import { $ } from "./utils.js";
import { resetSim } from "./sim.js";
import { render } from "./render.js";
import { stepCycle, stepInstr, stepToEnd, translate } from "./events.js";

document.addEventListener("DOMContentLoaded", () => {
  $("src").addEventListener("keydown", (e) => {
    if (e.key !== "Tab") return;

    e.preventDefault();
    const source = e.currentTarget;
    const start = source.selectionStart;
    const end = source.selectionEnd;
    source.setRangeText("    ", start, end, "end");
  });
  $("btnTranslate").addEventListener("click", translate);
  $("btnCycle").addEventListener("click", stepCycle);
  $("btnInstr").addEventListener("click", stepInstr);
  $("btnEnd").addEventListener("click", stepToEnd);
  $("fwd").addEventListener("change", () => {
    if (state.prog) {
      resetSim($("fwd").checked);
      render();
    }
  });
  document.addEventListener("keydown", (e) => {
    if (
      e.key === "ArrowRight" &&
      !/^(TEXTAREA|INPUT)$/.test(e.target.tagName)
    ) {
      if (e.shiftKey) stepInstr();
      else stepCycle();
    }
  });
});
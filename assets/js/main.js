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
import { stepCycle, stepInstr, translate } from "./events.js";

document.addEventListener("DOMContentLoaded", () => {
  $("btnTranslate").addEventListener("click", translate);
  $("btnCycle").addEventListener("click", stepCycle);
  $("btnInstr").addEventListener("click", stepInstr);
  $("fwd").addEventListener("change", () => {
    if (state.prog) {
      resetSim($("fwd").checked);
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
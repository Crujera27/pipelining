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
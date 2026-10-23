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
import { STAGES, IDX } from "./config.js";
import { $, esc, hex32, offText, varLabel } from "./utils.js";
import { instrText, total, stageAt, sIdx, dataReason, ctrlReason, pcValue } from "./sim.js";

export function colorOps(t) {
  return esc(t)
    .replace(
      /\b(eax|ebx|ecx|edx|esp|ebp|esi|edi)\b/g,
      '<span class="rg">$1</span>',
    )
    .replace(/\b(var_[0-9A-F]+)\b/g, '<span class="vn">$1</span>')
    .replace(/\b(offset)\b/g, '<span class="kw">$1</span>')
    .replace(/\b(\d[0-9A-F]*h|\d+)\b/g, '<span class="nm">$1</span>');
}

export function renderAsm() {
  const box = $("asm");
  box.innerHTML = "";
  state.prog.ins = [];
  state.prog.insEl = [];
  state.prog.insStg = [];
  state.prog.rows.forEach((r, i) => {
    const d = document.createElement("div");
    d.className = "asm-row";
    let h = '<span class="stg"></span><span class="a">' + esc(r.a) + "</span>";
    if (r.t === "ins") {
      h += '<span class="mn">' + esc(r.mn) + "</span>" + colorOps(r.ops);
      if (r.c) h += '  <span class="cm">; ' + esc(r.c) + "</span>";
      state.prog.ins.push(i);
      state.prog.insEl.push(d);
    } else if (r.t === "comment") {
      h += '<span class="cm">' + esc(r.x) + "</span>";
    } else if (r.t === "label") {
      h += '<span class="lb">' + esc(r.x) + "</span>";
    } else if (r.t === "var") {
      h += colorOps(r.x);
    } else if (r.t === "data") {
      h += '<span class="dt">' + esc(r.x) + "</span>";
    }
    d.innerHTML = h;
    if (r.t === "ins") state.prog.insStg.push(d.firstChild);
    box.appendChild(d);
  });
  box.scrollTop = 0;
}

export function renderVarMap() {
  const m = state.prog.meta;
  const ebp = m.esp0 - 4;
  let h = "";
  m.vars.forEach((v) => {
    h +=
      '<tr><td class="mono"><strong>' +
      esc(v.name) +
      "</strong></td>" +
      '<td class="mono">' +
      varLabel(v.n) +
      "</td>" +
      '<td class="mono">' +
      offText(-v.n) +
      "</td>" +
      '<td class="mono">' +
      hex32(ebp - v.n) +
      "</td></tr>";
  });
  $("varmap").innerHTML =
    h || '<tr><td colspan="4" class="has-text-grey">Sin variables</td></tr>';
}

export function renderRegs() {
  const list = [
    ["EAX", "eax", true],
    ["ECX", "ecx", true],
    ["EDX", "edx", true],
    ["ESP", "esp", false],
    ["EBP", "ebp", false],
  ];
  let h = "";
  list.forEach(([label, key, dec]) => {
    const v = state.st.regs[key];
    h +=
      '<tr class="' +
      (state.st.chReg.has(key) ? "reg-chg" : "") +
      '"><td><strong>' +
      label +
      "</strong></td>" +
      '<td class="mono">' +
      hex32(v) +
      "</td><td>" +
      (dec ? v : "") +
      "</td></tr>";
  });
  h +=
    '<tr><td><strong>EIP</strong></td><td class="mono">' +
    hex32(pcValue()) +
    "</td><td></td></tr>";
  $("regs").innerHTML = h;
}

export function renderStack() {
  const m = state.prog.meta;
  const top = m.esp0;
  const ebp0 = top - 4;
  const mainFrameBottom = ebp0 - m.frame;
  const argCount = m.maxPrintfArgs || 0;
  const formatAddress = mainFrameBottom - 4 * (argCount + 1);
  const printfReturnAddress = formatAddress - 4;
  const printfEbp = printfReturnAddress - 4;
  const bottom = argCount ? printfEbp : mainFrameBottom;
  let h = "";
  for (let a = top; a >= bottom; a -= 4) {
    let label = "";
    let zone = "";
    let showDec = false;
    if (a === top) {
      label = "Dirección de retorno";
      zone = "z-ret";
    } else if (a === ebp0) {
      label = "EBP guardado del llamador";
      zone = "z-ebp";
    } else if (a >= ebp0 - m.frame) {
      const v = m.vars.find((x) => x.n === ebp0 - a);
      label = v ? varLabel(v.n) + " (" + v.name + ")" : "";
      zone = "z-var";
      showDec = true;
    } else if (argCount && a < mainFrameBottom && a >= mainFrameBottom - 4 * argCount) {
      const position = Math.round((mainFrameBottom - a) / 4);
      label = "Argumento " + position + " de printf";
      zone = "z-arg";
      showDec = true;
    } else if (argCount && a === formatAddress) {
      label = "Argumento: formato";
      zone = "z-arg";
    } else if (argCount && a === printfReturnAddress) {
      label = "Retorno de printf";
      zone = "z-ret";
    } else if (argCount && a === printfEbp) {
      label = "EBP guardado por printf";
      zone = "z-ebp";
    } else {
      label = "Área de llamada printf";
      zone = "z-arg";
    }
    const alloc = a >= state.st.regs.esp;
    let value = "";
    let dec = "";
    if (state.st.mem.has(a) && alloc) {
      value = hex32(state.st.mem.get(a));
      if (showDec) dec = String(state.st.mem.get(a));
    } else if (alloc) {
      value = "??";
    }
    let tags = "";
    if (a === state.st.regs.esp) tags += '<span class="tag is-info">ESP</span> ';
    if (a === state.st.regs.ebp) tags += '<span class="tag is-link">EBP</span>';
    h +=
      '<tr class="' +
      zone +
      " " +
      (alloc ? "" : "row-free") +
      " " +
      (state.st.chMem.has(a) ? "mem-chg" : "") +
      '">' +
      '<td class="mono">' +
      hex32(a) +
      "</td><td>" +
      tags +
      "</td>" +
      '<td class="mono">' +
      offText(a - ebp0) +
      "</td><td>" +
      esc(label) +
      "</td>" +
      '<td class="mono">' +
      value +
      "</td><td>" +
      dec +
      "</td></tr>";
  }
  $("stack").innerHTML = h;
}

export function renderAsmState() {
  const { st, sched } = state;
  state.prog.insEl.forEach((el, i) => {
    el.className = "asm-row";
    const tag = state.prog.insStg[i];
    const sq = st.fault && i > st.faultIdx;
    const x = st.cyc > 0 && !sq ? stageAt(sched[i], st.cyc) : null;
    if (x && x.k !== "bub") {
      tag.textContent = x.k;
      tag.className =
        "stg stg-" + x.k + (x.stalled || x.held ? " stg-wait" : "");
    } else {
      tag.textContent = "";
      tag.className = "stg";
    }
  });
  if (st.fault) state.prog.insEl[st.faultIdx].classList.add("fault");
  else if (st.retired >= 0) state.prog.insEl[st.retired].classList.add("cur");

  let target = null;
  if (st.cyc > 0) {
    for (let i = 0; i < sched.length; i++) {
      const v = sIdx(sched[i], st.cyc);
      if (v >= 0 && v <= 4) {
        target = state.prog.insEl[i];
        break;
      }
    }
    if (!target)
      target = state.prog.insEl[st.fault ? st.faultIdx : sched.length - 1];
  }
  if (target) {
    const c = $("asm");
    c.scrollTop = Math.max(0, target.offsetTop - c.clientHeight / 2);
  }
}

export function renderStrip() {
  const { st, sched } = state;
  const c = st.cyc;
  const occ = [null, null, null, null, null];
  const idxs = [];
  sched.forEach((s, i) => {
    if (st.fault && i > st.faultIdx) return;
    idxs.push(sIdx(s, c));
    const x = stageAt(s, c);
    if (x && x.k !== "bub") occ[IDX[x.k]] = { i: i, x: x };
  });
  let h = '<div class="strip">';
  STAGES.forEach(([k, name], s) => {
    let cls = "stage st-" + k;
    let body;
    let tag = "";
    if (occ[s]) {
      body = '<span class="mono">' + esc(instrText(occ[s].i)) + "</span>";
      if (occ[s].x.stalled) {
        cls += " is-wait";
        tag = '<span class="tag is-danger is-light">espera un dato</span>';
      } else if (occ[s].x.held) {
        cls += " is-wait";
        tag = '<span class="tag is-danger is-light">retenida</span>';
      } else if (s === 4) {
        tag = '<span class="tag is-success is-light">se retira</span>';
      }
    } else {
      const older = idxs.some((v) => v > s);
      const younger = idxs.some((v) => v < s);
      if (c > 0 && older && younger) {
        cls += " is-bubble";
        body = "burbuja";
      } else {
        cls += " is-empty";
        body = "vacía";
      }
    }
    if (s > 0)
      h +=
        '<span class="stage-arrow"><i class="fa-solid fa-chevron-right"></i></span>';
    h +=
      '<div class="' +
      cls +
      '"><p class="stage-h"><strong>' +
      k +
      "</strong> " +
      name +
      "</p>" +
      '<p class="stage-b">' +
      body +
      '</p><p class="stage-t">' +
      tag +
      "</p></div>";
  });
  h += "</div>";
  $("strip").className = "";
  $("strip").innerHTML = h;
}

export function renderGantt() {
  const { st, sched } = state;
  const T = total(sched);
  const c = st.cyc;
  let h =
    '<table class="table is-narrow gantt"><thead><tr><th class="g-lab">Instrucción</th>';
  for (let k = 1; k <= T; k++)
    h += '<th class="g-c' + (k === c ? " g-now" : "") + '">' + k + "</th>";
  h += "</tr></thead><tbody>";
  sched.forEach((s, i) => {
    const sq = st.fault && i > st.faultIdx;
    h +=
      '<tr><td class="g-lab mono">' +
      (i + 1) +
      ". " +
      esc(instrText(i)) +
      "</td>";
    for (let k = 1; k <= T; k++) {
      let cell = "";
      let cls = "g-c" + (k === c ? " g-now" : "");
      if (k <= c) {
        const x = stageAt(s, k);
        if (x) {
          if (x.k === "bub") {
            cell = "B";
            cls += " g-bub";
          } else {
            cell = x.k;
            cls +=
              " g-" +
              x.k +
              (x.stalled || x.held ? " g-wait" : "") +
              (sq ? " g-sq" : "");
          }
        }
      }
      h += '<td class="' + cls + '">' + cell + "</td>";
    }
    h += "</tr>";
  });
  h += "</tbody></table>";
  const wrap = $("gantt");
  wrap.innerHTML = h;
  const now = wrap.querySelector("th.g-now");
  if (now)
    wrap.scrollLeft = Math.max(0, now.offsetLeft - wrap.clientWidth * 0.6);
  else wrap.scrollLeft = 0;
}

export function renderHaz() {
  const { st, sched } = state;
  const fwdChecked = $("fwd").checked;
  const range = (a, b) => (a === b ? String(a) : a + " a " + b);
  let rows = "";
  sched.forEach((s, i) => {
    if (st.fault && i > st.faultIdx) return;
    if (s.bub > 0 && st.cyc >= s.nat) {
      rows +=
        "<tr><td>" +
        range(s.nat, s.if0 - 1) +
        '</td><td>Control</td><td class="mono">' +
        esc(instrText(i)) +
        "</td><td>" +
        esc(ctrlReason(i)) +
        "</td><td>" +
        s.bub +
        "</td></tr>";
    }
    if (s.stall > 0 && st.cyc >= s.id0) {
      rows +=
        "<tr><td>" +
        range(s.id0, s.id1 - 1) +
        '</td><td>Datos</td><td class="mono">' +
        esc(instrText(i)) +
        "</td><td>" +
        esc(dataReason(i, fwdChecked)) +
        "</td><td>" +
        s.stall +
        "</td></tr>";
    }
  });
  $("haz").className = "";
  $("haz").innerHTML = rows
    ? '<table class="table is-narrow is-fullwidth is-size-7 mb-0"><thead><tr><th>Ciclos</th><th>Tipo</th><th>Instrucción</th><th>Causa</th><th>Burbujas</th></tr></thead><tbody>' +
      rows +
      "</tbody></table>"
    : '<span class="has-text-grey is-size-7">Sin riesgos detectados hasta ahora.</span>';
}

export function renderStats() {
  const { st, sched, schedNo, schedFw, prog } = state;
  const N = prog.ins.length;
  const T = total(sched);
  const sum = (sch, k) => sch.reduce((a, s) => a + s[k], 0);
  const fwd = $("fwd").checked;
  const rows = [
    ["Instrucciones", N, false],
    ["Ciclo actual", st.cyc + " de " + T, false],
    ["Ciclos por instrucción", (T / N).toFixed(2), false],
    ["Burbujas por datos", sum(sched, "stall"), false],
    ["Burbujas por control", sum(sched, "bub"), false],
    ["Ciclos sin segmentar (5 / instrucción)", 5 * N, false],
    ["Total de ciclos sin adelantamiento", total(schedNo), !fwd],
    ["Total de ciclos con adelantamiento", total(schedFw), fwd],
  ];
  $("stats").innerHTML = rows
    .map(
      (r) =>
        '<tr class="' +
        (r[2] ? "has-background-link-light" : "") +
        '"><td>' +
        esc(r[0]) +
        '</td><td class="has-text-right mono"><strong>' +
        esc(r[1]) +
        "</strong></td></tr>",
    )
    .join("");
}
/*
export function renderInfo() {
  const { st, sched, prog } = state;
  const T = total(sched);
  $("cycTag").textContent = "Ciclo " + st.cyc + " de " + T;
  let src;
  if (st.retired >= 0) src = prog.rows[prog.ins[st.retired]].s;
  else if (st.cyc === 0) src = "Sin ejecución en curso";
  else if (st.done) src = "Fin del programa";
  else src = "Ninguna línea se retira en este ciclo";
  $("srcLine").textContent = src;

  const ev = [];
  sched.forEach((s, i) => {
    if (st.fault && i > st.faultIdx) return;
    const x = stageAt(s, st.cyc);
    if (!x || st.cyc === 0) return;
    if (x.k === "ID" && x.stalled) {
      ev.push(
        '<li><strong>Burbuja de datos.</strong> <span class="mono">' +
          esc(instrText(i)) +
          "</span> espera en ID. " +
          esc(dataReason(i, $("fwd").checked)) +
          "</li>",
      );
    }
    if (x.k === "bub") {
      ev.push(
        "<li><strong>Burbuja de control.</strong> " +
          esc(ctrlReason(i)) +
          "</li>",
      );
    }
  });

  let h = "";
  if (st.cyc === 0) {
    h = "<p>Pulsa Siguiente ciclo para meter la primera instrucción en IF.</p>";
  } else {
    if (st.retired >= 0 && !st.fault) {
      const row = prog.rows[prog.ins[st.retired]];
      h +=
        '<p><span class="mono has-text-weight-bold">' +
        esc(instrText(st.retired)) +
        "</span> llega a WB y se retira.<br>" +
        esc(row.d) +
        "</p>";
    } else if (!st.done) {
      h += "<p>Ninguna instrucción llega a WB en este ciclo.</p>";
    }
    if (st.fault)
      h += '<p class="has-text-danger">' + esc(st.faultMsg) + "</p>";
    else if (st.done)
      h +=
        "<p>main ha devuelto el control al llamador con EAX = 0 en " +
        T +
        " ciclos. Pulsa Reiniciar para repetir la ejecución.</p>";
    if (ev.length)
      h += '<ul class="ml-4" style="list-style: disc">' + ev.join("") + "</ul>";
  }
  $("explain").innerHTML = h;
}
*/

export function renderConsole() {
  let h = state.st.out.map(esc).join("\n");
  if (state.st.faultMsg)
    h +=
      (h ? "\n" : "") + '<span class="err">' + esc(state.st.faultMsg) + "</span>";
  $("console").innerHTML =
    h || '<span class="ph">La salida de print aparecerá aquí.</span>';
}

export function render() {
  renderRegs();
  renderStack();
  renderAsmState();
  renderStrip();
  renderGantt();
  renderHaz();
  renderStats();
  renderConsole();
  $("btnCycle").disabled = state.st.done;
  $("btnInstr").disabled = state.st.done;
}
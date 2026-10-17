import { state } from "./state.js";
import { IDX } from "./config.js";
import { esc, addrOf } from "./utils.js";

export function instrText(i) {
  const r = state.prog.rows[state.prog.ins[i]];
  return (r.mn + " " + r.ops).trim();
}

export function buildSched(fwd) {
  const out = [];
  const lastW = {};
  state.prog.ins.forEach((ri, i) => {
    const hz = state.prog.rows[ri].hz;
    const prev = out[i - 1];
    let nat, if0, id0;
    if (!prev) {
      nat = 1;
      if0 = 1;
      id0 = 2;
    } else {
      nat = prev.id0;
      if0 = nat;
      if (prev.ctrl) {
        const gate = prev.mem + 1;
        if (gate > if0) if0 = gate;
      }
      id0 = Math.max(if0 + 1, prev.id1 + 1);
    }
    let ready = 0;
    let why = null;
    hz.r.forEach((r) => {
      const w = lastW[r];
      if (!w) return;
      const t = fwd ? (w.c === "mem" ? w.mem : w.ex) : w.wb;
      if (t > ready) {
        ready = t;
        why = { r: r, from: w.idx, c: w.c };
      }
    });
    const id1 = Math.max(id0, ready);
    const s = {
      if0: if0,
      id0: id0,
      id1: id1,
      ex: id1 + 1,
      mem: id1 + 2,
      wb: id1 + 3,
      nat: nat,
      bub: if0 - nat,
      stall: id1 - id0,
      why: id1 > id0 ? why : null,
      ctrl: hz.k === "ctrl",
    };
    hz.w.forEach((w) => {
      lastW[w.r] = { idx: i, ex: s.ex, mem: s.mem, wb: s.wb, c: w.c };
    });
    out.push(s);
  });
  return out;
}

export const total = (sch) => sch[sch.length - 1].wb;

export function stageAt(s, c) {
  if (s.bub > 0 && c >= s.nat && c < s.if0) return { k: "bub" };
  if (c >= s.if0 && c < s.id0) return { k: "IF", held: c > s.if0 };
  if (c >= s.id0 && c <= s.id1) return { k: "ID", stalled: c < s.id1 };
  if (c === s.ex) return { k: "EX" };
  if (c === s.mem) return { k: "MEM" };
  if (c === s.wb) return { k: "WB" };
  return null;
}

export function sIdx(s, c) {
  const x = stageAt(s, c);
  if (x && x.k !== "bub") return IDX[x.k];
  return c > s.wb ? 5 : -1;
}

export function dataReason(i, fwdChecked) {
  const w = state.sched[i].why;
  const R = w.r.toUpperCase();
  let t =
    "Lee " +
    R +
    ', que modifica "' +
    instrText(w.from) +
    '" (instrucción ' +
    (w.from + 1) +
    "). ";
  if (fwdChecked) {
    t +=
      w.c === "mem"
        ? "El valor no existe hasta el final de MEM, aunque se adelante a la entrada de EX, así que queda una burbuja (riesgo carga-uso)."
        : "El valor se adelanta desde el final de EX.";
  } else {
    t +=
      "Sin adelantamiento, ID solo puede leer el valor cuando la productora llega a WB (ciclo " +
      state.sched[w.from].wb +
      ").";
  }
  return t;
}

export function ctrlReason(i) {
  const p = i - 1;
  const isCall = state.prog.rows[state.prog.ins[p]].mn === "call";
  return (
    'Tras "' +
    instrText(p) +
    '" el pipeline no conoce la siguiente dirección hasta que esa instrucción llega a MEM. IF queda sin instrucción útil durante ' +
    state.sched[i].bub +
    " ciclos." +
    (isCall
      ? " El cuerpo de printf no se simula, solo se muestra el coste del vaciado."
      : "")
  );
}

export function resetSim(fwdChecked) {
  const m = state.prog.meta;
  state.schedNo = buildSched(false);
  state.schedFw = buildSched(true);
  state.sched = fwdChecked ? state.schedFw : state.schedNo;
  state.st = {
    regs: { eax: 0, ecx: 0, edx: 0, esp: m.esp0, ebp: m.callerEbp },
    mem: new Map(),
    cyc: 0,
    retired: -1,
    done: false,
    fault: false,
    faultIdx: -1,
    faultMsg: "",
    out: [],
    chReg: new Set(),
    chMem: new Set(),
  };
  state.st.mem.set(m.esp0, m.retAddr);
}

function val(o) {
  if (o.t === "r") return state.st.regs[o.r];
  if (o.t === "i") return o.v;
  return readMem(state.st.regs.ebp + o.o);
}
function readMem(a) {
  return state.st.mem.has(a) ? state.st.mem.get(a) : 0;
}
function setR(r, v) {
  state.st.regs[r] = v | 0;
  state.st.chReg.add(r);
}
function wrMem(a, v) {
  state.st.mem.set(a, v | 0);
  state.st.chMem.add(a);
}
function write(o, v) {
  if (o.t === "r") setR(o.r, v);
  else if (o.t === "m") wrMem(state.st.regs.ebp + o.o, v);
}

function run(e) {
  switch (e.op) {
    case "push":
      setR("esp", state.st.regs.esp - 4);
      wrMem(state.st.regs.esp, val(e.a));
      break;
    case "pop": {
      const v = readMem(state.st.regs.esp);
      setR(e.a.r, v);
      setR("esp", state.st.regs.esp + 4);
      break;
    }
    case "mov":
      write(e.a, val(e.b));
      break;
    case "add":
      write(e.a, val(e.a) + val(e.b));
      break;
    case "sub":
      write(e.a, val(e.a) - val(e.b));
      break;
    case "imul":
      write(e.a, Math.imul(val(e.b), val(e.c)));
      break;
    case "xor":
      write(e.a, val(e.a) ^ val(e.b));
      break;
    case "cdq":
      setR("edx", state.st.regs.eax < 0 ? -1 : 0);
      break;
    case "idiv": {
      const d = val(e.a);
      if (d === 0) throw { div: true };
      const n = state.st.regs.eax;
      setR("eax", Math.trunc(n / d));
      setR("edx", n % d);
      break;
    }
    case "call": {
      const s = String(readMem(state.st.regs.esp + 4));
      state.st.out.push(s);
      setR("eax", s.length + 1);
      break;
    }
    case "retn":
      setR("esp", state.st.regs.esp + 4);
      state.st.done = true;
      break;
  }
}

function commit(i) {
  state.st.retired = i;
  try {
    run(state.prog.rows[state.prog.ins[i]].ex);
  } catch (err) {
    if (err && err.div) {
      state.st.done = true;
      state.st.fault = true;
      state.st.faultIdx = i;
      state.st.faultMsg =
        "Excepción: división entre cero (#DE) en idiv. Las instrucciones posteriores que estaban en el pipeline se descartan.";
    } else {
      throw err;
    }
  }
}

export function tick() {
  state.st.cyc++;
  state.st.retired = -1;
  const i = state.sched.findIndex((s) => s.wb === state.st.cyc);
  if (i >= 0) commit(i);
  if (!state.st.done && state.st.cyc >= total(state.sched)) state.st.done = true;
}

export function pcValue() {
  const m = state.prog.meta;
  const { st, sched } = state;
  if (st.fault) return addrOf(state.prog.rows[state.prog.ins[st.faultIdx]]);
  if (st.cyc === 0) return addrOf(state.prog.rows[state.prog.ins[0]]);
  for (let i = 0; i < sched.length; i++) {
    const x = stageAt(sched[i], st.cyc);
    if (x && x.k === "IF") return addrOf(state.prog.rows[state.prog.ins[i]]);
  }
  for (let i = 0; i < sched.length; i++) {
    if (st.cyc < sched[i].if0) return addrOf(state.prog.rows[state.prog.ins[i]]);
  }
  const last = sched.length - 1;
  return st.cyc > sched[last].mem
    ? m.retAddr
    : addrOf(state.prog.rows[state.prog.ins[last]]) + 1;
}
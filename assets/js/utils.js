export const $ = (id) => document.getElementById(id);

export const esc = (s) =>
  String(s).replace(
    /[&<>"']/g,
    (c) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[c],
  );

export const hex32 = (v) =>
  (v >>> 0).toString(16).toUpperCase().padStart(8, "0") + "h";

export const addrOf = (row) => parseInt(row.a.split(":")[1], 16);

export const idaNum = (n) => {
  if (n < 10) return String(n);
  const h = n.toString(16).toUpperCase();
  return (/^[A-F]/.test(h) ? "0" : "") + h + "h";
};

export const offText = (d) =>
  d === 0 ? "EBP" : (d > 0 ? "EBP+" : "EBP-") + idaNum(Math.abs(d));

export const varLabel = (n) => "var_" + n.toString(16).toUpperCase();
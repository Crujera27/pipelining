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
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

export const STAGES = [
  ["IF", "Búsqueda"],
  ["ID", "Decodificación"],
  ["EX", "Ejecución"],
  ["MEM", "Memoria"],
  ["WB", "Escritura"],
];

export const IDX = { IF: 0, ID: 1, EX: 2, MEM: 3, WB: 4 };
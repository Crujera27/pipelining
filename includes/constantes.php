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



declare(strict_types=1);

const TEXT_BASE = 0x00401500;
const FMT_ADDR = 0x00402100;
const STACK_ESP0 = 0x0019FF84;
const CALLER_EBP = 0x0019FF94;
const RET_ADDR = 0x00401A3C;
const MAX_VARS = 24;

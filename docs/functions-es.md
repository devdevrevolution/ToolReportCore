# Funciones de Expresión

ToolReport incluye **más de 30 funciones integradas** para contenido dinámico en plantillas PDF. Usalas dentro de labels con la sintaxis `{{ }}`.

**Sintaxis básica:**

```
{{ NOMBRE_FUNCION(arg1, arg2) }}
```

**Con variables:**

```
{{ MULTIPLY(precio, cantidad) }}
{{ CONCAT("Total: ", FORMAT_CURRENCY(total)) }}
{{ IF(estado, "activo", "Activo", "Inactivo") }}
```

---

## Funciones Math

| Función | Firma | Descripción |
|---------|-------|-------------|
| `SUM` | `SUM(values[])` | Suma todos los valores numéricos. Aplana arrays automáticamente. |
| `MULTIPLY` | `MULTIPLY(a, b)` | Producto de dos valores. |
| `DIVIDE` | `DIVIDE(a, b)` | Cociente de dos valores. Devuelve string vacío si el divisor es cero. |
| `ADD` | `ADD(a, b)` | Suma de dos valores. |
| `SUBTRACT` | `SUBTRACT(a, b)` | Resta de dos valores. |
| `ROUND` | `ROUND(value, decimals)` | Redondea a N decimales (default: 0). |
| `ABS` | `ABS(value)` | Valor absoluto (quita el signo negativo). |
| `MIN` | `MIN(a, b, ...)` | El valor más chico de una lista de números. |
| `MAX` | `MAX(a, b, ...)` | El valor más grande de una lista de números. |
| `POW` | `POW(base, exponent)` | Base elevada a un exponente. |
| `SQRT` | `SQRT(value)` | Raíz cuadrada. Devuelve string vacío si es negativo. |
| `CEIL` | `CEIL(value)` | Redondea hacia arriba al entero más cercano. |
| `FLOOR` | `FLOOR(value)` | Redondea hacia abajo al entero más cercano. |
| `CLAMP` | `CLAMP(value, min, max)` | Limita un valor entre un mínimo y un máximo. |
| `MOD` | `MOD(a, b)` | Resto de una división. Devuelve string vacío si el divisor es cero. |

**Ejemplos:**

```
{{ SUM(precios) }}                           → 60 (de [10, 20, 30])
{{ SUM(items[].total) }}                     → Suma un campo anidado en un array
{{ MULTIPLY(precio, cantidad) }}             → 300
{{ DIVIDE(total, cantidad) }}                → 25.5
{{ ROUND(3.456, 2) }}                        → 3.46
{{ CLAMP(cantidad, 1, 100) }}                → 100 (si cantidad > 100)
{{ MOD(10, 3) }}                             → 1
```

---

## Funciones Text

| Función | Firma | Descripción |
|---------|-------|-------------|
| `UPPER` | `UPPER(text)` | Convierte a mayúsculas. |
| `LOWER` | `LOWER(text)` | Convierte a minúsculas. |
| `TRIM` | `TRIM(text)` | Elimina espacios al inicio y al final. |
| `CONCAT` | `CONCAT(a, b, ...)` | Concatena 2 o más valores en un solo string. |
| `LEFT` | `LEFT(text, count)` | Extrae los primeros N caracteres. |
| `RIGHT` | `RIGHT(text, count)` | Extrae los últimos N caracteres. |
| `MID` | `MID(text, start, length)` | Extrae un substring desde una posición por N caracteres. |
| `SUBSTR` | `SUBSTR(text, start, length)` | Alias de MID. Extrae una porción del texto. |
| `LEN` | `LEN(text)` | Devuelve la cantidad de caracteres. |
| `REPLACE` | `REPLACE(text, search, replace)` | Reemplaza todas las ocurrencias de search por replace. |

**Ejemplos:**

```
{{ UPPER(nombre) }}                          → JUAN PÉREZ
{{ CONCAT(nombre, " ", apellido) }}         → Juan Pérez
{{ LEFT(codigo, 3) }}                        → ABC
{{ RIGHT(codigo, 3) }}                       → CDE
{{ MID(nombre, 2, 5) }}                      → ello (de "hello world")
{{ LEN(texto) }}                             → 5
{{ REPLACE(texto, "_", " ") }}              → hola mundo
```

---

## Funciones Date

| Función | Firma | Descripción |
|---------|-------|-------------|
| `FORMAT_DATE` | `FORMAT_DATE(date, format)` | Formatea una fecha usando un string de formato PHP. |
| `DATE_ADD` | `DATE_ADD(date, amount, unit)` | Suma días, meses o años a una fecha. |
| `DATE_DIFF` | `DATE_DIFF(date1, date2)` | Diferencia en días entre dos fechas. |

**Ejemplos:**

```
{{ FORMAT_DATE(created_at, "d/m/Y") }}       → 15/01/2026
{{ FORMAT_DATE(created_at, "Y-m-d") }}       → 2026-01-15
{{ DATE_ADD(inicio, 30, "days") }}            → 2026-01-31
{{ DATE_DIFF(inicio, fin) }}                  → 30
```

**Tokens de formato:** Usá strings de formato estándar de [PHP date](https://www.php.net/manual/en/datetime.format.php): `d` (día), `m` (mes), `Y` (año de 4 dígitos), `H:i` (hora), etc.

---

## Funciones Logic

| Función | Firma | Descripción |
|---------|-------|-------------|
| `IF` | `IF(value, compare, trueResult, falseResult)` | Devuelve `trueResult` si value coincide con compare, si no `falseResult`. |
| `DEFAULT` | `DEFAULT(value, fallback)` | Devuelve fallback cuando value es null o está vacío. |

**Ejemplos:**

```
{{ IF(estado, "activo", "Activo", "Inactivo") }}   → Activo
{{ IF(total, "0", "Sin cargo", total) }}            → Total o "Sin cargo"
{{ DEFAULT(telefono, "N/A") }}                      → Número de teléfono o "N/A"
```

---

## Funciones Formatting

| Función | Firma | Descripción |
|---------|-------|-------------|
| `FORMAT_NUMBER` | `FORMAT_NUMBER(value, decimals, decSep, thousandsSep)` | Formatea un número con separadores de locale. |
| `FORMAT_CURRENCY` | `FORMAT_CURRENCY(value, symbol, decimals, decSep, thousandsSep)` | Formatea como moneda con símbolo. |

**Parámetros:**

| Param | Default | Descripción |
|-------|---------|-------------|
| `decimals` | `2` | Cantidad de decimales |
| `decSep` | `.` | Separador decimal |
| `thousandsSep` | `,` | Separador de miles |
| `symbol` | `$` | Símbolo de moneda (solo FORMAT_CURRENCY) |

**Ejemplos:**

```
{{ FORMAT_NUMBER(1234567.89, 2) }}                          → 1,234,567.89
{{ FORMAT_NUMBER(1234567.89, 2, ",", ".") }}                → 1.234.567,89
{{ FORMAT_CURRENCY(1234.56) }}                              → $1,234.56
{{ FORMAT_CURRENCY(1234.56, "EUR") }}                       → EUR 1,234.56
{{ FORMAT_CURRENCY(1234.56, "$", 0) }}                      → $1,235
```

---

## Variables de Página

Estas **no son funciones** — son variables inyectadas automáticamente por el motor PDF.

| Variable | Tipo | Descripción |
|----------|------|-------------|
| `PAGE_NUM` | `int` | Número de página actual (empieza en 1). |
| `PAGE_COUNT` | `int` | Cantidad total de páginas del reporte. |

**Ejemplos:**

```
{{ PAGE_NUM }}                                           → 2
{{ PAGE_COUNT }}                                         → 5
{{ CONCAT("Página ", PAGE_NUM, " de ", PAGE_COUNT) }}    → Página 2 de 5
```

---

## Auto-parsing de Números

Las funciones matemáticas parsean automáticamente strings con formato numérico latinoamericano / argentino, sin necesidad de conversión explícita:

| Formato | Parseado como |
|---------|---------------|
| `"45.000.000"` | `45000000` (punto = separador de miles) |
| `"530.000"` | `530000` (punto con 3 dígitos = miles) |
| `"1.234,56"` | `1234.56` (coma = separador decimal) |
| `"100.5"` | `100.5` (punto con 1-2 dígitos = decimal) |
| `"999"` | `999` (número simple) |

Esto significa que podés escribir `{{ SUM(items[].ki) }}` directamente sobre valores como `"45.000.000"` y obtener la suma correcta sin parseo manual.

---

## Manejo de Errores

Las funciones manejan casos extremos sin romper el render del PDF:

- `DIVIDE` o `MOD` con divisor cero → devuelve string vacío
- `SQRT` de número negativo → devuelve string vacío
- `FORMAT_DATE` con fecha inválida → devuelve string vacío
- Funciones que reciben `null` → devuelven string vacío o un valor por defecto razonable
- Strings no numéricos en funciones matemáticas → el valor se usa tal cual (no crashea)

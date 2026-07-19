# ToolReport Core — Guía en Español

> Guía paso a paso para desarrolladores. Si buscás la referencia técnica completa, mirá el [README en inglés](../README.md).

## 1. ¿Qué es ToolReport Core?

ToolReport Core es un **paquete de Laravel** que te permite **diseñar reportes PDF visualmente** y generarlos desde tu aplicación, sin escribir HTML/CSS para cada reporte y sin depender de un servicio externo.

El problema que resuelve: como dev, normalmente terminás escribiendo una vista Blade por cada reporte (factura, remito, certificado, reporte de stock…) y cada cambio requiere tocar código. ToolReport cambia ese modelo:

- Diseñás la plantilla **arrastrando elementos** en un lienzo visual (Vue 3).
- Guardás la plantilla en la base de datos.
- Desde código o desde la API, renderizás el PDF pasándole variables (`{{ empresa }}`, `{{ total }}`).

### ¿Por qué es útil para vos?

- **Visual**: arrastrar y soltar en lugar de pelear con CSS de DomPDF.
- **Portable**: corre en cualquier servidor PHP 8.3+ con Laravel. No necesitás Node, Chromium ni wkhtmltopdf en el servidor — solo PHP y Composer.
- **Compatible con hosting compartido**: las extensiones que usa (`dompdf`, `tc-lib-pdf`) funcionan en shared hosting típico (cPanel, Hostinger, SiteGround) donde no podés instalar binarios.
- **Multi-página**: una `Composición` agrupa varias plantillas en un único PDF (ej. carátula + páginas de detalle).
- **Dos motores**: DomPDF para layouts simples en HTML, TCPDF composite para layouts precisos con posiciones absolutas.

## 2. Arquitectura en 1 minuto

```
┌─────────────────────────────────────────────────┐
│  Tu app Laravel                                 │
│                                                 │
│  ┌─────────────────┐    ┌────────────────────┐  │
│  │  Designer (Vue) │───▶│  API REST          │  │
│  │  /pdf-designer  │    │  /api/pdf-designer │  │
│  └─────────────────┘    └─────────┬──────────┘  │
│                                   │             │
│                          ┌────────▼─────────┐   │
│                          │  ToolReport Core │   │
│                          │  - Models        │   │
│                          │  - Layout Engine │   │
│                          │  - PDF Engines   │   │
│                          └────────┬─────────┘   │
│                                   │             │
│                          ┌────────▼─────────┐   │
│                          │   Archivo PDF    │   │
│                          │  storage/…/x.pdf │   │
│                          └──────────────────┘   │
└─────────────────────────────────────────────────┘
```

Componentes:

- **Designer (Vue 3)**: interfaz visual que vive en `vendor/toolreport/core/designer/src`. Se sirve desde `/pdf-designer`.
- **API REST**: rutas CRUD para plantillas, documentos, composiciones, variables y datasources.
- **Layout Engine**: interpola variables (`{{ total }}`) y resuelve bandas/elementos.
- **Motores PDF**: DomPDF (HTML→PDF) o TCPDF composite (componentes con posición).

## 3. Instalación paso a paso

### Paso 1 — Requerir el paquete

```bash
composer require toolreport/core
```

> Requiere PHP 8.3+ y Laravel 13+.

### Paso 2 — Instalador

```bash
php artisan pdf-designer:install
```

Publica config, migraciones y vistas. Si querés que también publique los assets del designer:

```bash
php artisan pdf-designer:install --with-assets
```

### Paso 3 — Migraciones

```bash
php artisan migrate
```

Esto crea las tablas `pdf_templates`, `pdf_documents`, `report_compositions`, `composition_pages` y `template_vars`.

### Paso 4 — Configurar Vite

Editá tu `vite.config.ts` para que Laravel Vite incluya el entry del designer y resuelva el alias `@`:

```ts
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath } from 'url'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'vendor/toolreport/core/designer/src/main.ts',
            ],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(
                new URL('./vendor/toolreport/core/designer/src', import.meta.url),
            ),
        },
    },
})
```

### Paso 5 — Dependencias JS y Tailwind v4

```bash
npm install -D vue @vitejs/plugin-vue
npm install pinia axios vue-router
```

En `resources/css/app.css` agregá la directiva para que Tailwind escanee el designer:

```css
@source "../../vendor/toolreport/core/designer/src/**/*.{vue,ts,js}";
```

### Paso 6 — Build

```bash
npm run build
```

> Solo necesitás Node para **compilar**, no en producción. En shared hosting subís el `public/build/` ya compilado.

### Paso 7 — Abrí el designer

Navegá a `http://tu-app.test/pdf-designer` y empezá a diseñar.

## 4. Conceptos clave

| Concepto            | Qué es                                                                                   |
|---------------------|------------------------------------------------------------------------------------------|
| **Plantilla**       | Diseño visual de una página (bandas + elementos + variables). Vive en `pdf_templates`.   |
| **Documento**       | PDF ya generado a partir de una plantilla + datos. Se guarda en `pdf_documents`.         |
| **Composición**    | ⚠️ **Beta** — Conjunto ordenado de plantillas que se renderizan como **un único PDF multi-página**. La API y el comportamiento pueden cambiar. |
| **Página de comp.** | Instancia de una plantilla dentro de una composición. Solo guarda `pdf_template_id` + `sort_order` (el orden de aparición). No tiene variables propias — la data se pasa a la composición entera al render. |
| **Placeholder**     | Texto `{{ nombre }}` o `{{ orders[].campo }}` que escribís dentro del `text` de un Label o bajo `key` de una columna de tabla clásica (dompdf). Se interpola al renderizar contra la data del render o contra el item actual de una banda detail iterada. No se persisten como registros propios — viven en el JSON del lienzo. |
| **TemplateVar**     | Variable de entorno (env_var) que **sí** se persiste en la tabla `template_vars`. Tiene `name`, `value`, `visibility` (`public` \| `private`), `is_required`, `description`. Sirve para: (a) interpolar URL/headers/auth token de datasources (`{{ api_key }}` en la URL), (b) capa base para interpolación de placeholders del lienzo. Las `private` se enmascaran (`***`) en respuesta de la API. |
| **Datasource**      | Hoy: endpoint **REST/JSON** que devuelve datos. Se prueba desde `/datasources/test` y se ejecuta al render para alimentar bandas y placeholders. |
| **Motor PDF**       | DomPDF o TCPDF composite. Se elige por plantilla con el campo `engine` (`dompdf` \| `pdf-engine`). |

## 5. Primitivas del designer (composite)

Cuando elegís el motor `pdf-engine` (TCPDF composite), el lienzo se compone con **nodos** en lugar de HTML/CSS. Estos son los disponibles hoy:

### 5.1 Contenedores

| Nodo      | Comportamiento                                                                                     |
|-----------|----------------------------------------------------------------------------------------------------|
| **HBox**  | Dispone a sus hijos en una **fila horizontal**. Stretch: estira alto si el padre es HBox.         |
| **VBox**  | Dispone a sus hijos en una **columna vertical**. Stretch: estira ancho si el padre es VBox.       |

Uso típico:

- `HBox` → barras de botones, encabezados de tabla, datos lado a lado.
- `VBox` → formularios, listados verticales, apilar secciones.

Anidamiento: un `VBox` dentro de un `HBox` (o viceversa) te da layouts tipo "fila con sub-columnas". El cálculo de dimensiones es en **mm**, con DPI por defecto 96 (ver `config/pdf-designer.php → layout`).

### 5.2 Elementos visuales

| Nodo       | Props relevantes                                                                  | Notas |
|------------|-----------------------------------------------------------------------------------|-------|
| **Label**  | `text` (admite `{{ variable }}`), `fontFamily`, `fontSize`, `color`, `style`, `width`, `height`, `wrap` (bool), `margin` | Los placeholders `{{ x }}` se interpolan al renderizar. Si `wrap: false`, texto en una sola línea con overflow oculto. |
| **Image**  | `url` (admite `{{ logo }}`), `altText`, `objectFit` (contain/cover/fill/none), `opacity`, `shapeType` (rect/circle/ellipse), `borderRadius`, `fillColor`, `strokeColor`, `strokeWidth`, `lineStyle`, `width`, `height`, `margin` | Soporta enmascarado con SVG clip-path (círculo, rect con radio). La URL puede ser fija o interpolada. |
| **Shape**  | `shapeType`: `line` \| `rect` \| `circle` \| `ellipse`; `fillColor`, `strokeColor`, `strokeWidth`, `lineStyle` (solid/dashed/dotted), `borderRadius`, `margin` | `line` usa `x1/y1/x2/y2`. `rect`/`circle`/`ellipse` usan `w/h`. |
| **Table**  | `columnWidths: number[]`, `rows: TableRowNode[]`, `margin` | Cada `TableRowNode` tiene `cells: TableCellNode[]`, y cada `TableCellNode` envuelve un `child: CompositeNode` (cualquier nodo: Label, Image, Shape, HBox, VBox…). No hay columnHeader/merges/showHeader en el composite — el header se arma metiendo Labels en la primera fila. |

> Nota: si estás con motor `dompdf` en lugar de `pdf-engine`, los elementos disponibles son los del diseñador clásico: `text`, `image`, `table`, `line`, `rectangle`, `barcode`, `page_number`, `container`. La tabla clásica (`TableContent`) sí soporta `columns[].key` (dot-path contra la data), `merges[]` tipo Excel (colspan/rowspan) y `showHeader`. El composite `TableNode` es más primitivo: es una grilla de cajas anidadas.

### 5.3 Reglas de stretch (importante)

Confirmado contra `HBoxNode.vue`, `VBoxNode.vue`, `LabelNode.vue`, `ImageNode.vue` y `ShapeNode.vue`:

| Hijo           | En HBox (padre)                              | En VBox (padre)                              |
|----------------|----------------------------------------------|----------------------------------------------|
| **HBox**       | estira **alto** (`h-full`)                   | sin stretch (usa w/h propios o flow)         |
| **VBox**       | sin stretch (usa w/h propios o flow)         | estira **ancho** (`w-full`)                  |
| **Label**      | **sin stretch** (dimensiones de texto o w/h propias) | **sin stretch** (idem)               |
| **Image**      | estira **alto** (`h-full`)                   | estira **ancho** (`w-full`)                  |
| **Shape**      | estira **alto** (`h-full`)                   | estira **ancho** (`w-full`)                  |
| **Table**      | (no inspeccionado — asumir comportamiento propio) | (idem)                                  |
| **Círculo** (Image o Shape con `shapeType: circle`) | tamaño único = dimensión cruzada (ancho en HBox, alto en VBox) y se centra | idem |

Notas:

- Label **nunca** estira — sus dimensiones se calculan a partir del texto y de `width`/`height` explícitos que le pongas.
- La señal de "quién es mi padre" viaja por Vue `provide('compositeParentLayout')` del padre a los hijos. Cada componente decide su propio stretch según esa señal.
- Lo que aparece en el lienzo del navegador **no es 1:1** con el PDF final — las métricas de fuente y el flow de TCPDF difieren del navegador. Usá el preview como aproximación.

## 6. Bandas (secciones del reporte)

ToolReport organiza cada plantilla en **bandas** con semántica estilo JasperReports/iReport. Cada banda tiene un `type`, un `anchor`, un `height` (en mm, redimensionable) y un `enabled: bool`. Dentro de cada banda viven los hijos (elementos dompdf o composite roots, según motor).

### 6.1 Tipos de banda

| Tipo            | Anchor    | Renderizado real (confirmado en `LayoutEngine.php`) | Uso típico                              |
|-----------------|-----------|----------------------------------------------|-----------------------------------------|
| `title`         | `top`     | **Una vez** al inicio (en el flow, después del pageHeader fijo) | Portada, encabezado del documento       |
| `pageHeader`    | `top`     | **Repetido fijo** al tope de cada página (via `position: fixed` en dompdf) | Logo + número de página                 |
| `columnHeader`  | `top`     | Fluye una vez, después del pageHeader (NO se repite entre páginas) | Headers de tabla                        |
| `detail`        | `fill`    | **Repetido por cada item de la colección** si tiene `datasourceId` + `collectionPath`; si no, una sola vez | Una fila del listado                    |
| `columnFooter`  | `bottom`  | Fluye al final del body (NO fijo al pie de página) | Subtotales (sin agregaciones todavía)  |
| `pageFooter`    | `bottom`  | **Repetido fijo** al pie de cada página (via `position: fixed` en dompdf) | Firma, pie legal, paginación           |
| `summary`       | `bottom`  | Fluye al final, después de `columnFooter` | Totales generales (sin agregaciones todavía) |

### 6.2 Anchors

Confirmado contra el código:

- `top` → banda "flujo" desde el tope (title, columnHeader). `pageHeader` es especial: está en `FIXED_TOP_TYPES` y se renderiza con `position: fixed`.
- `bottom` → banda de flujo al final (columnFooter, summary). `pageFooter` es especial: está en `FIXED_BOTTOM_TYPES` y se renderiza con `position: fixed`.
- `fill` → banda que ocupa el espacio entre el top-flow y el bottom-flow (detail).

### 6.3 Posición del summary (`summaryPosition`)

> ⚠️ **Proyectado, no implementado.** El type `ReportBand.summaryPosition?: 'afterDetail' \| 'pageBottom'` existe en `designer.ts`, pero `LayoutEngine.php` no lo lee — todas las bandas `summary` se renderizan al final del flow sin distinguir las dos posiciones. El comportamiento "iReport style" todavía no está conectado.

Planeado:
- `afterDetail` → justo después del último ítem del detail.
- `pageBottom` → al pie de la última página (estilo iReport clásico).

### 6.4 Binding de data a bandas

Cada banda puede tener `datasourceId` + `collectionPath` para indicar: "esta banda itera sobre `items` de la datasource X y repite su contenido por cada elemento". **Esta lógica ya está implementada** para la banda `detail` — ver sección 7 para el flujo completo.

## 7. Datasources — estado actual y roadmap

> ⚠️ Hoy solo se soportan datasources **REST/JSON**. El binding a bandas y la iteración ya funcionan; las agregaciones están proyectadas.

### 7.1 Lo que funciona hoy

- **Test de conexión** — Endpoint `POST /api/pdf-designer/templates/{id}/datasources/test`. Acepta una config de datasource (`url`, `method` GET/POST, `headers`, `auth` bearer/none, `timeout`). Devuelve `{ success, fields, error, status }` donde `fields[]` contiene `name`, `path`, `type`, `level`, `datasourceId` descubiertos del JSON de respuesta. Incluye **protección SSRF** (rechaza URLs a redes privadas) y resolución de variables de entorno (`TemplateVar`) en URL/headers/auth.
- **Ejecución al render** — Cuando una plantilla tiene datasources configurados, `PdfRenderingService::resolveData()` los ejecuta vía `DatasourceExecutionService` antes de renderizar, y mergea la respuesta con las variables que pasaste a `renderTemplate()`.
- **Iteración de la banda detail** — Si la banda `detail` tiene `datasourceId` + `collectionPath`, **ambos motores** iteran la banda una vez por cada item de la colección:
  - En `LayoutEngine` (dompdf): `collectionPath = "orders"` itera sobre `$data['orders']`; `collectionPath = ""` itera sobre el array raíz de la respuesta. Sin binding, la banda se renderiza una sola vez.
  - En `ReportCompiler` (pdf-engine composite): clasifica las bandas, extrae los roots del detail, pre-computa alturas, distribuye items en buckets por página y renderiza cada item con su `local_data`. Si no hay colección, lo renderiza una vez como contenido estático.
- **Interpolación por item** — Dentro de la banda iterada, los Labels pueden usar `{{ [].campo }}` (resuelve `campo` desde el item actual) o `{{ orders[].total }}` (resuelve `total` desde el item actual de la colección `orders`). Soporta dot-paths anidados (`{{ [].producto.nombre }}`), indexado específico (`{{ [0].campo }}`, `{{ orders[0].total }}`). **Los filtros pipe (`{{ total | currency("$") }}`) y la concatenación con literales (`{{ 'Total: ' + total }}`) solo funcionan en el motor `dompdf`** (ver sección 8).
- **Paginación estilo iReport** — `pageHeader` se repite fijo al tope de cada página; `pageFooter` se repite fijo al pie de cada página; `columnHeader` se renderiza después del `pageHeader`; `columnFooter` y `summary` se renderizan al final del detalle.

### 7.2 Lo que falta (roadmap)

- **Agregaciones** en bandas de cierre (`columnFooter`, `summary`) sobre la colección iterada — las funciones concretas (sumas, conteos, promedios, etc.) todavía **no están definidas**; se proyectan para una fase posterior del roadmap.
- **Caché de datasets** entre renders repetidos con los mismos parámetros (no hay capa de caché explícita hoy).

### 7.3 Cómo se usa

Flujo real (no proyectado — funciona hoy):

1. En el designer, **definir una datasource** en la plantilla: endpoint REST que devuelve JSON (`url`, `method`, headers, auth bearer si aplica).
2. **Probar** la conexión con el botón "Test" → entra al endpoint `/datasources/test` y te muestra los fields descubiertos.
3. **Asignar** `datasourceId + collectionPath` a la banda `detail` (ej: `ds-1` + `data.items`).
4. **Bindear** cada Label con dot-paths: `{{ [].producto.nombre }}` o `{{ data.items[].producto.nombre }}`. En una tabla, usá `columns[].key = "producto.nombre"`.
5. Al llamar a `PdfDesigner::renderTemplate($template, [], 'Titulo')` podés pasar `$data = []` — el PDF resuelve la data desde la datasource. Alternativamente, pasá variables manuales para overrides o para plantillas sin datasource.

## 8. Sistema de expresiones ( placeholders con filtros)

> Confirmado contra `src/Layout/InterpolatesVariables.php` + `src/Expression/` (FilterRegistry, FilterInterface, filtros concretos). **Funciona solo en el motor `dompdf`** — el trait `InterpolatesVariables` lo usan los renderers de elementos de dompdf (`TextElementRenderer`, `ImageElementRenderer`, `TableElementRenderer`, `RectangleElementRenderer`, `ContainerElementRenderer`, `BarcodeElementRenderer`). El motor `pdf-engine` (composite) **NO** usa `FilterRegistry` ni `InterpolatesVariables` — tiene su propia interpolación en `Primitives\Label::interpolate()` que solo soporta sustitución simple y dot-paths, **sin filtros pipe ni concatenación con literales**.

Los placeholders `{{ … }}` no son solo sustitución simple: soportan **dot-paths**, **notación de colección**, **filtros pipe** y **concatenación**. Esto te ahorra tener que pre-procesar la data en PHP antes de pasarla al render.

### 8.1 Sintaxis básica

| Sintaxis                | Qué hace                                                        | Ejemplo                         |
|-------------------------|-----------------------------------------------------------------|---------------------------------|
| `{{ name }}`            | Resuelve `name` (local-first, luego global)                    | `{{ empresa }}` → "Acme"        |
| `{{ client.name }}`     | Dot-path anidado (local-first, luego global)                   | `{{ client.name }}` → "John"    |
| `{{ [].campo }}`        | Resuelve `campo` desde el item actual de la banda detail       | `{{ [].precio }}` → "12.50"     |
| `{{ orders[].total }}`  | Idem, nombrando la colección                                    | `{{ orders[].total }}`          |
| `{{ [0].campo }}`       | Indexado específico en data global                              | `{{ [0].nombre }}`              |
| `{{ orders[0].total }}` | Indexado específico en colección anidada                        | `{{ orders[0].total }}`         |
| `{{ … \| filter }}`     | Aplica filtro                                                  | `{{ total \| currency("$") }}` → "$1,234.56" |
| `{{ … \| filter \| filter }}` | Encadena filtros                                          | `{{ name \| trim \| upper }}` → "JOHN DOE" |
| `{{ 'Literal ' + var }}`| Concatena string + variable                                    | `{{ 'Total: ' + total }}` → "Total: 1234" |
| `{{ var + '!' }}`       | Variable + string                                              | `{{ name \| upper + '!' }}` → "JOHN!" |

Si un placeholder no se resuelve, queda literal (`{{ name }}` aparece así en el PDF).

### 8.2 Filtros disponibles (confirmados en `FilterRegistry::registerDefaults`)

| Filtro       | Sintaxis                                                            | Ejemplo                                         |
|--------------|---------------------------------------------------------------------|-------------------------------------------------|
| `currency`   | `currency(symbol, decimals?, decimal_sep?, thousands_sep?, position?)` | `{{ price \| currency("€", 2, ",", ".") }}` → "€1.234,56" |
| `number`     | `number(decimals?, decimal_sep?, thousands_sep?)`                  | `{{ total \| number(2, ",", ".") }}` → "1.234,56" |
| `upper`      | `upper`                                                             | `{{ name \| upper }}` → "JOHN DOE"             |
| `lower`      | `lower`                                                             | `{{ name \| lower }}` → "john doe"             |
| `trim`       | `trim`                                                              | `{{ name \| trim }}` → "John"                  |
| `default`    | `default(fallback)`                                                 | `{{ phone \| default("—") }}` → "—" si phone es null |
| `date`       | `date(format)`                                                      | `{{ created_at \| date("d/m/Y") }}` → "19/07/2026" |
| `if`         | `if(compare, true_result, false_result)`                           | `{{ status \| if("active", "Activo", "Inactivo") }}` |
| `substr`     | `substr(start, length?)`                                            | `{{ name \| substr(0, 10) }}`                   |
| `replace`    | `replace(search, replace)`                                          | `{{ name \| replace(" ", "_") }}`               |

### 8.3 Cómo registrar filtros propios

Cualquier filtro que implemente `Toolreport\Core\Expression\Filter\FilterInterface` se puede registrar antes del render:

```php
use Toolreport\Core\Expression\FilterRegistry;
use Toolreport\Core\Expression\Filter\FilterInterface;

class MiFiltro implements FilterInterface {
    public function name(): string { return 'mi_filtro'; }
    public function apply(mixed $value, array $params = []): mixed {
        // tu lógica
    }
}

// En el boot de un ServiceProvider:
$registry = app(FilterRegistry::class);
$registry->registerDefaults(); // opcional, ya se llama lazy
$registry->register(new MiFiltro());
```

> Los placeholders no soportan operaciones aritméticas (`{{ a + b }}` suma strings, no números). Si necesitás cálculo, hacelo en PHP antes de pasar la data al render — o esperá las agregaciones proyectadas (sección 7.2).

## 9. ¿DomPDF o TCPDF composite?

| Criterio               | DomPDF                       | TCPDF composite                          |
|------------------------|------------------------------|------------------------------------------|
| Modelo                 | HTML + CSS                   | Componentes con posición absoluta        |
| Curva                  | Baja (si sabés HTML/CSS)     | Media (pensás en cajas/nodos)            |
| Precisión de layout    | Limitada por CSS             | Mm/puntos exactos                        |
| Fuentes                | System fonts + @font-face    | TTF embebidas                           |
| Ideal para             | Facturas simples, listados   | Certificados, etiquetas, formularios    |

Regla práctica: empezá con **DomPDF**. Si el diseño requiere posición exacta o fuentes específicas embebidas, switch a **`pdf-engine`** desde el campo `engine` de la plantilla.

> La justificación detallada de por qué existen dos motores (pros/contras específicos, incidencias reales de DomPDF que motivaron el composite) está en la sección 10.

## 10. Matriz de soporte — designer × motor PDF

> Inventario confirmado contra el código fuente. ✅ = soportado, ❌ = no soportado, ⚠️ = parcial / planeado.

### 10.1 Elementos del lienzo (designer clásico, motor `dompdf`)

Estos son los 8 `ElementType` de `designer/src/types/designer.ts` L8, con renderers en `src/Layout/Renderers/`:

| Elemento       | Renderer (file)                              | Soporte | Notas                                                          |
|----------------|----------------------------------------------|---------|----------------------------------------------------------------|
| `text`         | `TextElementRenderer.php`                    | ✅      | TextContent con `text` + `variable`, con estilos de `DesignerStyles` (font, color, align, lineHeight, border, padding, borderRadius, backgroundColor). |
| `image`        | `ImageElementRenderer.php`                   | ✅      | `imageUrl` admite `{{ }}`. | 
| `table`        | `TableElementRenderer.php`                   | ✅      | `columns[]` con `key` (dot-path), `header`, `width`, `align`; `rows[]`; `merges[]` (colspan/rowspan tipo Excel); `showHeader`. |
| `line`         | `LineElementRenderer.php`                    | ✅      | `orientation`, `lineWidth`, `lineStyle`.                       |
| `rectangle`    | `RectangleElementRenderer.php`               | ✅      | `colorVariable?` (dot-path a `status.color` desde data).       |
| `barcode`      | `BarcodeElementRenderer.php`                 | ⚠️      | **Placeholder**: renderiza un patrón visual de barras con `str_repeat('\|', …)` — NO genera un código de barras scannable. El comentario original del renderer recomienda instalar `picqer/php-barcode-generator` para producción. Soporta `value` (interpolable), `format`, `showLabel`. |
| `page_number`  | `PageNumberElementRenderer.php`              | ✅      | `format`, `startAt` — généralement usado en pageHeader/pageFooter. |
| `container`    | `ContainerElementRenderer.php`               | ✅      | Layout `vertical` \| `horizontal` con `gap`, `padding`, `children` recursivos. |

### 10.2 Primitivas composite (motor `pdf-engine`)

Nodos de `CompositeNodeType` (`designer/src/types/designer.ts` L179), con primitivas en `src/Modules/PdfEngine/Primitives/` y containers en `Containers/`:

| Nodo      | Class PHP                                            | Soporte | Notas                                                                          |
|-----------|------------------------------------------------------|---------|--------------------------------------------------------------------------------|
| `HBox`    | `Containers/HBox.php`                                | ✅      | Fila horizontal de `CompositeNode[]`. Stretch alto si padre es HBox.         |
| `VBox`    | `Containers/VBox.php`                                | ✅      | Columna vertical de `CompositeNode[]`, con `padding` como gap. Stretch ancho si padre es VBox. |
| `Label`   | `Primitives/Label.php`                               | ✅      | `text`, `fontFamily`, `fontSize`, `style`, `color`, `width`, `height`, `wrap`, `margin`. Métricas reales con `FontMetrics`. |
| `Image`   | `Primitives/Image.php`                               | ✅      | `url` (admite `{{ }}`), `objectFit` (contain/cover/fill/none), `shapeType` (rect/circle/ellipse) **clip real via TCPDF**, `borderRadius`, `fillColor`, `strokeColor`, `strokeWidth`, `lineStyle`, `opacity`, `width`, `height`, `margin`. |
| `Shape`   | `Primitives/Shape.php`                               | ✅      | `shapeType`: `line`/`rect`/`circle`/`ellipse`; `fillColor`, `strokeColor`, `strokeWidth`, `lineStyle` (solid/dashed/dotted), `borderRadius` (rect redondeado). |
| `Table`   | `Containers/Table/Table.php`                         | ✅      | `columnWidths: number[]`, `rows: TableRowNode[]` (cada cell wrap un `CompositeNode`). Header se arma con Labels en la primera fila. No hay merges ni showHeader. |

### 10.3 Lo que el composite **NO** soporta hoy

| Elemento disponible en dompdf | Estado en `pdf-engine`                                                              |
|------------------------------|--------------------------------------------------------------------------------------|
| `barcode`                    | ❌ No hay `Primitives\Barcode` — sin equivalente en el motor composite.              |
| `page_number`                | ❌ No hay primitiva ni mecanismo de paginación nativa en el composite.              |
| `container` (clásico)       | ❌ En composite se usa `HBox`/`VBox` en lugar de `container` con layout v/h.       |
| Table merges (colspan/rowspan) | ❌ El `TableNode` composite no tiene `merges[]`.                                  |
| Table `showHeader`           | ❌ El `TableNode` composite no tiene flag. El header se arma manualmente con Labels. |

### 10.4 Bandas — soporte por motor

Comparativa de los 7 tipos de banda (datos confirmados en `LayoutEngine.php` para dompdf y `ReportCompiler.php` para composite):

| Banda           | dompdf (`LayoutEngine`)                          | composite (`ReportCompiler`)                                |
|-----------------|--------------------------------------------------|-------------------------------------------------------------|
| `title`         | ✅ Fluye una vez (en `flowingBandsTop`)         | ✅ Se renderiza solo en `page_index === 0` (primera página) |
| `pageHeader`    | ✅ Fijo al top via `position: fixed`            | ✅ Repetido al top de cada página (`topRepeating`)          |
| `columnHeader`  | ✅ Fluye una vez (NO se repite entre páginas)   | ✅ En `topRepeating` (se repite en cada página)             |
| `detail`        | ✅ Itera si `datasourceId` + `collectionPath`  | ✅ Itera con `collectionPath` + distribución en buckets por página |
| `columnFooter`  | ✅ Fluye al final del body                       | ✅ Se renderiza después del detalle o summary               |
| `pageFooter`    | ✅ Fijo al bottom via `position: fixed`          | ✅ Fijo al bottom de cada página                             |
| `summary`       | ✅ Fluye al final (sin distinción de posición)  | ✅ En la última página — **distingue `afterDetail` vs `pageBottom`** |

⚠️ **Discrepancia entre motores**:

- `columnHeader` en dompdf fluye **una sola vez**; en composite se repite en cada página (estilo iReport).
- `summaryPosition` (cómo se ubica el summary en la última página) en **composite está implementado**: `afterDetail` lo pega después del último item, `pageBottom` lo fija al pie de la última página sobre el `pageFooter`. En **dompdf NO está implementado** — los summary bands siempre se renderizan al final del flow sin distinguir el caso.

### 10.5 Motores — resumen

| Característica                       | dompdf (`dompdf`) | composite (`pdf-engine`) |
|--------------------------------------|-------------------|--------------------------|
| HTML+CSS como fuente                 | ✅                 | ❌ (usa nodos)            |
| Posicionamiento mm absoluto real     | ⚠️ (vía CSS, frágil) | ✅ (1:1 del diseñador)   |
| Bandas con auto-paginación           | ✅                 | ✅ (con buckets calculados) |
| `summaryPosition`                    | ❌                 | ✅                        |
| Barcode                              | ⚠️ (placeholder) | ❌                        |
| PageNumber                           | ✅                 | ❌                        |
| Image con clipping circular          | ❌ (sin clip-path) | ✅ (TCPDF clipping real) |
| Table merges colspan/rowspan         | ✅                 | ❌                        |
| Filtros pipe + concatenación         | ✅ (via `InterpolatesVariables` + `FilterRegistry`) | ❌ (solo interpolación simple en `Label::interpolate()`) |
| Iteración de banda detail por data   | ✅                 | ✅                        |
| Datasources REST/JSON                | ✅                 | ✅                        |

> La gran diferencia operativa: **dompdf** tiene más elementos (incluido `page_number` y `barcode` en gran medida) y tablas con merges tipo Excel; **composite** tiene mayor precisión de layout, clipping de imagen con forma, `summaryPosition` y columnHeader repetido entre páginas — pero no tiene barcode, page_number ni merges.

## 11. Incidencias técnicas y decisiones del proyecto

Esta sección cuenta **por qué** ToolReport terminó con dos motores PDF en lugar de uno solo. Es un registro de las incidencias reales que surgieron durante el desarrollo.

### 11.1 Por qué existe el motor composite (TCPDF)

Inicialmente el proyecto arrancó con **DomPDF** como único motor. Era la opción obvia: soporta HTML+CSS, es rápido para layouts simples y ya es una dependencia Laravel conocida (`barryvdh/laravel-dompdf`).

En la práctica, al construir un **designer visual** donde el usuario posiciona elementos en un lienzo con coordenadas en mm, DomPDF empezó a mostrar limitaciones serias:

- **CSS limitado**: DomPDF no soporta flexbox ni grid. El strokeLine vertical/horizontal y las cajas inline-flex no se comportan como en el navegador.
- **Posicionamiento absoluto frágil**: `position: absolute` funciona, pero las unidades `mm` no siempre se respetan igual que en el preview del designer, y los sangrados varían según el font-size del contenedor.
- **Page breaks impredecibles**: las bandas largas se cortan en cualquier lugar y las cabeceras de tabla no se repiten entre páginas sin hacks manuales.
- **Fuentes**: para embeber una TTF hay que agregarla al `fontDir` y mapearla en `dompdf_options`. Subsetting a veces rompe caracteres acentuados.
- **Imagen con máscara/circle clip**: DomPDF no soporta `clip-path` ni SVG en línea — un logo circular queda cuadrado.
- **Tablas con `colspan/rowspan`**: se renderizan, pero los anchos de columna a veces colapsan si no definís `width` en todos los `<td>`.

Cada una de esas incidencias demandaba un **workaround en el LayoutEngine** battling con CSS. El resultado era frágil: un Pixel-perfect en el designer se veía corrido en el PDF.

### 11.2 La decisión: motor composite con TCPDF

Para tener control real sobre el output, se implementó un **segundo motor propio** basado en `tecnickcom/tc-lib-pdf` (TCPDF). La inversión fue alta — mucho mayor que DomPDF — porque hubo que construir:

- Un **árbol de nodos composite** (`HBox`, `VBox`, `Label`, `Image`, `Shape`, `Table`) que el designer serializa a JSON.
- Un **renderer por tipo de nodo** que entiende mm, padding, stretch y posicionamiento absoluto.
- **Reglas de layout** (estilo flbox propio) para que `HBox`/`VBox` respeten el stretch del padre.
- **Embebido de fuentes TTF** manual y métricas de texto para wrapping.
- **Clipping con SVG** para imágenes con máscara circular/rectangular.

El resultado: **acercamiento fiable**. El motor composite sigue la geometría del designer con mucha mayor precisión que DomPDF, pero **no es 1:1** — existen diferencias conocidas entre el lienzo (renderizado en navegador con CSS/Vue) y el PDF final (TCPDF). Tipográficamente, el wrapping de texto, el kerning y las métricas de fuente no se comportan idénticamente; y el escalado mm→px del preview usa un DPI fijo que no siempre coincide con el cálculo del motor. Lo que sí se logró es que la **posición, el tamaño y el layout** de cajas y bandas sean predecibles y deterministas (mismo input → mismo output), en lugar de depender del capricho de un renderer HTML.

### 11.3 Pros y contras — DomPDF

**Pros**
- Bajo costo de implementación (HTML ya existe).
- Iteración rápida para reportes simples.
- Curva baja: cualquier dev que sepa Blade+CSS puede personalizar.
- Soporte de Blade y herencia de vistas si lo necesitás.
- Comunidad grande y mucha documentación.

**Contras**
- Sin flexbox/grid — el layout complejo se vuelve frágil.
- Coordenadas mm no son confiables para posicionamiento absoluto.
- Page breaks poco controlables; sin `thead` repeating entre páginas.
- `clip-path` y SVG en línea no soportados → imágenes circulares/imposibles.
- Embebido de fuentes TTF requiere config manual y a veces rompe acentos.
- `colspan`/`rowspan` inconsistente según contenido.
- No sirve para etiquetas, formularios preimpresos ni certificados con posición exacta.

### 11.4 Pros y contras — TCPDF composite

**Pros**
- **Pixel-perfect**: cada mm del designer se respeta en el PDF.
- Posicionamiento absoluto en mm/puntos, sin pelear con CSS.
- Embebido de fuentes TTF con métricas reales → wrapping predecible.
- SVG y clipping nativos → imágenes con máscara (círculo, rect redondeado).
- Page breaks determinísticos (dependen del layout que vos escribís).
- Stretch `HBox`/`VBox` implementado a medida, no sujeto al capricho de un renderer HTML.
- Ideal para certificados, etiquetas, formularios, reportes con bands iterativas.

**Contras**
- **Costo de implementación alto**: hubo que escribir el motor composite completo.
- Sin HTML/CSS — no reutilizás vistas Blade existentes.
- Curva más alta para devs nuevos: hay que pensar en nodos, no en DOM.
- Cada feature visual (border-radius en esquinas, gradiente, sombra) hay que implementarla a mano.
- Menos comunidad que DomPDF para casos específicos.
- Render más lento que DomPDF en layouts simples (hay que medir texto, layoutear cajas).

### 11.5 Recomendación práctica

| Situación                                              | Elegí        |
|--------------------------------------------------------|--------------|
| Factura simple, listado, reporte chico               | `dompdf`     |
| Certificado, etiqueta, formulario preimpreso         | `pdf-engine` |
| Necesitás posición exacta (mm) y PDF ≈ designer       | `pdf-engine` |
| Reutilizás vistas Blade que ya tenés                  | `dompdf`     |
| Imágenes circulares / con máscara                     | `pdf-engine` |
| Tablas complejas con merges tipo Excel                | `pdf-engine` |
| Reporte con bandas que se repiten por data           | `pdf-engine` (en cuanto datasource binding esté listo) |

> La inversión en el motor composite fue el costo de tener **control real sobre el output**. DomPDF queda como opción rápida y simple; composite es el camino para todo lo que requiera precisión.

## 12. Flujo real: una factura end-to-end

> La sección **12.4 (Composiciones multi-página)** está en **Beta**. La API y el formato de salida pueden cambiar entre versiones. Usala con cuidado en producción y cubrí los casos con tests.

### 12.1 Diseñar la plantilla

1. En `/pdf-designer` → “Nueva plantilla”.
2. Elegí `engine: dompdf` y tamaño A4 portrait.
3. Arrastrá Labels para `{{ empresa }}`, `{{ fecha }}`, `{{ total }}`.
4. Guardá con nombre `Factura`.

### 12.2 Renderizar desde código (Facade)

```php
use Toolreport\Core\Facades\PdfDesigner;
use Toolreport\Core\Models\PdfTemplate;

// Buscamos la plantilla por nombre o ID
$plantilla = PdfTemplate::where('name', 'Factura')->firstOrFail();

// Renderizamos pasando las variables que usamos en el lienzo
$documento = PdfDesigner::renderTemplate($plantilla, [
    'empresa' => 'Acme Corp',
    'fecha'   => now()->format('d/m/Y'),
    'total'   => '$1.234,56',
], 'Factura #1001');

// El PDF ya está guardado en disco; devolvemos la ruta al usuario
return response()->download(
    storage_path('app/' . $documento->file_path)
);
```

### 12.3 Renderizar desde la API

```bash
curl -X POST http://tu-app.test/api/pdf-designer/templates/1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "variables": {
      "empresa": "Acme Corp",
      "fecha": "19/07/2026",
      "total": "$1.234,56"
    },
    "filename": "Factura #1001"
  }'
```

### 12.4 Reporte multi-página (Composición) — *Beta*

> **Beta**: la generación de composiciones multi-página es experimental. Los nombres de campos, el orden de páginas y el formato del PDF final pueden cambiar en versiones futuras sin considerarse un breaking change mayor.

Para una carátula + detalle:

1. Creá dos plantillas: `Caratula` y `DetalleFactura`.
2. En el designer → “Nueva composición” → agregá `Caratula` como página 1 y `DetalleFactura` como página 2.
3. Renderizá:

```bash
curl -X POST http://tu-app.test/api/pdf-designer/compositions/1/generate \
  -H "Content-Type: application/json" \
  -d '{ "filename": "Reporte-Anual-2026" }'
```

Obtenés un único PDF con ambas páginas.

## 13. TL;DR

- ToolReport = **diseñador visual de PDF** para Laravel.
- **Portable**: solo PHP + Composer en el server. Ideal shared hosting.
- **Dos motores**: DomPDF (HTML) y TCPDF composite (precisión).
- **Multi-página** vía Composiciones (⚗️ Beta — la API puede cambiar).
- **Salida**: archivo PDF guardado en `storage/app/pdf-documents/…`.
- Designer en `/pdf-designer`, API en `/api/pdf-designer`.

## 14. Configuración típica

`config/pdf-designer.php`:

```php
'api_prefix' => env('PDF_DESIGNER_API_PREFIX', 'api/pdf-designer'),

'storage' => [
    'disk' => env('PDF_DESIGNER_STORAGE_DISK', 'local'),
    'path' => env('PDF_DESIGNER_STORAGE_PATH', 'pdf-documents'),
],

'pdf-engine' => [
    'enabled'      => env('PDF_DESIGNER_PDF_ENGINE_ENABLED', true),
    'default_font' => env('PDF_DESIGNER_PDF_ENGINE_FONT', 'dejavusans'),
],
```

Variables útiles para `.env`:

```env
PDF_DESIGNER_API_PREFIX=api/pdf-designer
PDF_DESIGNER_STORAGE_DISK=local
PDF_DESIGNER_STORAGE_PATH=pdf-documents
PDF_DESIGNER_PDF_ENGINE_ENABLED=true
PDF_DESIGNER_PDF_ENGINE_FONT=dejavusans
```

## 15. Testing

```bash
composer test
```

## 16. Enlaces

- [README en inglés](../README.md) — referencia técnica completa y tabla de endpoints.
- [Issues](https://github.com/toolreport/core/issues)
- [Source](https://github.com/toolreport/core)

## Licencia

MIT — hacé con esto lo que quieras, sin garantía.
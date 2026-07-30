# ToolReport Core — Guía en Español

> Guía paso a paso para desarrolladores. Si buscás la referencia técnica completa, mirá el [README en inglés](../README.md).

## 1. ¿Qué es ToolReport Core?

ToolReport Core es un **paquete de Laravel** que te permite **diseñar reportes PDF visualmente** y generarlos desde tu aplicación, sin escribir HTML/CSS para cada reporte y sin depender de un servicio externo.

El problema que resuelve: como dev, normalmente terminás escribiendo una vista Blade por cada reporte (factura, remito, certificado, reporte de stock…) y cada cambio requiere tocar código. ToolReport cambia ese modelo:

- Diseñás la plantilla **arrastrando elementos** en un lienzo visual (Vue 3).
- Guardás la plantilla en la base de datos.
- Desde código o desde la API, renderizás el PDF pasándole variables (`{{ empresa }}`, `{{ total }}`).

### ¿Por qué es útil para vos?

- **Visual**: arrastrar y soltar en lugar de pelear con código HTML/CSS.
- **Portable**: corre en cualquier servidor PHP 8.3+ con Laravel. No necesitás Node, Chromium ni wkhtmltopdf en el servidor — solo PHP y Composer.
- **Compatible con hosting compartido**: corre en cualquier hosting PHP 8.3+ (cPanel, Hostinger, SiteGround) — no necesitás instalar binarios.
- **Multi-página**: una `Composición` agrupa varias plantillas en un único PDF (ej. carátula + páginas de detalle).
- **Precisión**: renderizado TCPDF composite con posiciones absolutas, clipping de imagen, bordes redondeados, y control real sobre el output.

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
- **Motor PDF**: TCPDF composite — renderizado por componentes con posición precisa.

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
| **Placeholder**     | Texto `{{ nombre }}` o `{{ orders[].campo }}` que escribís dentro del `text` de un Label o bajo `key` de una columna de tabla. Se interpola al renderizar contra la data del render o contra el item actual de una banda detail iterada. No se persisten como registros propios — viven en el JSON del lienzo. |
| **TemplateVar**     | Variable de entorno (env_var) que **sí** se persiste en la tabla `template_vars`. Tiene `name`, `value`, `visibility` (`public` \| `private`), `is_required`, `description`. Sirve para: (a) interpolar URL/headers/auth token de datasources (`{{ api_key }}` en la URL), (b) capa base para interpolación de placeholders del lienzo. Las `private` se enmascaran (`***`) en respuesta de la API. |
| **Datasource**      | Hoy: endpoint **REST/JSON** que devuelve datos. Se prueba desde `/datasources/test` y se ejecuta al render para alimentar bandas y placeholders. |
| **Motor PDF**       | TCPDF composite. Único motor disponible. |

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

> El diseñador composite usa nodos: `Label`, `VBox`, `HBox`, `Shape`, `Table`, `Image`. Cada nodo se posiciona dentro de una banda con coordenadas x/y y dimensiones width/height. La tabla es una grilla de celdas anidadas con `columnWidths` y `rows`.

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

ToolReport organiza cada plantilla en **bandas** con semántica estilo JasperReports/iReport. Cada banda tiene un `type`, un `anchor`, un `height` (en mm, redimensionable) y un `enabled: bool`. Dentro de cada banda viven los nodos composite (`CompositeRoot`) que se posicionan con coordenadas x/y.

### 6.1 Tipos de banda

| Tipo            | Anchor    | Renderizado real (confirmado en `LayoutEngine.php`) | Uso típico                              |
|-----------------|-----------|----------------------------------------------|-----------------------------------------|
| `title`         | `top`     | **Una vez** al inicio (en el flow, después del pageHeader fijo) | Portada, encabezado del documento       |
| `pageHeader`    | `top`     | **Repetido fijo** al tope de cada página | Logo + número de página                 |
| `columnHeader`  | `top`     | Fluye una vez, después del pageHeader (NO se repite entre páginas) | Headers de tabla                        |
| `detail`        | `fill`    | **Repetido por cada item de la colección** si tiene `datasourceId` + `collectionPath`; si no, una sola vez | Una fila del listado                    |
| `columnFooter`  | `bottom`  | Fluye al final del body (NO fijo al pie de página) | Subtotales (sin agregaciones todavía)  |
| `pageFooter`    | `bottom`  | **Repetido fijo** al pie de cada página | Firma, pie legal, paginación           |
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
  - `collectionPath = "orders"` itera sobre `$data['orders']`; `collectionPath = ""` itera sobre el array raíz de la respuesta. Sin binding, la banda se renderiza una sola vez.
  - En `ReportCompiler` (pdf-engine composite): clasifica las bandas, extrae los roots del detail, pre-computa alturas, distribuye items en buckets por página y renderiza cada item con su `local_data`. Si no hay colección, lo renderiza una vez como contenido estático.
- **Interpolación por item** — Dentro de la banda iterada, los Labels pueden usar `{{ [].campo }}` (resuelve `campo` desde el item actual) o `{{ orders[].total }}` (resuelve `total` desde el item actual de la colección `orders`). Soporta dot-paths anidados (`{{ [].producto.nombre }}`), indexado específico (`{{ [0].campo }}`, `{{ orders[0].total }}`).
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

> La interpolación usa sustitución simple y dot-paths. Los filtros pipe y la concatenación con literales no están soportados en el motor TCPDF composite.

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

## 12. Flujo real: una factura end-to-end

> La sección **12.4 (Composiciones multi-página)** está en **Beta**. La API y el formato de salida pueden cambiar entre versiones. Usala con cuidado en producción y cubrí los casos con tests.

### 12.1 Diseñar la plantilla

1. En `/pdf-designer` → “Nueva plantilla”.
2. Elegí tamaño A4 portrait.
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
- **Un motor**: TCPDF composite con precisión de posiciones y nodos tipados.
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

- [Funciones de expresión](functions-es.md) — referencia completa de funciones matemáticas, de texto, fechas y formato.
- [README en inglés](../README.md) — referencia técnica completa y tabla de endpoints.
- [Issues](https://github.com/toolreport/core/issues)
- [Source](https://github.com/toolreport/core)

## Licencia

MIT — hacé con esto lo que quieras, sin garantía.
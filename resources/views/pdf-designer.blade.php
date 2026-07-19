<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF Designer — ToolReport</title>
    <meta name="pdf-designer-api-prefix" content="/{{ ltrim(config('pdf-designer.api_prefix', 'api/pdf-designer'), '/') }}">
    @vite(['resources/css/app.css', 'vendor/toolreport/core/designer/src/main.ts'])
</head>
<body class="antialiased">
    <div id="app" class="h-screen w-screen overflow-hidden"></div>
</body>
</html>

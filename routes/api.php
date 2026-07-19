<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Toolreport\Core\Http\Controllers\CompositionController;
use Toolreport\Core\Http\Controllers\CompositionGeneratorController;
use Toolreport\Core\Http\Controllers\DatasourceProxyController;
use Toolreport\Core\Http\Controllers\PdfDocumentController;
use Toolreport\Core\Http\Controllers\PdfTemplateController;
use Toolreport\Core\Http\Controllers\TemplateVarController;

Route::prefix(config('pdf-designer.api_prefix', 'api/pdf-designer'))
    ->middleware(config('pdf-designer.api_middleware', ['api', 'auth:sanctum']))
    ->group(function () {

        // Health — no extra permissions needed beyond auth
        Route::get('/health', fn() => response()->json(['status' => 'ok', 'version' => '0.1.0']))
            ->name('pdf-designer.health');

        // Templates CRUD
        Route::get('/templates', [PdfTemplateController::class, 'index'])
            ->name('pdf-designer.templates.index');
        Route::post('/templates', [PdfTemplateController::class, 'store'])
            ->name('pdf-designer.templates.store');
        Route::get('/templates/{pdf_template}', [PdfTemplateController::class, 'show'])
            ->name('pdf-designer.templates.show');
        Route::put('/templates/{pdf_template}', [PdfTemplateController::class, 'update'])
            ->name('pdf-designer.templates.update');
        Route::delete('/templates/{pdf_template}', [PdfTemplateController::class, 'destroy'])
            ->name('pdf-designer.templates.destroy');
        Route::post('/templates/{pdf_template}/duplicate', [PdfTemplateController::class, 'duplicate'])
            ->name('pdf-designer.templates.duplicate');

        // Documents
        Route::post('/templates/{pdf_template}/generate', [PdfDocumentController::class, 'generate'])
            ->name('pdf-designer.templates.generate');
        Route::get('/templates/{pdf_template}/documents', [PdfDocumentController::class, 'index'])
            ->name('pdf-designer.templates.documents');
        Route::get('/documents/{pdf_document}', [PdfDocumentController::class, 'show'])
            ->name('pdf-designer.documents.show');
        Route::get('/documents/{pdf_document}/download', [PdfDocumentController::class, 'download'])
            ->name('pdf-designer.documents.download');

        // Datasource testing
        Route::post('/templates/{pdf_template}/datasources/test', [DatasourceProxyController::class, 'test'])
            ->name('pdf-designer.templates.datasources.test');

        // Template Variables (per-template)
        Route::get('/templates/{pdf_template}/template-vars', [TemplateVarController::class, 'index'])
            ->name('pdf-designer.templates.template-vars.index');
        Route::post('/templates/{pdf_template}/template-vars', [TemplateVarController::class, 'store'])
            ->name('pdf-designer.templates.template-vars.store');
        Route::put('/templates/{pdf_template}/template-vars/{template_var}', [TemplateVarController::class, 'update'])
            ->name('pdf-designer.templates.template-vars.update');
        Route::delete('/templates/{pdf_template}/template-vars/{template_var}', [TemplateVarController::class, 'destroy'])
            ->name('pdf-designer.templates.template-vars.destroy');

        // Composition CRUD
        Route::get('/compositions', [CompositionController::class, 'index'])
            ->name('pdf-designer.compositions.index');
        Route::post('/compositions', [CompositionController::class, 'store'])
            ->name('pdf-designer.compositions.store');
        Route::get('/compositions/{composition}', [CompositionController::class, 'show'])
            ->name('pdf-designer.compositions.show');
        Route::put('/compositions/{composition}', [CompositionController::class, 'update'])
            ->name('pdf-designer.compositions.update');
        Route::delete('/compositions/{composition}', [CompositionController::class, 'destroy'])
            ->name('pdf-designer.compositions.destroy');

        // Composition generation
        Route::post('/compositions/{composition}/generate', [CompositionGeneratorController::class, 'generate'])
            ->name('pdf-designer.compositions.generate');
    });

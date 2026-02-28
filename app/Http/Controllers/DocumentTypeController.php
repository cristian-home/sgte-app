<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentTypeStoreRequest;
use App\Http\Requests\DocumentTypeUpdateRequest;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class DocumentTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $documentTypes = QueryBuilder::for(DocumentType::class)
            ->allowedFilters(['code', 'name'])
            ->allowedSorts(['code', 'name'])
            ->get();

        return Inertia::render('document-types/index', [
            'documentTypes' => $documentTypes,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('document-types/create');
    }

    public function store(DocumentTypeStoreRequest $request): RedirectResponse
    {
        $documentType = DocumentType::create($request->validated());

        return redirect()->route('document-types.index');
    }

    public function show(Request $request, DocumentType $documentType): Response
    {
        return Inertia::render('document-types/show', [
            'documentType' => $documentType,
        ]);
    }

    public function edit(Request $request, DocumentType $documentType): Response
    {
        return Inertia::render('document-types/edit', [
            'documentType' => $documentType,
        ]);
    }

    public function update(DocumentTypeUpdateRequest $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->update($request->validated());

        return redirect()->route('document-types.index');
    }

    public function destroy(Request $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->delete();

        return redirect()->route('document-types.index');
    }
}

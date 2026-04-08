<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductManagementController extends Controller
{
    public function menu()
    {
        if ($response = $this->denyIfUnauthorized($this->productPermissions())) {
            return $response;
        }

        $products = Product::with(['menuCategory', 'taxRate'])
            ->where('product_type', 'simple')
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::withCount([
            'products as simple_products_count' => fn ($query) => $query->where('product_type', 'simple'),
        ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('products.menu.index', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function createMenuProduct()
    {
        if ($response = $this->denyIfUnauthorized(['products.create'])) {
            return $response;
        }

        return view('products.menu.form', [
            'pageTitle' => 'Crear producto',
            'product' => new Product(['active' => true]),
            'categoryName' => old('category_name'),
            'taxRates' => $this->taxRates(),
            'categoryOptions' => $this->categoryOptions(),
            'formAction' => route('products.menu.store'),
            'submitLabel' => 'Guardar producto',
        ]);
    }

    public function storeMenuProduct(Request $request): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['products.create'])) {
            return $response;
        }

        $validated = $this->validateProductData($request);

        Product::create($this->buildProductPayload($validated, $request, 'simple'));

        return redirect()
            ->route('products.menu.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function editMenuProduct(Product $product)
    {
        if ($response = $this->denyIfUnauthorized(['products.edit'])) {
            return $response;
        }

        abort_unless($product->product_type === 'simple', 404);

        return view('products.menu.form', [
            'pageTitle' => 'Editar producto',
            'product' => $product,
            'categoryName' => old('category_name', $product->menuCategory->name ?? $product->category),
            'taxRates' => $this->taxRates(),
            'categoryOptions' => $this->categoryOptions(),
            'formAction' => route('products.menu.update', $product),
            'submitLabel' => 'Actualizar producto',
        ]);
    }

    public function updateMenuProduct(Request $request, Product $product): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['products.edit'])) {
            return $response;
        }

        abort_unless($product->product_type === 'simple', 404);

        $validated = $this->validateProductData($request, $product);

        $product->update($this->buildProductPayload($validated, $request, 'simple'));

        return redirect()
            ->route('products.menu.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroyMenuProduct(Product $product): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['products.delete'])) {
            return $response;
        }

        abort_unless($product->product_type === 'simple', 404);

        return $this->deleteProductSafely($product, 'products.menu.index');
    }

    public function combos()
    {
        if ($response = $this->denyIfUnauthorized($this->comboPermissions())) {
            return $response;
        }

        $combos = Product::with(['menuCategory', 'taxRate', 'components.componentProduct'])
            ->withCount('components')
            ->where('product_type', 'combo')
            ->orderBy('name')
            ->get();

        return view('products.combos.index', [
            'combos' => $combos,
            'simpleProductsCount' => Product::where('product_type', 'simple')->count(),
        ]);
    }

    public function createCombo()
    {
        if ($response = $this->denyIfUnauthorized(['combos.create'])) {
            return $response;
        }

        return view('products.combos.form', [
            'pageTitle' => 'Crear combo',
            'combo' => new Product(['active' => true, 'product_type' => 'combo']),
            'categoryName' => old('category_name', 'Combos'),
            'taxRates' => $this->taxRates(),
            'categoryOptions' => $this->categoryOptions(),
            'availableProducts' => $this->availableComboProducts(),
            'componentRows' => $this->comboComponentRows(),
            'formAction' => route('products.combos.store'),
            'submitLabel' => 'Guardar combo',
        ]);
    }

    public function storeCombo(Request $request): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['combos.create'])) {
            return $response;
        }

        $validated = $this->validateComboData($request);

        DB::transaction(function () use ($request, $validated): void {
            $combo = Product::create($this->buildProductPayload($validated, $request, 'combo'));
            $this->syncComboComponents($combo, $validated['components']);
        });

        return redirect()
            ->route('products.combos.index')
            ->with('success', 'Combo creado correctamente.');
    }

    public function editCombo(Product $product)
    {
        if ($response = $this->denyIfUnauthorized(['combos.edit'])) {
            return $response;
        }

        abort_unless($product->product_type === 'combo', 404);

        $product->load('components');

        return view('products.combos.form', [
            'pageTitle' => 'Editar combo',
            'combo' => $product,
            'categoryName' => old('category_name', $product->menuCategory->name ?? $product->category),
            'taxRates' => $this->taxRates(),
            'categoryOptions' => $this->categoryOptions(),
            'availableProducts' => $this->availableComboProducts(),
            'componentRows' => $this->comboComponentRows($product),
            'formAction' => route('products.combos.update', $product),
            'submitLabel' => 'Actualizar combo',
        ]);
    }

    public function updateCombo(Request $request, Product $product): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['combos.edit'])) {
            return $response;
        }

        abort_unless($product->product_type === 'combo', 404);

        $validated = $this->validateComboData($request, $product);

        DB::transaction(function () use ($request, $validated, $product): void {
            $product->update($this->buildProductPayload($validated, $request, 'combo'));
            $this->syncComboComponents($product, $validated['components']);
        });

        return redirect()
            ->route('products.combos.index')
            ->with('success', 'Combo actualizado correctamente.');
    }

    public function destroyCombo(Product $product): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['combos.delete'])) {
            return $response;
        }

        abort_unless($product->product_type === 'combo', 404);

        return $this->deleteProductSafely($product, 'products.combos.index');
    }

    public function taxes()
    {
        if ($response = $this->denyIfUnauthorized($this->taxPermissions())) {
            return $response;
        }

        $taxRates = TaxRate::withCount('products')
            ->orderByDesc('is_default')
            ->orderBy('rate')
            ->orderBy('name')
            ->get();

        return view('products.taxes.index', [
            'taxRates' => $taxRates,
            'productsWithoutTax' => Product::whereNull('tax_rate_id')->count(),
            'taxableProducts' => Product::whereNotNull('tax_rate_id')->count(),
        ]);
    }

    public function createTax()
    {
        if ($response = $this->denyIfUnauthorized(['taxes.create'])) {
            return $response;
        }

        return view('products.taxes.form', [
            'pageTitle' => 'Crear impuesto',
            'taxRate' => new TaxRate(['is_active' => true]),
            'formAction' => route('products.taxes.store'),
            'submitLabel' => 'Guardar impuesto',
        ]);
    }

    public function storeTax(Request $request): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['taxes.create'])) {
            return $response;
        }

        $validated = $this->validateTaxData($request);

        DB::transaction(function () use ($request, $validated): void {
            if ($request->boolean('is_default')) {
                TaxRate::query()->update(['is_default' => false]);
            }

            TaxRate::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'rate' => $validated['rate'],
                'is_inclusive' => $request->boolean('is_inclusive'),
                'is_default' => $request->boolean('is_default'),
                'is_active' => $request->boolean('is_active') || $request->boolean('is_default'),
            ]);
        });

        return redirect()
            ->route('products.taxes.index')
            ->with('success', 'Impuesto creado correctamente.');
    }

    public function editTax(TaxRate $taxRate)
    {
        if ($response = $this->denyIfUnauthorized(['taxes.edit'])) {
            return $response;
        }

        return view('products.taxes.form', [
            'pageTitle' => 'Editar impuesto',
            'taxRate' => $taxRate,
            'formAction' => route('products.taxes.update', $taxRate),
            'submitLabel' => 'Actualizar impuesto',
        ]);
    }

    public function updateTax(Request $request, TaxRate $taxRate): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['taxes.edit'])) {
            return $response;
        }

        $validated = $this->validateTaxData($request, $taxRate);

        DB::transaction(function () use ($request, $validated, $taxRate): void {
            if ($request->boolean('is_default')) {
                TaxRate::where('id', '!=', $taxRate->id)->update(['is_default' => false]);
            }

            $taxRate->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'rate' => $validated['rate'],
                'is_inclusive' => $request->boolean('is_inclusive'),
                'is_default' => $request->boolean('is_default'),
                'is_active' => $request->boolean('is_active') || $request->boolean('is_default'),
            ]);
        });

        return redirect()
            ->route('products.taxes.index')
            ->with('success', 'Impuesto actualizado correctamente.');
    }

    public function destroyTax(TaxRate $taxRate): Response|RedirectResponse
    {
        if ($response = $this->denyIfUnauthorized(['taxes.delete'])) {
            return $response;
        }

        $linkedProducts = Product::where('tax_rate_id', $taxRate->id)->count();
        $wasDefault = $taxRate->is_default;

        DB::transaction(function () use ($taxRate): void {
            Product::where('tax_rate_id', $taxRate->id)->update(['tax_rate_id' => null]);
            $taxRate->delete();
        });

        if ($wasDefault) {
            $replacement = TaxRate::where('is_active', true)->orderBy('name')->first();

            if ($replacement) {
                $replacement->update(['is_default' => true]);
            }
        }

        $message = $linkedProducts > 0
            ? 'Impuesto eliminado. Los productos asociados quedaron sin impuesto asignado.'
            : 'Impuesto eliminado correctamente.';

        return redirect()
            ->route('products.taxes.index')
            ->with($linkedProducts > 0 ? 'warning' : 'success', $message);
    }

    private function validateProductData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateComboData(Request $request, ?Product $combo = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($combo?->id)],
            'category_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'active' => ['nullable', 'boolean'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.component_product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('product_type', 'simple')),
            ],
            'components.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'components.*.unit_label' => ['nullable', 'string', 'max:50'],
            'components.*.extra_price' => ['nullable', 'numeric', 'min:0'],
            'components.*.is_optional' => ['nullable', 'boolean'],
        ]);

        $components = collect($request->input('components', []))
            ->map(function (array $component): array {
                return [
                    'component_product_id' => isset($component['component_product_id']) ? (int) $component['component_product_id'] : null,
                    'quantity' => isset($component['quantity']) && $component['quantity'] !== '' ? (float) $component['quantity'] : null,
                    'unit_label' => $component['unit_label'] ?? null,
                    'extra_price' => isset($component['extra_price']) && $component['extra_price'] !== '' ? (float) $component['extra_price'] : 0,
                    'is_optional' => !empty($component['is_optional']),
                ];
            })
            ->filter(fn (array $component) => !empty($component['component_product_id']))
            ->values();

        if ($components->isEmpty()) {
            throw ValidationException::withMessages([
                'components' => 'Debes agregar al menos un producto al combo.',
            ]);
        }

        if ($combo && $components->contains(fn (array $component) => $component['component_product_id'] === $combo->id)) {
            throw ValidationException::withMessages([
                'components' => 'Un combo no puede incluirse a si mismo.',
            ]);
        }

        if ($components->pluck('component_product_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'components' => 'No repitas el mismo producto dentro del combo.',
            ]);
        }

        $validated['components'] = $components->all();

        return $validated;
    }

    private function validateTaxData(Request $request, ?TaxRate $taxRate = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('tax_rates', 'code')->ignore($taxRate?->id)],
            'description' => ['nullable', 'string'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_inclusive' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function buildProductPayload(array $validated, Request $request, string $type): array
    {
        $category = $this->resolveCategory($validated['category_name']);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'category' => $category->name,
            'category_id' => $category->id,
            'tax_rate_id' => $validated['tax_rate_id'] ?? null,
            'product_type' => $type,
            'sku' => $validated['sku'],
            'active' => $request->boolean('active'),
        ];
    }

    private function resolveCategory(string $categoryName): ProductCategory
    {
        $normalizedName = trim($categoryName);
        $slug = Str::slug($normalizedName);

        return ProductCategory::firstOrCreate(
            ['slug' => $slug ?: 'categoria-' . Str::random(8)],
            [
                'name' => $normalizedName,
                'description' => null,
                'sort_order' => ((int) ProductCategory::max('sort_order')) + 1,
                'is_active' => true,
            ]
        );
    }

    private function syncComboComponents(Product $combo, array $components): void
    {
        $combo->components()->delete();

        foreach ($components as $component) {
            $combo->components()->create($component);
        }
    }

    private function comboComponentRows(?Product $combo = null): array
    {
        $oldRows = old('components');

        if (is_array($oldRows)) {
            return array_values($oldRows);
        }

        if ($combo) {
            return $combo->components
                ->map(fn ($component) => [
                    'component_product_id' => $component->component_product_id,
                    'quantity' => $component->quantity + 0,
                    'unit_label' => $component->unit_label,
                    'extra_price' => $component->extra_price + 0,
                    'is_optional' => $component->is_optional,
                ])
                ->values()
                ->all();
        }

        return [[
            'component_product_id' => null,
            'quantity' => 1,
            'unit_label' => 'unidad',
            'extra_price' => 0,
            'is_optional' => false,
        ]];
    }

    private function availableComboProducts(): Collection
    {
        return Product::where('product_type', 'simple')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'active']);
    }

    private function categoryOptions(): Collection
    {
        return ProductCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function taxRates(): Collection
    {
        return TaxRate::orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'is_default', 'is_active']);
    }

    private function deleteProductSafely(Product $product, string $routeName): RedirectResponse
    {
        if ($product->usedInCombos()->exists()) {
            return redirect()
                ->route($routeName)
                ->with('error', 'No se puede eliminar porque este producto forma parte de uno o mas combos.');
        }

        if ($product->saleItems()->exists()) {
            $product->update(['active' => false]);

            return redirect()
                ->route($routeName)
                ->with('warning', 'El producto tiene ventas registradas. Se desactivo para proteger el historico.');
        }

        $product->delete();

        return redirect()
            ->route($routeName)
            ->with('success', 'Registro eliminado correctamente.');
    }

    private function productPermissions(): array
    {
        return ['products.view', 'products.create', 'products.edit', 'products.delete'];
    }

    private function comboPermissions(): array
    {
        return ['combos.view', 'combos.create', 'combos.edit', 'combos.delete'];
    }

    private function taxPermissions(): array
    {
        return ['taxes.view', 'taxes.create', 'taxes.edit', 'taxes.delete'];
    }

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}

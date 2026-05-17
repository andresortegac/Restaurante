<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $products = Product::query()
            ->with(['menuCategory', 'taxRate'])
            ->where('product_type', 'simple')
            ->orderedForMenu()
            ->get();

        $categories = ProductCategory::withCount([
            'products as simple_products_count' => fn ($query) => $query->where('product_type', 'simple'),
            'products as active_products_count' => fn ($query) => $query->where('product_type', 'simple')->where('active', true),
        ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('products.menu.index', [
            'products' => $products,
            'categories' => $categories,
            'uncategorizedProductsCount' => Product::query()
                ->where('product_type', 'simple')
                ->whereNull('category_id')
                ->count(),
        ]);
    }

    public function categories()
    {
        if ($response = $this->denyIfUnauthorized($this->productPermissions())) {
            return $response;
        }

        $categories = ProductCategory::withCount([
            'products as simple_products_count' => fn ($query) => $query->where('product_type', 'simple'),
            'products as active_products_count' => fn ($query) => $query->where('product_type', 'simple')->where('active', true),
        ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('products.categories.index', [
            'categories' => $categories,
            'simpleProductsCount' => Product::query()->where('product_type', 'simple')->count(),
        ]);
    }

    public function createMenuProduct()
    {
        if ($response = $this->denyIfUnauthorized(['products.create'])) {
            return $response;
        }

        return view('products.menu.form', [
            'pageTitle' => 'Crear producto',
            'product' => new Product([
                'active' => true,
                'tracks_stock' => false,
                'sort_order' => $this->nextProductSortOrder(),
            ]),
            'selectedCategoryId' => old('category_id'),
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
        $request->attributes->set('current_image_path', null);

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
            'selectedCategoryId' => old('category_id', $product->category_id),
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
        $request->attributes->set('current_image_path', $product->image_path);

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

    public function storeCategory(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['products.create'])) {
            return $response;
        }

        $validated = $this->validateCategoryData($request);

        DB::transaction(function () use ($request, $validated): void {
            $category = ProductCategory::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'sort_order' => 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->moveCategoryToSortOrder($category, (int) $validated['sort_order']);
        });

        return redirect()
            ->route('products.categories.index')
            ->with('success', 'Categoria creada correctamente.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['products.edit'])) {
            return $response;
        }

        $validated = $this->validateCategoryData($request, $category);

        DB::transaction(function () use ($request, $validated, $category): void {
            $category->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->moveCategoryToSortOrder($category, (int) $validated['sort_order']);

            Product::query()
                ->where('category_id', $category->id)
                ->update(['category' => $validated['name']]);
        });

        return redirect()
            ->route('products.categories.index')
            ->with('success', 'Categoria actualizada correctamente.');
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['products.delete'])) {
            return $response;
        }

        $linkedProducts = $category->products()->count();

        if ($linkedProducts > 0) {
            return redirect()
                ->route('products.categories.index')
                ->with('error', 'No se puede eliminar la categoria porque aun tiene productos asociados.');
        }

        DB::transaction(function () use ($category): void {
            $category->delete();
            $this->normalizeCategorySortOrders();
        });

        return redirect()
            ->route('products.categories.index')
            ->with('success', 'Categoria eliminada correctamente.');
    }

    private function validateProductData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'stock' => ['nullable', Rule::requiredIf($request->boolean('tracks_stock')), 'integer', 'min:0'],
            'tracks_stock' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateCategoryData(Request $request, ?ProductCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['name'] = trim($validated['name']);
        $validated['slug'] = Str::slug($validated['name']);

        if ($validated['slug'] === '') {
            throw ValidationException::withMessages([
                'name' => 'Escribe un nombre valido para generar el identificador de la categoria.',
            ]);
        }

        $slugExists = ProductCategory::query()
            ->where('slug', $validated['slug'])
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($slugExists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una categoria con ese nombre.',
            ]);
        }

        return $validated;
    }

    private function moveCategoryToSortOrder(ProductCategory $category, int $requestedSortOrder): void
    {
        $categories = ProductCategory::query()
            ->whereKeyNot($category->id)
            ->lockForUpdate()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        $targetIndex = max(1, min($requestedSortOrder, count($categories) + 1)) - 1;

        array_splice($categories, $targetIndex, 0, [$category]);

        $this->persistCategorySortOrders($categories);
    }

    private function normalizeCategorySortOrders(): void
    {
        $this->persistCategorySortOrders(
            ProductCategory::query()
                ->lockForUpdate()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->all()
        );
    }

    /**
     * @param  array<int, ProductCategory>  $categories
     */
    private function persistCategorySortOrders(array $categories): void
    {
        foreach ($categories as $index => $category) {
            $newSortOrder = $index + 1;

            if ((int) $category->sort_order === $newSortOrder) {
                continue;
            }

            $category->forceFill(['sort_order' => $newSortOrder])->save();
        }
    }

    private function buildProductPayload(array $validated, Request $request, string $type): array
    {
        $category = $this->resolveCategoryFromValidatedData($validated);
        $tracksStock = $request->boolean('tracks_stock');
        $currentImagePath = $request->attributes->get('current_image_path');
        $imagePath = $currentImagePath;

        if ($request->boolean('remove_image') && $currentImagePath) {
            Storage::disk('public')->delete($currentImagePath);
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            if ($currentImagePath) {
                Storage::disk('public')->delete($currentImagePath);
            }

            $imagePath = $request->file('image')->store('products', 'public');
        }

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $tracksStock ? (int) ($validated['stock'] ?? 0) : 0,
            'tracks_stock' => $tracksStock,
            'category' => $category->name,
            'category_id' => $category->id,
            'tax_rate_id' => null,
            'product_type' => $type,
            'sort_order' => (int) $validated['sort_order'],
            'sku' => $validated['sku'] ?? null,
            'image_path' => $imagePath,
            'active' => $request->boolean('active'),
        ];
    }

    private function resolveCategoryFromValidatedData(array $validated): ProductCategory
    {
        if (array_key_exists('category_id', $validated)) {
            $category = ProductCategory::query()->find((int) $validated['category_id']);

            if (! $category) {
                throw ValidationException::withMessages([
                    'category_id' => 'Selecciona una categoria valida.',
                ]);
            }

            return $category;
        }

        throw ValidationException::withMessages([
            'category_id' => 'Selecciona una categoria valida.',
        ]);
    }

    private function categoryOptions(): Collection
    {
        return ProductCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function nextProductSortOrder(): int
    {
        return ((int) Product::max('sort_order')) + 1;
    }

    private function deleteProductSafely(Product $product, string $routeName): RedirectResponse
    {
        if ($product->saleItems()->exists()) {
            $product->update(['active' => false]);

            return redirect()
                ->route($routeName)
                ->with('warning', 'El producto tiene ventas registradas. Se desactivo para proteger el historico.');
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
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

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}

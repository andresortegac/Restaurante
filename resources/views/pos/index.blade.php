@extends('layouts.app')

@section('title', 'Punto de Venta - POS')

@section('content')
<div class="pos-toolbar">
    <div>
        <h1>Punto de Venta</h1>
        <p>{{ $activeBox ? 'Caja activa: ' . $activeBox->name : 'No hay una caja abierta en este momento.' }}</p>
    </div>
    <a href="{{ route('pos.sales-history.index') }}" class="btn btn-outline-primary">
        <i class="fas fa-clock-rotate-left"></i> Historial de ventas
    </a>
</div>

<div class="pos-container" id="posApp">
    <div class="row h-100">
        <!-- Seccion de Productos -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-box"></i> Productos</h5>
                    <input type="text" id="searchProducts" class="form-control mt-3" placeholder="Buscar producto...">
                </div>
                <div class="card-body products-list" id="productsList" style="overflow-y: auto; height: calc(100vh - 250px);">
                    <div class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Cargando productos...
                    </div>
                </div>
            </div>
        </div>

        <!-- Seccion de Carrito y Pago -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-shopping-cart"></i> Carrito de Compras</h5>
                </div>
                <div class="card-body" style="height: calc(100vh - 450px); overflow-y: auto;">
                    <div id="cartItems">
                        <p class="text-muted text-center">El carrito esta vacio</p>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="d-grid gap-2">
                        <div class="row mb-2">
                            <div class="col-6">Subtotal:</div>
                            <div class="col-6 text-end"><strong id="subtotal">$0.00</strong></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">Descuento:</div>
                            <div class="col-6 text-end"><strong id="discount">$0.00</strong></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">Impuesto (16%):</div>
                            <div class="col-6 text-end"><strong id="tax">$0.00</strong></div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-6">Total:</div>
                            <div class="col-6 text-end"><h5 id="total">$0.00</h5></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cupon de Descuento</label>
                            <input type="text" id="couponCode" class="form-control" placeholder="Ingrese codigo...">
                            <button class="btn btn-sm btn-secondary mt-2 w-100" onclick="applyCoupon()">Aplicar Cupon</button>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Metodo de Pago</label>
                            <select id="paymentMethod" class="form-select">
                                <option value="">Seleccionar metodo...</option>
                            </select>
                        </div>

                        <button class="btn btn-danger w-100 mb-2" onclick="clearCart()">
                            <i class="fas fa-times"></i> Vaciar Carrito
                        </button>
                        <button class="btn btn-success w-100" onclick="completeSale()" id="completeBtn" disabled>
                            <i class="fas fa-check"></i> Completar Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pos-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.pos-toolbar h1 {
    margin: 0 0 4px;
    font-size: 32px;
    color: #243b7a;
}

.pos-toolbar p {
    margin: 0;
    color: #64748b;
}

.pos-container {
    min-height: calc(100vh - 180px);
}

.products-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.product-card {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.product-card:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
    transform: translateY(-2px);
}

.product-image {
    width: 100%;
    height: 80px;
    background: #f0f0f0;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
}

.cart-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-item-name {
    flex: 1;
    font-size: 14px;
}

.cart-item-controls {
    display: flex;
    gap: 5px;
    align-items: center;
}

.cart-item-controls button {
    padding: 2px 5px;
    font-size: 12px;
}

@media (max-width: 768px) {
    .pos-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .pos-toolbar h1 {
        font-size: 26px;
    }
}
</style>

<script>
let cart = [];
let products = @json($initialProducts ?? []);
let paymentMethods = @json($initialPaymentMethods ?? []);
const activeBoxId = @json(optional($activeBox)->id);
const csrfToken = @json(csrf_token());
const salesHistoryUrl = @json(route('pos.sales-history.index'));
const salesPrintBaseUrl = @json(url('/pos/sales'));

document.addEventListener('DOMContentLoaded', function() {
    renderProducts();
    renderPaymentMethods();
    updateTotals();
    loadProducts(true);
    loadPaymentMethods(true);
});

async function loadProducts(isBackgroundRefresh = false) {
    try {
        const response = await fetch('/pos/api/products', {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('No se pudieron cargar los productos del POS.');
        }

        const data = await response.json();
        products = Array.isArray(data) ? data : [];
        applyProductFilter();
    } catch (error) {
        console.error('Error al cargar productos:', error);

        if (!isBackgroundRefresh && products.length === 0) {
            renderProducts([]);
        }
    }
}

async function loadPaymentMethods(isBackgroundRefresh = false) {
    try {
        const response = await fetch('/pos/api/payment-methods', {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('No se pudieron cargar los metodos de pago.');
        }

        const data = await response.json();
        paymentMethods = Array.isArray(data) ? data : [];
        renderPaymentMethods();
    } catch (error) {
        console.error('Error al cargar metodos de pago:', error);

        if (!isBackgroundRefresh && paymentMethods.length === 0) {
            renderPaymentMethods();
        }
    }
}

function normalizeText(text) {
    return (text || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function buildPrintUrl(saleId) {
    return `${salesPrintBaseUrl}/${saleId}/print`;
}

function showMessage(icon, title, text) {
    if (window.Swal) {
        return Swal.fire({
            icon,
            title,
            text,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: icon === 'error' ? '#dc3545' : '#1d4ed8'
        });
    }

    alert(text);
    return Promise.resolve({});
}

function confirmAction(title, text, confirmButtonText = 'Aceptar') {
    if (window.Swal) {
        return Swal.fire({
            icon: 'warning',
            title,
            text,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6b7280'
        });
    }

    return Promise.resolve({
        isConfirmed: confirm(text)
    });
}

async function showSaleSuccess(sale) {
    const total = Number(sale.total ?? 0).toFixed(2);

    if (window.Swal) {
        const result = await Swal.fire({
            icon: 'success',
            title: 'Venta completada',
            html: `La venta <strong>#${sale.id}</strong> se registro correctamente por <strong>$${total}</strong>.`,
            showConfirmButton: true,
            confirmButtonText: 'Imprimir factura',
            confirmButtonColor: '#1d4ed8',
            showDenyButton: true,
            denyButtonText: 'Ver historial',
            denyButtonColor: '#667eea',
            showCancelButton: true,
            cancelButtonText: 'Cerrar'
        });

        if (result.isConfirmed) {
            window.open(buildPrintUrl(sale.id), '_blank', 'noopener');
        }

        if (result.isDenied) {
            window.location.href = salesHistoryUrl;
        }

        return;
    }

    alert(`Venta completada exitosamente. ID: ${sale.id}`);
}

function renderProducts(productList = products) {
    const productsList = document.getElementById('productsList');

    if (!Array.isArray(productList) || productList.length === 0) {
        productsList.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-box-open d-block mb-2"></i>
                No hay productos disponibles para vender.
            </div>
        `;
        return;
    }

    productsList.innerHTML = productList.map(product => `
        <div class="product-card" onclick="selectProduct(${product.id})">
            <div class="product-image"><i class="fas fa-utensils"></i></div>
            <h6 style="margin: 8px 0; font-size: 12px;">${product.name}</h6>
            <p style="margin: 0; color: #007bff; font-weight: bold;">$${parseFloat(product.price).toFixed(2)}</p>
            <small style="color: #999;">Stock: ${product.stock}</small>
            <div style="margin-top: 6px; font-size: 11px; color: #666;">${product.product_type === 'combo' ? 'Combo' : 'Producto'}</div>
        </div>
    `).join('');
}

function renderPaymentMethods() {
    const select = document.getElementById('paymentMethod');

    if (!Array.isArray(paymentMethods) || paymentMethods.length === 0) {
        select.disabled = true;
        select.innerHTML = '<option value="">No hay metodos activos</option>';
        return;
    }

    select.disabled = false;
    select.innerHTML = '<option value="">Seleccionar metodo...</option>' + paymentMethods.map(method => `
        <option value="${method.id}">${method.name}</option>
    `).join('');
}

function selectProduct(productId) {
    const selectedProduct = products.find(product => Number(product.id) === Number(productId)) || null;

    if (!selectedProduct) {
        showMessage('error', 'Producto no disponible', 'No se encontro el producto seleccionado.');
        return;
    }

    const availableStock = Number(selectedProduct.stock ?? 0);
    const currentQuantityInCart = cart.find(item => Number(item.id) === Number(selectedProduct.id))?.quantity ?? 0;

    if (availableStock < 1 || currentQuantityInCart >= availableStock) {
        showMessage('warning', 'Stock agotado', 'Producto sin stock disponible.');
        return;
    }

    addToCart(selectedProduct, 1);
}

function addToCart(product, quantity) {
    const existingItem = cart.find(item => Number(item.id) === Number(product.id));

    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            ...product,
            price: Number(product.price),
            stock: Number(product.stock ?? 0),
            quantity
        });
    }

    renderCart();
    updateTotals();
}

function removeFromCart(productId) {
    cart = cart.filter(item => Number(item.id) !== Number(productId));
    renderCart();
    updateTotals();
}

function updateQuantity(productId, newQuantity) {
    const item = cart.find(cartItem => Number(cartItem.id) === Number(productId));

    if (!item) {
        return;
    }

    const availableStock = Number(products.find(product => Number(product.id) === Number(productId))?.stock ?? item.stock ?? 0);

    if (newQuantity > availableStock) {
        showMessage('warning', 'Stock insuficiente', `No puedes superar el stock disponible (${availableStock}).`);
        return;
    }

    if (newQuantity > 0) {
        item.quantity = newQuantity;
        renderCart();
        updateTotals();
        return;
    }

    removeFromCart(productId);
}

function renderCart() {
    const cartItems = document.getElementById('cartItems');

    if (cart.length === 0) {
        cartItems.innerHTML = '<p class="text-muted text-center">El carrito esta vacio</p>';
        document.getElementById('completeBtn').disabled = true;
        return;
    }

    cartItems.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div>
                <div class="cart-item-name">${item.name}</div>
                <small>$${parseFloat(item.price).toFixed(2)} c/u</small>
            </div>
            <div class="cart-item-controls">
                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">-</button>
                <input type="number" value="${item.quantity}" style="width: 40px; text-align: center;" readonly>
                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">+</button>
                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');

    document.getElementById('completeBtn').disabled = false;
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = 0;
    const tax = (subtotal - discount) * 0.16;
    const total = subtotal - discount + tax;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('discount').textContent = '$' + discount.toFixed(2);
    document.getElementById('tax').textContent = '$' + tax.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

async function clearCart() {
    const confirmation = await confirmAction('Vaciar carrito', 'Desea vaciar el carrito?', 'Vaciar');

    if (confirmation.isConfirmed) {
        cart = [];
        renderCart();
        updateTotals();
    }
}

function applyCoupon() {
    const code = document.getElementById('couponCode').value;

    if (!code) {
        showMessage('info', 'Cupon requerido', 'Ingrese un codigo de cupon.');
        return;
    }

    showMessage('info', 'En desarrollo', 'La funcionalidad de cupones estara disponible pronto.');
}

async function completeSale() {
    if (cart.length === 0) {
        showMessage('warning', 'Carrito vacio', 'Agrega al menos un producto al carrito.');
        return;
    }

    if (!activeBoxId) {
        showMessage('warning', 'Caja no disponible', 'No hay una caja abierta para registrar la venta.');
        return;
    }

    const paymentMethodId = document.getElementById('paymentMethod').value;

    if (!paymentMethodId) {
        showMessage('warning', 'Metodo de pago requerido', 'Seleccione un metodo de pago.');
        return;
    }

    const saleData = {
        box_id: activeBoxId,
        payment_method_id: Number(paymentMethodId),
        items: cart.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            unit_price: item.price
        }))
    };

    const completeBtn = document.getElementById('completeBtn');
    completeBtn.disabled = true;

    try {
        const response = await fetch('/pos/api/sales', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(saleData)
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(extractErrorMessage(data, 'Error al completar la venta'));
        }

        if (!data.id) {
            throw new Error('La venta no se pudo registrar correctamente.');
        }

        syncProductStockFromCart();
        cart = [];
        renderCart();
        updateTotals();
        document.getElementById('paymentMethod').value = '';
        document.getElementById('couponCode').value = '';
        await showSaleSuccess(data);
    } catch (error) {
        console.error('Error al completar la venta:', error);
        await showMessage('error', 'No se pudo completar la venta', error.message || 'Error al completar la venta');
    } finally {
        if (cart.length > 0) {
            completeBtn.disabled = false;
        }
    }
}

function syncProductStockFromCart() {
    cart.forEach(item => {
        const product = products.find(productItem => Number(productItem.id) === Number(item.id));

        if (product) {
            product.stock = Math.max(0, Number(product.stock ?? 0) - Number(item.quantity ?? 0));
        }
    });

    applyProductFilter();
}

function applyProductFilter() {
    const search = normalizeText(document.getElementById('searchProducts').value.trim());
    const filtered = products.filter(product => normalizeText(product.name).includes(search));
    renderProducts(filtered);
}

function extractErrorMessage(data, fallbackMessage) {
    if (data?.errors) {
        const validationMessages = Object.values(data.errors).flat();

        if (validationMessages.length > 0) {
            return validationMessages.join(' ');
        }
    }

    return data?.message || data?.error || fallbackMessage;
}

document.getElementById('searchProducts').addEventListener('input', applyProductFilter);
</script>
@endsection

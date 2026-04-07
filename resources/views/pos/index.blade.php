@extends('layouts.app')

@section('title', 'Punto de Venta - POS')

@section('content')
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

<div class="modal fade" id="quantityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cantidad de Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Producto: <strong id="modalProductName"></strong></label>
                <label class="form-label mt-2">Cantidad:</label>
                <input type="number" id="quantityInput" class="form-control" value="1" min="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="addToCartConfirm()">Agregar</button>
            </div>
        </div>
    </div>
</div>

<style>
.pos-container {
    height: calc(100vh - 120px);
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
</style>

<script>
let cart = [];
let products = [];
let paymentMethods = [];
let selectedProduct = null;

document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    loadPaymentMethods();
});

function loadProducts() {
    fetch('/pos/api/products')
        .then(response => response.json())
        .then(data => {
            products = data;
            renderProducts();
        })
        .catch(error => console.error('Error:', error));
}

function loadPaymentMethods() {
    fetch('/pos/api/payment-methods')
        .then(response => response.json())
        .then(data => {
            paymentMethods = data;
            renderPaymentMethods();
        })
        .catch(error => console.error('Error:', error));
}

function normalizeText(text) {
    return (text || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function renderProducts(productList = products) {
    const productsList = document.getElementById('productsList');
    productsList.innerHTML = productList.map(product => `
        <div class="product-card" onclick="selectProduct(${product.id})">
            <div class="product-image"><i class="fas fa-utensils"></i></div>
            <h6 style="margin: 8px 0; font-size: 12px;">${product.name}</h6>
            <p style="margin: 0; color: #007bff; font-weight: bold;">$${parseFloat(product.price).toFixed(2)}</p>
            <small style="color: #999;">Stock: ${product.stock}</small>
        </div>
    `).join('');
}

function renderPaymentMethods() {
    const select = document.getElementById('paymentMethod');
    select.innerHTML = '<option value="">Seleccionar metodo...</option>' + paymentMethods.map(method => `
        <option value="${method.id}">${method.name}</option>
    `).join('');
}

function selectProduct(productId) {
    selectedProduct = products.find(p => p.id === productId);
    if (selectedProduct && selectedProduct.stock > 0) {
        document.getElementById('modalProductName').textContent = selectedProduct.name;
        document.getElementById('quantityInput').value = 1;
        new bootstrap.Modal(document.getElementById('quantityModal')).show();
    } else {
        alert('Producto sin stock disponible');
    }
}

function addToCartConfirm() {
    const quantity = parseInt(document.getElementById('quantityInput').value);
    if (quantity > 0 && quantity <= selectedProduct.stock) {
        addToCart(selectedProduct, quantity);
        bootstrap.Modal.getInstance(document.getElementById('quantityModal')).hide();
    } else {
        alert('Cantidad invalida');
    }
}

function addToCart(product, quantity) {
    const existingItem = cart.find(item => item.id === product.id);
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({ ...product, quantity });
    }
    renderCart();
    updateTotals();
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    renderCart();
    updateTotals();
}

function updateQuantity(productId, newQuantity) {
    const item = cart.find(i => i.id === productId);
    if (item) {
        if (newQuantity > 0) {
            item.quantity = newQuantity;
        } else {
            removeFromCart(productId);
        }
        renderCart();
        updateTotals();
    }
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
    const discount = 0; // Sera calculado con cupones
    const tax = (subtotal - discount) * 0.16;
    const total = subtotal - discount + tax;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('discount').textContent = '$' + discount.toFixed(2);
    document.getElementById('tax').textContent = '$' + tax.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

function clearCart() {
    if (confirm('Desea vaciar el carrito?')) {
        cart = [];
        renderCart();
        updateTotals();
    }
}

function applyCoupon() {
    const code = document.getElementById('couponCode').value;
    if (!code) {
        alert('Ingrese un codigo de cupon');
        return;
    }
    alert('Funcionalidad de cupones en desarrollo');
}

function completeSale() {
    const paymentMethodId = document.getElementById('paymentMethod').value;
    if (!paymentMethodId) {
        alert('Seleccione un metodo de pago');
        return;
    }

    const saleData = {
        box_id: 1,
        items: cart.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            unit_price: item.price
        }))
    };

    fetch('/pos/api/sales', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(saleData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.id) {
            alert('Venta completada exitosamente. ID: ' + data.id);
            cart = [];
            renderCart();
            updateTotals();
            document.getElementById('paymentMethod').value = '';
            document.getElementById('couponCode').value = '';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al completar la venta');
    });
}

document.getElementById('searchProducts').addEventListener('input', function(e) {
    const search = normalizeText(e.target.value.trim());
    const filtered = products.filter(product => normalizeText(product.name).includes(search));
    renderProducts(filtered);
});
</script>
@endsection

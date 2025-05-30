@extends('layouts.app3')
@section('title', 'Registrar Venta')
@section('content')
<div class="container">
    <h1 class="mb-4">Registrar Venta</h1>

    <!-- Barra de búsqueda -->
    <form id="search-form" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Buscar productos por nombre..." value="{{ request('search') }}" id="search-input">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <!-- Lista de productos -->
    <h3>Productos Disponibles</h3>
    <div id="product-list">
        <div class="row">
            @forelse ($productos as $producto)
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">{{ $producto->nombre }}</h5>
                            <p class="card-text mb-2">
                                Precio (sin IVA): ${{ number_format($producto->precio, 2) }}<br>
                                Precio (con IVA): ${{ number_format($producto->precio * 1.19, 2) }}<br>
                                Stock: {{ $producto->stock }}
                            </p>
                            <button class="btn btn-success btn-sm mt-auto add-to-cart"
                                    data-id="{{ $producto->id }}"
                                    data-name="{{ $producto->nombre }}"
                                    data-price="{{ $producto->precio }}"
                                    data-stock="{{ $producto->stock }}">
                                Añadir
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-center">No se encontraron productos.</p>
                </div>
            @endforelse
        </div>
        <div id="pagination-links" class="d-flex justify-content-center mt-4">
            {{ $productos->links() }}
        </div>
    </div>

    <!-- Calculadora de venta -->
    <div class="card mt-4 sticky-cart">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Carrito de Venta</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('cajero.sale') }}" id="sale-form">
                @csrf
                <table class="table table-bordered table-sm" id="cart-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Subtotal (con IVA)</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cart-items">
                        <!-- Productos añadidos aparecerán aquí -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end"><strong>Subtotal (sin IVA):</strong></td>
                            <td colspan="2" id="subtotal-amount">$0.00</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-end"><strong>IVA (19%):</strong></td>
                            <td colspan="2" id="iva-amount">$0.00</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-end"><strong>Total (con IVA):</strong></td>
                            <td colspan="2" id="total-amount">$0.00</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="mb-3">
                    <label class="form-label">Método de Pago</label>
                    <select name="metodo_pago" class="form-control" required>
                        <option value="efectivo">Efectivo</option>
                        <option value="nequi">Nequi</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-success w-100">Registrar Venta</button>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    .sticky-cart {
        position: sticky;
        top: 20px;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .card {
        border-radius: 8px;
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .table-sm th, .table-sm td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    .quantity-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cart = [];
        const cartTable = document.getElementById('cart-items');
        const subtotalAmount = document.getElementById('subtotal-amount');
        const ivaAmount = document.getElementById('iva-amount');
        const totalAmount = document.getElementById('total-amount');
        const searchForm = document.getElementById('search-form');
        const productList = document.getElementById('product-list');
        const paginationLinks = document.getElementById('pagination-links');
        const IVA_RATE = 1.19;
        const stockCache = new Map(); // Cache for stock values

        // Debounced search
        const debouncedSearch = _.debounce((search) => {
            fetch(`{{ route('cajero.sale') }}?search=${encodeURIComponent(search)}`, {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                productList.querySelector('.row').innerHTML = doc.querySelector('#product-list .row').innerHTML;
                paginationLinks.innerHTML = doc.getElementById('pagination-links').innerHTML;
                bindAddToCartButtons();
                bindPaginationLinks();
            })
            .catch(error => console.error('Error in search:', error));
        }, 300);

        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const search = document.getElementById('search-input').value;
            debouncedSearch(search);
        });

        // Bind add-to-cart buttons
        function bindAddToCartButtons() {
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.removeEventListener('click', handleAddToCart);
                button.addEventListener('click', handleAddToCart);
            });
        }

        function handleAddToCart() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            const stock = parseInt(this.dataset.stock);

            const existingItem = cart.find(item => item.id === id);
            if (existingItem) {
                if (existingItem.quantity < stock) {
                    existingItem.quantity += 1;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sin stock',
                        text: 'No hay suficiente stock disponible.',
                    });
                    return;
                }
            } else {
                cart.push({ id, name, price, quantity: 1, stock });
                stockCache.set(id, stock); // Cache initial stock
            }

            updateCart();
        }

        bindAddToCartButtons();

        // Bind pagination links for AJAX
        function bindPaginationLinks() {
            document.querySelectorAll('#pagination-links a').forEach(link => {
                link.removeEventListener('click', handlePaginationClick);
                link.addEventListener('click', handlePaginationClick);
            });
        }

        function handlePaginationClick(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            fetch(url, {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                productList.querySelector('.row').innerHTML = doc.querySelector('#product-list .row').innerHTML;
                paginationLinks.innerHTML = doc.getElementById('pagination-links').innerHTML;
                bindAddToCartButtons();
                bindPaginationLinks();
            })
            .catch(error => console.error('Error in pagination:', error));
        }

        bindPaginationLinks();

        // Actualizar tabla del carrito y total
        function updateCart() {
            cartTable.innerHTML = '';
            let subtotal = 0;

            cart.forEach((item, index) => {
                const subtotalWithIVA = item.price * item.quantity * IVA_RATE;
                subtotal += item.price * item.quantity;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.name}</td>
                    <td>
                        <input type="number" class="form-control quantity-input" 
                               value="${item.quantity}" min="1" max="${item.stock}" 
                               data-index="${index}" data-id="${item.id}">
                        <input type="hidden" name="productos[]" value="${item.id}">
                        <input type="hidden" name="cantidades[]" value="${item.quantity}" class="quantity-hidden">
                    </td>
                    <td>$${subtotalWithIVA.toFixed(2)}</td>
                    <td>
                        <button class="btn btn-danger btn-sm remove-item" data-index="${index}">Eliminar</button>
                    </td>
                `;
                cartTable.appendChild(row);
            });

            const iva = subtotal * (IVA_RATE - 1);
            const total = subtotal * IVA_RATE;

            subtotalAmount.textContent = `$${subtotal.toFixed(2)}`;
            ivaAmount.textContent = `$${iva.toFixed(2)}`;
            totalAmount.textContent = `$${total.toFixed(2)}`;

            // Bind event listeners
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.removeEventListener('change', debouncedQuantityChange);
                input.addEventListener('change', debouncedQuantityChange);
            });

            document.querySelectorAll('.remove-item').forEach(button => {
                button.removeEventListener('click', handleRemoveItem);
                button.addEventListener('click', handleRemoveItem);
            });
        }

        // Debounced quantity change
        const debouncedQuantityChange = _.debounce(async (e) => {
            const input = e.target;
            const index = parseInt(input.dataset.index);
            const productId = input.dataset.id;
            const newQuantity = parseInt(input.value);
            const originalQuantity = cart[index].quantity;

            // Disable input during stock check
            input.disabled = true;

            try {
                // Use cached stock if available and not stale
                let stock = stockCache.get(productId) || cart[index].stock;
                let useCache = !!stockCache.get(productId);

                if (!useCache) {
                    const response = await fetch(`/api/productos/${productId}/stock`, {
                        headers: { 
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
                    }

                    const data = await response.json();
                    stock = data.stock;
                    stockCache.set(productId, stock);
                }

                if (newQuantity <= stock && newQuantity > 0) {
                    cart[index].quantity = newQuantity;
                    const hiddenInput = input.nextElementSibling.nextElementSibling;
                    hiddenInput.value = newQuantity;
                    updateCart();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cantidad inválida',
                        text: `Solo hay ${stock} unidades disponibles.`,
                    });
                    input.value = originalQuantity;
                }
            } catch (error) {
                console.error('Error checking stock:', error);
                Swal.fire({
                    icon: 'warning',
                    title: 'Advertencia',
                    text: 'No se pudo verificar el stock. Usando stock local (' + cart[index].stock + ').',
                });
                // Fallback to local stock
                if (newQuantity <= cart[index].stock && newQuantity > 0) {
                    cart[index].quantity = newQuantity;
                    const hiddenInput = input.nextElementSibling.nextElementSibling;
                    hiddenInput.value = newQuantity;
                    updateCart();
                } else {
                    input.value = originalQuantity;
                }
            } finally {
                input.disabled = false;
            }
        }, 500);

        function handleRemoveItem() {
            const index = parseInt(this.dataset.index);
            const productId = cart[index].id;
            cart.splice(index, 1);
            // Optionally clear stock cache for removed item
            stockCache.delete(productId);
            updateCart();
        }

        // Validar formulario
        document.getElementById('sale-form').addEventListener('submit', (e) => {
            if (cart.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Carrito vacío',
                    text: 'Por favor, añade productos antes de registrar la venta.',
                });
            }
        });
    });
</script>
@endpush
@endsection
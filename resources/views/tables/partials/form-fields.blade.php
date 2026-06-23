<div class="form-grid">
    <div>
        <label class="form-label" for="name">Nombre de la mesa</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $restaurantTable->name) }}" required>
    </div>

    @if($restaurantTable->exists)
        <div>
            <label class="form-label" for="code">Codigo interno</label>
            <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $restaurantTable->code) }}" required>
            <div class="form-help">Ejemplo: M-01, Terraza-2 o VIP-05.</div>
        </div>
    @endif

    <div>
        <label class="form-label" for="area">Area</label>
        <input type="text" class="form-control" id="area" name="area" value="{{ old('area', $restaurantTable->area) }}" placeholder="Salon principal, terraza, VIP...">
    </div>

    <div>
        <label class="form-label" for="capacity">Capacidad</label>
        <input type="number" class="form-control" id="capacity" name="capacity" min="1" max="30" value="{{ old('capacity', $restaurantTable->capacity) }}" required>
    </div>

    <div>
        <label class="form-label" for="status">Estado visual</label>
        <select class="form-select" id="status" name="status" required>
            <option value="free" @selected(old('status', $restaurantTable->status) === 'free')>Libre</option>
            <option value="occupied" @selected(old('status', $restaurantTable->status) === 'occupied')>Ocupada</option>
            <option value="reserved" @selected(old('status', $restaurantTable->status) === 'reserved')>Reservada</option>
        </select>
        <div class="form-help">Usa reservado para apartados y libre cuando no tenga servicio activo.</div>
    </div>

    <div class="form-switch-row">
        <div>
            <label class="form-label d-block mb-1" for="is_active">Disponibilidad en el sistema</label>
            <div class="form-help">Si desactivas la mesa se oculta del flujo operativo, pero conserva su historial.</div>
        </div>
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $restaurantTable->exists ? $restaurantTable->is_active : true))>
            <label class="form-check-label" for="is_active">Mesa activa</label>
        </div>
    </div>

    <div class="full-width">
        <label class="form-label" for="notes">Notas operativas</label>
        <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Detalles utiles para el equipo, por ejemplo ubicacion o restricciones.">{{ old('notes', $restaurantTable->notes) }}</textarea>
    </div>
</div>

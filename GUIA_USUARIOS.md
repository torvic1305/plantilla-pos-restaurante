# Guía de Uso - Sistema POS Restaurante

## Inicio Rápido

### 1. Primer Acceso
- URL: `http://localhost/restaurante/`
- Usuario: **admin**
- Contraseña: **pointofsale**

### 2. Configuración Inicial (Gerente)
1. Ve a **Dashboard** → **Configuración**
2. Configura el **Porcentaje de Impuesto** (ej: 16% para IVA)
3. Ajusta el **Número de Mesas**
4. Define las **Formas de Pago** disponibles
5. Personaliza **Colores y Estilos** si deseas
6. Haz clic en **Guardar Configuración**

### 3. Crear Empleados
1. Ve a **Empleados**
2. Haz clic en **Agregar Empleado**
3. Completa datos: nombre, usuario, contraseña, rol
4. Roles disponibles:
   - **Mesero**: Toma pedidos en el POS
   - **Cocinero**: Ve comandas en Cocina
   - **Gerente**: Acceso a todas las funciones

### 4. Crear Menú
1. Ve a **Menú**
2. Haz clic en **Agregar Producto**
3. Completa: nombre, precio, categoría
4. Categorías recomendadas: Entradas, Platos principales, Bebidas, Postres

## Operación Diaria

### Tomar un Pedido (Mesero)
1. Entra en **Punto de Venta**
2. **Selecciona tu nombre** en "Mesero"
3. **Elige una mesa** en el panel de mesas
4. **Agrega productos** haciendo clic en el menú
5. Para cada producto puedes:
   - Hacer clic en **Nota** para agregar instrucciones especiales
   - Ver precio y descripción
6. En el carrito ves:
   - Lista de items
   - **Subtotal**
   - **Descuento** (si aplica promoción)
   - **Impuesto**
   - **Total**

### Modificar un Pedido
1. Haz clic en **Nota** junto al item para agregar/editar notas
2. Haz clic en **Quitar** para eliminar un item
3. Puedes agregar un **Descuento Manual** antes de cobrar

### Suspender una Venta
1. Si el cliente aún no paga, haz clic en **Suspender Venta**
2. Se guarda la orden para continuar después
3. Se imprime automáticamente la comanda para cocina
4. En **Informes** → **Ventas Suspendidas** puedes:
   - Buscar la venta por ID, mesa o mesero
   - Continuar agregando items
   - Completar y cobrar

### Cobrar una Venta
1. Asegúrate de que la orden está completa
2. Selecciona **Forma de Pago**:
   - **Efectivo**: Ingresa monto pagado, se calcula automáticamente el cambio
   - **Tarjeta/Transferencia**: El monto se auto-completa
3. Haz clic en **Cobrar**
4. Se abre un modal de **confirmación**
5. Haz clic en **Confirmar y Cobrar**
6. Se imprime automáticamente:
   - **Pre-recibo** (visto por cliente antes de terminar)
   - **Recibo final** (con método de pago)
   - **Comandas** para cocina/barra (si hay items de comida/bebida)
7. Aparece panel de **Propina** (opcional)
8. Sistema listo para siguiente venta

### Agregar Propina (Después de Cobrar)
1. Después de cobrar, aparece el panel de propina
2. Opciones rápidas: **0%**, **10%**, **15%**, **20%**
3. O ingresa un monto manual
4. La propina se suma al recibo y se imprime

## Reportes y Análisis

### Informes Básicos
Ve a **Informes** para:
- **Ventas completadas**: Tabla de todas las ventas con opción de agregar propina
- **Ventas suspendidas**: Búsqueda y reimpresión de pedidos en pausa
- **Propinas**: Historial de propinas por fecha

### Reportes Avanzados
Ve a **Reportes Avanzados** para:

**Filtros Disponibles:**
- Rango de fechas (desde/hasta)
- Tipo de reporte

**Tipo: Ventas por Día**
- Muestra ingresos por cada día
- Desglose: Efectivo, Tarjeta, Transferencia
- Descuentos e Impuestos aplicados
- Propinas recibidas

**Tipo: Ventas por Mesero**
- Productividad de cada mesero
- Número de ventas y monto total
- Ticket promedio (venta promedio)
- Propinas generadas
- Descuentos aplicados

**Tipo: Ventas por Hora**
- Analiza picos de venta por horario
- Total de ventas por hora
- Ticket promedio en cada horario
- Propinas por franja horaria

## Cierre de Caja

### Realizar Cierre Diario
1. Ve a **Cierre de Caja**
2. Aparece automático un resumen de ventas del día:
   - Total de transacciones
   - Ingresos por método de pago
   - Descuentos e impuestos
   - Propinas
3. Verifica los montos contra tu caja física
4. Haz clic en **Finalizar Cierre** (no se puede deshacer)
5. Se guarda el registro en historial

### Imprimir Cierre
1. En el panel de Cierre de Caja, haz clic en **Imprimir Cierre**
2. Se abre ventana con formato 80mm (recibo)
3. Imprime o guarda como PDF

## Kitchen Display System (KDS)

### Para Cocineros
1. Ve a **Cocina**
2. Aparecen automáticamente las órdenes activas
3. Muestra:
   - Items a preparar con instrucciones especiales
   - Número de mesa
   - Hora del pedido
4. Al terminar, marca como listo

## Gestión Avanzada

### Crear Promociones
1. Ve a **Promociones**
2. Haz clic en **Agregar Promoción**
3. Completa:
   - Nombre (ej: "Descuento Almuerzo")
   - Porcentaje de descuento (ej: 15%)
   - Monto mínimo de compra (ej: $100)
   - Fecha/Hora inicio y fin
4. **Automáticamente se aplica** cuando se alcanza el monto mínimo

### Registrar Merma
1. Ve a **Merma**
2. Haz clic en **Agregar Merma**
3. Completa:
   - Producto dañado
   - Cantidad
   - Razón (ej: Rotura, Caducidad, etc)
4. Se registra para auditoría

### Gestionar Empleados
1. Ve a **Empleados**
2. Ver lista de personal activo
3. Desde Gerente:
   - Agregar nuevos empleados
   - Ver historial de accesos
   - Asignar permisos

## Solución de Problemas

### No aparecen los impuestos
- Ve a **Configuración**
- Verifica que el **Porcentaje de Impuesto** es > 0
- Guarda los cambios
- Recarga POS

### Impresora no funciona
- El sistema abre automáticamente una ventana de impresión
- En la ventana, selecciona tu impresora térmica (80mm)
- Asegúrate que está conectada y encendida
- Usa "Imprimir a PDF" si no tienes impresora disponible

### Venta no se guarda
- Verifica que has seleccionado **Mesero** y **Mesa**
- Revisa que el total de efectivo es ≥ al total de venta
- Intenta nuevamente

### Olvidé contraseña
- Contacta al administrador (Gerente)
- Ve a **Empleados** y reseteá la contraseña

## Consejos Prácticos

✅ **Imprime Pre-Recibo** antes de finalizar para que cliente verifique
✅ **Suspende en vez de cobrar** si aún hay duda (ej: cliente pidió esperar)
✅ **Agrega notas** para especiales (alergias, cocción especial, etc)
✅ **Revisa reportes** regularmente para analizar horarios con más ventas
✅ **Realiza cierre diario** para reconciliar caja con sistema
✅ **Crea promociones** durante horarios bajos para incentivar ventas

---

**¿Necesitas ayuda?** Contacta al equipo técnico o consulta el manual en README.md

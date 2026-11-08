# Guía de Uso - Sistema POS Restaurante

## Inicio Rápido

### 1. Primer Acceso
- URL: `http://localhost/restaurante/`
- Usuario: **admin**
- Contraseña: **pointofsale**
- Al actualizar una instalación existente, respalda la base de datos y ejecuta `setup.php` una vez para aplicar las columnas nuevas.

### 2. Configuración Inicial (Gerente)
1. Ve a **Dashboard** → **Configuración**
2. Configura el **Nombre del restaurante**
3. Configura el **Porcentaje de Impuesto** (ej: 16% para IVA)
4. Ajusta el **Número de Mesas** y decide si se permiten ventas de mostrador
5. Define las **Formas de Pago** y el método predeterminado
6. Configura el efectivo mínimo de caja para recibir alertas en el Dashboard
7. Elige las opciones del POS, confirmación de cobro y campos del recibo
8. En **Impresoras y comandas**, configura IP privada y puerto (normalmente `9100`) para recibos, cocina y barra. Deja una IP vacía para usar el diálogo del navegador.
9. Escribe las categorías que deben ir a barra, una por línea; las demás categorías se mandan a cocina. Guarda y prueba cada impresora.
10. En **Recibos**, cambia el ancho, tamaño de letra, altura y márgenes; arrastra los márgenes en la vista previa editable para ajustar el área de impresión.
11. Sube opcionalmente un logo y/o una imagen QR, elige si va en el encabezado o en el pie y selecciona qué datos, IVA y propina aparecerán.
12. Personaliza **Colores y Estilos** si deseas y haz clic en **Guardar Configuración**.

La impresión ESC/POS por red requiere que el servidor donde se ejecuta PHP pueda conectarse por TCP a cada impresora. Usa impresoras ESC/POS compatibles con impresión directa de red; esta opción no descubre impresoras automáticamente.

### 3. Crear Empleados
1. Ve a **Configuración** → **Empleados y permisos** → **Administrar empleados y permisos**
2. Haz clic en **Agregar Empleado**
3. Completa datos: nombre, usuario, contraseña, rol
4. Roles disponibles:
   - **Mesero**: Toma pedidos en el POS
   - **Cocinero**: Ve comandas en Cocina
   - **Gerente**: Acceso a todas las funciones
5. Ajusta los permisos individuales del empleado y guarda los cambios. Los permisos se verifican en el servidor; ocultar un enlace no es la única protección.

Los permisos disponibles cubren el POS, lectura y edición del menú, clientes e inventario, empleados, informes, mermas, promociones, configuración, cocina, cierre de caja, propinas y consulta/eliminación de ventas suspendidas. Los nuevos empleados reciben permisos iniciales según su rol; el gerente tiene todos los permisos por defecto.

### Registrar gastos
1. Asigna al empleado el permiso **Registrar y consultar gastos del restaurante** desde **Empleados y permisos**.
2. El empleado con permiso abre **Gastos** desde el panel, registra concepto, categoría, monto, fecha y forma de pago, y consulta el historial.
3. Sin el permiso, el acceso no aparece y el servidor bloquea la página.

### Historial del sistema
El **Historial del sistema** registra inicios de sesión, ventas, cambios de empleados, menú, inventario, propinas, compras, caja, gastos y otras operaciones administrativas. Solo está disponible para la cuenta especial del programador (`super`) y las cuentas con rol **Gerente**. Permite filtrar por fechas, acción y área.

### Diseñar e imprimir recibos
En **Configuración → Recibos**, modifica el ancho de papel, la letra, la altura mínima y los márgenes. La vista previa se actualiza al cambiar los valores y permite arrastrar las bandas amarillas de margen. Sube imágenes locales PNG, JPG o WEBP (máximo 2 MB) para el logo y/o un QR; puedes ubicarlos al inicio o al pie del recibo. Los controles de IVA y propina permiten mostrar sus renglones incluso cuando el importe sea $0.00. La impresión del navegador puede agregar encabezados configurados por el propio navegador; usa márgenes de página cero y desactiva encabezados/pies en el diálogo de impresión si aparecen.

Las ventas con formas de pago personalizadas también aparecen con su propio total en el desglose del cierre de caja y se guardan como “otros métodos” en el registro del cierre.

### 4. Crear Menú
1. Ve a **Menú**
2. Haz clic en **Agregar Producto**
3. Completa: nombre, precio, categoría
4. Categorías recomendadas: Entradas, Platos principales, Bebidas, Postres

## Operación Diaria

### Tomar un Pedido (Mesero)
1. Entra en **Punto de Venta**
2. **Selecciona tu nombre** en "Mesero"
3. **Elige una mesa** en el panel "Mesa y mesero", situado junto al menú en pantallas amplias
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
4. En el encabezado del POS, haz clic en **Ventas en espera**
5. Busca la venta por folio, mesa o mesero. **Editar y cobrar** permite cambiar cantidades, notas, quitar o agregar productos, guardar los cambios y completar el cobro.

### Pedidos de Reparto
La campana del encabezado abre **Notificaciones de pedidos**. La pantalla mostrará los detalles cuando exista una integración autorizada; por ahora, no recibe ni guarda pedidos de Uber Eats, DiDi Food o Rappi. Para habilitarla, el restaurante necesita aprobación y credenciales oficiales de cada plataforma.

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

También puedes iniciar una venta desde el acceso **Comenzar una venta** del panel principal.

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

### Administrar Inventario
1. Abre **Inventario** desde el menú lateral.
2. Busca por nombre, código de barras, categoría o proveedor, y combina la búsqueda con el filtro de categoría.
3. Usa **Nuevo artículo** para registrar código, categoría, existencias, mínimo/máximo, costo, precio de venta y proveedor.
4. Los artículos por debajo de su mínimo se identifican en la tabla y en la sección de reposición.
5. Si se actualiza una instalación existente, respalda la base de datos y ejecuta `setup.php` para agregar los nuevos campos del inventario.

### Registrar Compras
1. Abre **Compras** desde el menú lateral o desde Inventario.
2. Indica proveedor, folio y fecha; agrega uno o más artículos con su cantidad y costo unitario.
3. Al guardar, el sistema registra el historial, aumenta las existencias y actualiza el costo del artículo con el último costo de compra.
4. Consulta **Historial de compras** para ver el detalle y las observaciones de cada registro.
5. Se requiere permiso de edición de inventario para registrar compras; el historial está disponible para quienes pueden consultar inventario.

### Gestionar Empleados
1. Ve a **Empleados** para consultar las cuentas activas y sus permisos.
2. Desde una cuenta de Gerente puedes agregar empleados y cambiar sus permisos.
3. Usa **Suspender** para vacaciones, permisos temporales o suspensiones. El empleado no podrá iniciar sesión ni aparecer como mesero asignable; usa **Reactivar** cuando regrese.
4. Usa **Archivar** cuando alguien deje de trabajar en el restaurante. El archivo conserva sus ventas históricas y no borra permanentemente la cuenta.
5. Abre la pestaña **Archivados** para restaurar a una persona que regrese. La cuenta restaurada podrá iniciar sesión otra vez.
6. La última cuenta de Gerente activa no se puede suspender ni archivar.

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

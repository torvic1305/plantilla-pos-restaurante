# Punto de Venta para Restaurante

Sistema de punto de venta profesional para restaurante construido con HTML5, CSS, JavaScript y PHP, usando MySQL para la base de datos. Diseñado para competir con sistemas como Square POS, Toast, y Lightspeed.

## Características Principales

### Operación POS
- ✅ Interfaz intuitiva con selección de mesas y meseros
- ✅ Menú con búsqueda y filtrado por categoría
- ✅ Carrito de compras con agregar/quitar items
- ✅ Notas especiales por item (instrucciones de cocina)
- ✅ Descuentos manuales y automáticos (promociones)
- ✅ Cálculo automático de impuestos
- ✅ Formas de pago configurables y desglose por método en el cierre de caja
- ✅ Cálculo de cambio para efectivo
- ✅ Sistema de propinas con quick-select (0%, 10%, 15%, 20%)
- ✅ Suspensión y reanudación de ventas
- ✅ Lista independiente de ventas en espera para buscar, editar productos/notas y completar cobros
- ✅ Panel de mesas y meseros separado a la izquierda del menú en pantallas amplias
- ✅ Acceso desde campana a la pantalla de pedidos de reparto (la integración con plataformas requiere acceso oficial)
- ✅ Modal de confirmación antes de cobrar
- ✅ Validación en servidor de productos y precios vigentes al guardar ventas y añadir artículos a ventas suspendidas
- ✅ Venta directa de artículos comercializables del inventario, con cantidades por producto
- ✅ Recetas por platillo con consumo automático de insumos al cobrar (también al completar una venta suspendida)
- ✅ Registro de movimientos de inventario y alerta de existencias bajas con acceso directo a reposición
- ✅ Stock fraccionario con precisión de tres decimales; las ventas pueden continuar aunque el inventario quede negativo

### Impresión y Recibos
- ✅ Pre-recibo (preview del cliente)
- ✅ Recibo final completo
- ✅ Comandas separadas para cocina y barra
- ✅ Impresión de comandas de cocina y barra con una sola acción desde el POS
- ✅ Envío de recibos y comandas a impresoras térmicas de red ESC/POS por estación
- ✅ Impresión directo a impresora de recibos 80mm
- ✅ Reimpresión de documentos (desde POS e informes)
- ✅ Cierre de caja con impresión

### Reportes y Análisis
- ✅ Informes de ventas por fecha
- ✅ Reporte por mesero con productividad
- ✅ Análisis por hora del día
- ✅ Desglose por método de pago
- ✅ Totales de descuentos e impuestos
- ✅ Tracking de propinas

### Gestión Administrativa
- ✅ Panel de empleados con roles (Mesero, Cocinero, Gerente)
- ✅ Acceso desde Configuración para administrar empleados y permisos
- ✅ Suspensión temporal de empleados y archivo reversible que conserva el historial de ventas
- ✅ Gestión de menú con precios y categorías
- ✅ Inventario con búsqueda por campos, categorías, código de barras, costo y precio de venta
- ✅ Registro de compras con proveedor, folio, detalle por artículo e incremento transaccional de existencias
- ✅ Sistema de promociones con rango de fechas
- ✅ Registro de merma (pérdidas/daños)
- ✅ Kitchen Display System (KDS) con estados de preparación, antigüedad de comandas y actualización automática
- ✅ Cierre de caja diario con resumen completo
- ✅ Auditoría de todas las operaciones
- ✅ Configuración personalizable de impuestos, mesas, POS, recibos y apariencia
- ✅ Paleta predeterminada negro y dorado, personalizable desde Configuración
- ✅ Permisos granulares por empleado, validados también en el servidor
- ✅ Permiso individual para mostrar u ocultar la gráfica de ventas del panel y filtros por hoy, ayer, semana, mes, año actual, año anterior o rango personalizado

### Seguridad
- ✅ Autenticación obligatoria
- ✅ Hashing de contraseñas con PASSWORD_DEFAULT
- ✅ Control de roles y permisos
- ✅ Log de auditoría de operaciones
- ✅ Validación de datos en servidor
- ✅ Protección CSRF en formularios

## Instalación

1. Inicia MySQL y configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` en Apache si no usas los valores predeterminados (`localhost`, `restaurante_pos`, `root`, sin contraseña).
2. Ejecuta `setup.php` desde el mismo servidor (`http://localhost/restaurante/setup.php`) para la instalación inicial. El instalador crea las tablas y solicita crear una cuenta gerente propia; no crea usuarios ni contraseñas predeterminados.
3. En instalaciones existentes, inicia sesión como gerente, abre `setup.php` y confirma la migración con el formulario protegido por CSRF. Las ventas anteriores a una actualización del KDS no se reabren como comandas; las ventas nuevas entran pendientes. Requiere MySQL 5.7.8 o posterior.
4. Accede a `index.php` para iniciar sesión. Mantén el instalador inicial fuera del acceso público; el bootstrap solo acepta conexiones locales.

Las instalaciones anteriores podrían conservar cuentas de demostración ya creadas. Cambia sus contraseñas y elimina o desactiva las cuentas que no uses antes de exponer el sistema a una red.

## Estructura de Archivos

```
restaurante/
├── index.php              # Página de login
├── dashboard.php          # Panel principal
├── pos.php                # Sistema punto de venta
├── guardar_venta.php      # Endpoint API venta
├── completar_suspendida.php # Endpoint API suspensión
├── informes.php           # Reportes básicos
├── reportes_avanzados.php # Reportes con análisis
├── cierre_caja.php        # Cierre diario
├── empleados.php          # Gestión de staff
├── menu.php               # Gestión de menú
├── merma.php              # Registro de pérdidas
├── promociones.php        # Gestión de promociones
├── cocina.php             # Kitchen Display System
├── actualizar_estado_comanda.php # Actualización segura de estados de cocina
├── config.php             # Configuración app
├── data.php               # Capa de datos (BD)
├── style.php              # CSS dinámico
├── setup.php              # Instalación inicial
├── utilidades.js          # Utilidades JavaScript compartidas
├── config.json            # Configuración en JSON
└── README.md              # Este archivo
```

## Mejoras Recientes (14 Mayo 2026)

### Correcciones
- ✅ Error de sintaxis en pos.php línea 9 corregido
- ✅ Validación PHP de todos los archivos

### Nuevas Características
- ✅ **Notas en Items**: Modal para agregar instrucciones especiales
- ✅ **Descuentos Manuales**: Campo configurable en cada venta
- ✅ **Impuestos Dinámicos**: Cálculo y desglose en recibos
- ✅ **Cierre de Caja Profesional**: Sistema completo de arqueo diario
- ✅ **Reportes Avanzados**: 3 tipos de análisis con filtros
- ✅ **Auditoría Completa**: Tabla de logs con usuario, acción, fecha

### Cambios en Base de Datos
- Nueva columna `impuesto` en tabla `ventas`
- Nueva tabla `audit_log` para auditoría
- Nueva tabla `cierres_caja` para arqueos
- Nueva tabla `inventario` para stock básico

## Tecnología

- **Frontend**: HTML5, CSS3, JavaScript Vanilla
- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL 5.7+
- **Servidor**: Apache (XAMPP recomendado)
- **Impresión**: Thermal receipt printer compatible 80mm

## Mejoras Futuras Planeadas

### Corto Plazo
- [ ] Modificadores de items (tamaño, extras, preparación)
- [ ] Clientes con historial de compra
- [ ] Integración de caja registradora física

### Mediano Plazo
- [ ] Dashboard con gráficas en tiempo real
- [ ] Notificaciones WebSocket para cocina
- [ ] Sistema de puntos/fidelización
- [ ] Multi-sede/sucursal

### Largo Plazo
- [ ] Aplicación móvil (React Native)
- [ ] Sincronización en la nube
- [ ] Integraciones con plataformas de delivery
- [ ] Análisis predictivo de ventas

## Comparación con Sistemas Profesionales

| Función | Nuestro POS | Square | Toast | Lightspeed |
|---------|------------|--------|-------|-----------|
| POS Básico | ✅ | ✅ | ✅ | ✅ |
| Reportes Avanzados | ✅ | ✅ | ✅ | ✅ |
| Cierre de Caja | ✅ | ✅ | ✅ | ✅ |
| Auditoría | ✅ | ✅ | ✅ | ✅ |
| KDS (Cocina) | ✅ | ✅ | ✅ | ✅ |
| Control de Inventario | Básico | ✅ | ✅ | ✅ |
| Clientes/Fidelización | Planeado | ✅ | ✅ | ✅ |
| Multi-sede | Planeado | ✅ | ✅ | ✅ |
| Costo | Gratis | $75-300/mes | $500+/mes | $500+/mes |

## Contribución

Para reportar errores o sugerir mejoras, contacta al equipo de desarrollo.

## Licencia

Código cerrado - Reservados todos los derechos.  
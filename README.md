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
- ✅ Modal de confirmación antes de cobrar

### Impresión y Recibos
- ✅ Pre-recibo (preview del cliente)
- ✅ Recibo final completo
- ✅ Comandas separadas para cocina y barra
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
- ✅ Gestión de menú con precios y categorías
- ✅ Sistema de promociones con rango de fechas
- ✅ Registro de merma (pérdidas/daños)
- ✅ Kitchen Display System (KDS) para cocina
- ✅ Cierre de caja diario con resumen completo
- ✅ Auditoría de todas las operaciones
- ✅ Configuración personalizable de impuestos, mesas, POS, recibos y apariencia
- ✅ Permisos granulares por empleado, validados también en el servidor

### Seguridad
- ✅ Autenticación obligatoria
- ✅ Hashing de contraseñas con PASSWORD_DEFAULT
- ✅ Control de roles y permisos
- ✅ Log de auditoría de operaciones
- ✅ Validación de datos en servidor
- ✅ Protección CSRF en formularios

## Instalación

1. Inicia MySQL y configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` en Apache si no usas los valores predeterminados (`localhost`, `restaurante_pos`, `root`, sin contraseña).
2. Ejecuta `setup.php` en tu navegador: `http://localhost/restaurante/setup.php`. El instalador crea y actualiza las tablas; requiere MySQL 5.7.8 o posterior.
3. Accede a `index.php` para iniciar sesión.

## Usuarios por Defecto

| Usuario | Contraseña | Rol |
|---------|-----------|-----|
| admin | pointofsale | Gerente |
| super | VicSuper$1 | Gerente |
| mesero1 | mesero123 | Mesero |
| cocinero1 | cocinero123 | Cocinero |

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
├── config.php             # Configuración app
├── data.php               # Capa de datos (BD)
├── style.php              # CSS dinámico
├── setup.php              # Instalación inicial
├── 2.js                   # JavaScript compartido
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
- [ ] Inventario con SKU y códigos de barras
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
#  VendAR - Sistema de Gestión para Kioscos (SaaS)

**VendAR** es un sistema integral de gestión y Punto de Venta (POS) diseñado para kioscos, minimercados y cadenas comerciales. 

Construido bajo una arquitectura **SaaS Multi-Tenant**, permite que un solo código base sirva a múltiples clientes, manteniendo los datos de cada comercio (Tenant) totalmente aislados mediante esquemas de base de datos.

##  Stack Tecnológico (TALL + Filament)

* **Backend:** PHP 8.2+ / Laravel 11
* **Base de Datos:** PostgreSQL (Multi-Schema)
* **Frontend (POS):** Livewire v3 + Alpine.js + Tailwind CSS
* **Back-office:** Filament PHP v3
* **Arquitectura SaaS:** Stancl/Tenancy
* **Autenticación y Roles:** Spatie Laravel Permission

##  Características Principales

* **Aislamiento de Datos:** Cada comercio opera en su propio esquema de PostgreSQL.
* **Gestión Multi-Sucursal:** Soporte para múltiples locales por cliente, con inventario independiente.
* **POS Reactivo:** Ventas en mostrador en tiempo real sin recargar la página.
* **Catálogo Centralizado:** Productos administrados a nivel comercio, stock gestionado a nivel sucursal.
* **Control de Accesos:** Roles y permisos granulares por sucursal.

##  Requisitos Previos

* PHP 8.2+
* Composer
* Node.js & NPM
* PostgreSQL (Estrictamente necesario para el aislamiento de schemas)

##  Instalación Local

1. **Clonar el repositorio:**
   ```bash
   git clone (https://github.com/tu-usuario/vendar.git)
   cd vendar

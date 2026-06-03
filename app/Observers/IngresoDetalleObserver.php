<?php
// Observer eliminado: la actualización de stock ya se maneja en
// IngresoMercaderiaController::store() dentro de una transacción (L146-159).
// Esta lógica duplicada sin transacción ni lock causaba data races en stock.
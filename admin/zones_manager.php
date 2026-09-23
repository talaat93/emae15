<?php
declare(strict_types=1);
/**
 * Ancien écran « Zones géographiques ».
 *
 * Il faisait doublon avec la page Zones d'intervention, qui rassemble
 * désormais la liste, les villes, les départements et l'ordre d'affichage.
 * On conserve l'adresse pour les liens et les favoris existants.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
redirect_to('admin/zones.php');

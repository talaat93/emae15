<?php
declare(strict_types=1);
/**
 * /llms.txt : présentation du site pour les assistants IA (format llmstxt.org, Markdown).
 * Généré à partir des mêmes données que le site (villes, coordonnées, tarifs) pour ne jamais
 * dire autre chose que les pages ; aucune promesse qui n'y figure pas.
 */
require_once __DIR__.'/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$abs = static fn(string $u): string => absolute_url($u);
$name = company_name();
$parDep = [];
foreach (array_keys(city_pages()) as $s) { $c = city_page($s); $parDep[$c['dep'].' ('.$c['dep_code'].')'][] = $c; }

$o  = "# {$name} — Entreprise Multi-technique Avancée\n\n";
$o .= "> Dépannage, installation et entretien en électricité, plomberie, chauffage et climatisation pour les particuliers et les professionnels. "
    . "Zone d'intervention : la Côte-d'Or (21), le Jura (39) et le Doubs (25), en Bourgogne-Franche-Comté, depuis notre base de Dole.\n\n";
$o .= "- ".hours_text()."\n";
$o .= "- ".intervention_terms_text()."\n";
$o .= "- Prix annoncé avant tout déplacement ; travaux sur devis gratuit et sans engagement.\n";
$o .= "- Téléphone : ".company_phone()." · E-mail : ".company_email()."\n\n";

$o .= "## Zone d'intervention\n\n";
$o .= "Côte-d'Or (21), Jura (39) et Doubs (25) : les villes ci-dessous et leurs communes voisines. "
    . "Pour une autre commune de ces départements, appeler pour vérifier.\n\n";
foreach ($parDep as $dep => $villes) {
    $o .= "### {$dep}\n\n";
    foreach ($villes as $c) $o .= "- [{$c['nom']} ({$c['cp']})](".$abs($c['url'])."): ".implode(', ', array_slice($c['voisines'], 0, 5))."\n";
    $o .= "\n";
}

$o .= "## Services\n\n";
foreach ([['electricite', 'Électricité : panne, tableau électrique, mise aux normes'],
          ['plomberie', 'Plomberie : fuite, débouchage, chauffe-eau'],
          ['chauffage', 'Chauffage : chaudière, pompe à chaleur, radiateurs'],
          ['climatisation', 'Climatisation'],
          ['vmc', 'VMC'],
          ['portail', 'Portail automatique et visiophone']] as [$r, $l]) {
    $o .= "- [{$l}](".$abs(route_url($r)).")\n";
}
$o .= "\n## Pages utiles\n\n";
if (tarifs_enabled()) $o .= "- [Tarifs](".$abs(route_url('tarifs'))."): déplacement, diagnostic et main-d'œuvre TTC\n";
$o .= "- [Nos zones d'intervention](".$abs(route_url('zones')).")\n";
$o .= "- [Toutes nos villes](".$abs(url_for('intervention/')).")\n";
$o .= "- [Demander un devis](".$abs(route_url('quote')).")\n";
$o .= "- [Contact](".$abs(route_url('contact')).")\n";
$o .= "- [Mentions légales](".$abs(route_url('mentions-legales')).")\n";
echo $o;

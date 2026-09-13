<?php
// Diagnostic Phase 3 — supprimez ce fichier après le test
$ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnu';
echo "<h2>Diagnostic géolocalisation</h2>";
echo "<p>Votre IP : <strong>$ip</strong></p>";

// Test ip-api.com
$url = 'http://ip-api.com/json/'.$ip.'?fields=status,zip,city,regionName&lang=fr';
echo "<p>URL testée : $url</p>";

$ctx = stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true]]);
$json = @file_get_contents($url, false, $ctx);

if ($json === false) {
    echo "<p style='color:red'><strong>BLOQUÉ</strong> — file_get_contents vers ip-api.com est bloqué par l'hébergeur.</p>";
} else {
    $data = json_decode($json, true);
    echo "<p style='color:green'><strong>OK</strong> — Réponse reçue :</p>";
    echo "<pre>".htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))."</pre>";
    $zip = $data['zip'] ?? '';
    $code = substr($zip, 0, 2);
    echo "<p>Code département détecté : <strong>$code</strong></p>";
    $map = ['39'=>'jura','25'=>'doubs','21'=>'cote-dor','01'=>'ain','38'=>'isere',
            '75'=>'paris','77'=>'seine-et-marne','78'=>'yvelines','91'=>'essonne',
            '92'=>'hauts-de-seine','93'=>'seine-saint-denis','94'=>'val-de-marne','95'=>'val-d-oise'];
    if (isset($map[$code])) {
        echo "<p style='color:green'>✓ Département dans nos zones → redirect vers <strong>".$map[$code]."</strong></p>";
    } else {
        echo "<p style='color:orange'>⚠ Département <strong>$code</strong> hors zone (non couvert par Phase 3)</p>";
    }
}
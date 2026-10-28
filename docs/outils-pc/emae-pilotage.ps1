# =====================================================================
#  EMAE Pilotage - le site, Google Ads et Pennylane réunis sur le PC
#  Lancement :  powershell -ExecutionPolicy Bypass -File .\emae-pilotage.ps1
#
#  - affiche le résumé de la page Pilotage du site (dispatcher/pilotage.php) ;
#  - envoie au site les chiffres Google Ads (rapport CSV exporté de Google Ads) ;
#  - lance « Claude EMAE Pilotage » : un Claude qui voit les 3 dossiers (site,
#    Google Ads, Pennylane) et propose puis applique les optimisations.
#
#  Réglages enregistrés dans %APPDATA%\emae-pilotage (jeton chiffré pour ta
#  session Windows). Source versionnée : docs/outils-pc/ du dépôt emae15.
# =====================================================================

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Dossier      = Join-Path $env:APPDATA 'emae-pilotage'
$FichierConf  = Join-Path $Dossier 'config.json'
$FichierJeton = Join-Path $Dossier 'jeton.xml'
$DossierClaude = Join-Path $env:USERPROFILE 'ClaudeEmaePilotage'
New-Item -ItemType Directory -Force -Path $Dossier | Out-Null

# ---------------------------------------------------------------------
#  Réglages : adresse du site et dossiers des trois projets
# ---------------------------------------------------------------------
function Read-Dossier($question, $defaut) {
    while ($true) {
        $v = (Read-Host "$question [$defaut]").Trim().Trim('"')
        if (-not $v) { $v = $defaut }
        if (-not $v -or (Test-Path $v)) { return $v }
        Write-Host "Dossier introuvable : $v" -ForegroundColor Yellow
    }
}

function Get-Conf {
    if (Test-Path $FichierConf) { return Get-Content -Raw $FichierConf | ConvertFrom-Json }
    Write-Host ''
    Write-Host 'Premier lancement : où sont tes trois projets ? (Entrée = proposition entre crochets, vide si inconnu)' -ForegroundColor Cyan
    $adsDef = @('C:\emae-ads-automation') | Where-Object { Test-Path $_ } | Select-Object -First 1
    $siteDef = @('C:\xampp\htdocs\emae15', 'C:\xampp\htdocs\emaee', "$env:USERPROFILE\emae15") | Where-Object { Test-Path $_ } | Select-Object -First 1
    $c = [pscustomobject]@{
        site_url  = 'https://emaee.fr'
        site      = Read-Dossier 'Dossier du site emaee.fr (code)' $siteDef
        ads       = Read-Dossier 'Dossier de l''outil Google Ads' $adsDef
        pennylane = Read-Dossier 'Dossier Claude Pennylane' (Join-Path $env:USERPROFILE 'ClaudePennylane')
    }
    $u = (Read-Host "Adresse du site [$($c.site_url)]").Trim()
    if ($u) { $c.site_url = $u.TrimEnd('/') }
    $c | ConvertTo-Json | Set-Content -Path $FichierConf -Encoding UTF8
    $c
}

# ---------------------------------------------------------------------
#  Jeton partagé avec le site (Réglages → Pilotage)
# ---------------------------------------------------------------------
function New-Jeton {
    $car = [char[]]'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $b = New-Object byte[] 40; $rng.GetBytes($b)
    $j = -join ($b | ForEach-Object { $car[$_ % $car.Length] })
    ConvertTo-SecureString $j -AsPlainText -Force | Export-Clixml $FichierJeton
    try { Set-Clipboard -Value $j } catch {}
    Write-Host ''
    Write-Host '  Nouveau jeton de liaison (déjà copié dans le presse-papiers) :' -ForegroundColor Cyan
    Write-Host "  $j" -ForegroundColor White
    Write-Host '  Colle-le dans le site : Dispatcher > Réglages > Pilotage > « Jeton de l''outil du PC », puis Enregistrer.' -ForegroundColor Cyan
    Write-Host '  Il ne sera plus affiché ensuite (menu J pour en créer un nouveau).' -ForegroundColor DarkGray
    Read-Host 'Appuie sur Entrée une fois collé' | Out-Null
}

function Get-Jeton {
    if (-not (Test-Path $FichierJeton)) { New-Jeton }
    $s = Import-Clixml $FichierJeton
    $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
    try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b) }
}

function Invoke-Site {
    param([string]$Methode = 'GET', [string]$Query = '', [byte[]]$Corps = $null, [string]$Type = 'application/json')
    $j = Get-Jeton
    $p = @{
        Method  = $Methode
        Uri     = "$($script:Conf.site_url)/api/pilotage.php$(if ($Query) { '?' + $Query })"
        Headers = @{ Authorization = "Bearer $j"; 'X-Pilotage-Token' = $j }
    }
    if ($Corps) { $p.Body = $Corps; $p.ContentType = $Type }
    try { Invoke-RestMethod @p }
    catch {
        $code = $null; if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
        if ($code -eq 401) { Write-Host 'Le site refuse le jeton : colle-le dans Dispatcher > Réglages > Pilotage (ou menu J pour en créer un nouveau).' -ForegroundColor Red }
        else { Write-Host "Erreur ($code) : $($_.Exception.Message)" -ForegroundColor Red }
        if ($_.ErrorDetails.Message) { Write-Host $_.ErrorDetails.Message -ForegroundColor DarkRed }
        $null
    }
}

# ---------------------------------------------------------------------
#  Actions
# ---------------------------------------------------------------------
function Format-Euro($v) { if ($null -eq $v) { '—' } else { '{0:N2} €' -f [double]$v } }

function Show-Resume([int]$jours) {
    $r = Invoke-Site -Query "jours=$jours"; if (-not $r) { return }
    $t = $r.funnel.totals
    Write-Host ''
    Write-Host "  PILOTAGE - $jours derniers jours (du $($r.periode.du) au $($r.periode.au))" -ForegroundColor Green
    Write-Host ("  Google Ads dépensé : {0}   ({1} clics)" -f (Format-Euro $t.cost), $t.clicks)
    Write-Host ("  Demandes reçues    : {0}   dont {1} via Google Ads   coût par demande : {2}" -f $t.leads, $t.leads_ads, (Format-Euro $t.cpl_ads))
    Write-Host ("  Interventions      : {0}   dont {1} via Google Ads" -f $t.interventions, $t.interventions_ads)
    Write-Host ("  Facturé HT         : {0}   dont {1} via Google Ads" -f (Format-Euro $t.ca_ht), (Format-Euro $t.ca_ht_ads))
    if ($null -ne $t.roas) { Write-Host ("  1 € de pub rapporte {0:N1} €" -f [double]$t.roas) -ForegroundColor Cyan }
    Write-Host ''
    Write-Host '  À FAIRE :' -ForegroundColor Yellow
    if (-not $r.recommandations) { Write-Host '  Rien d''urgent.' -ForegroundColor Green }
    foreach ($x in $r.recommandations) {
        $c = @{ 1 = 'Red'; 2 = 'Yellow'; 3 = 'Cyan' }[[int]$x.niveau]
        Write-Host ("  [{0}] {1}" -f $x.outil, $x.titre) -ForegroundColor $c
        Write-Host "        $($x.detail)" -ForegroundColor DarkGray
    }
    if ($r.funnel.campaigns) {
        Write-Host ''
        $r.funnel.campaigns | ForEach-Object {
            [pscustomobject]@{ Campagne = $_.campaign; Dépensé = Format-Euro $_.cost; Demandes = [math]::Round($_.leads, 1)
                               Facturé = Format-Euro $_.ca_ht; 'Rapporte/€' = if ($null -ne $_.roas) { '{0:N1}' -f [double]$_.roas } else { '—' } }
        } | Format-Table -AutoSize | Out-Host
    }
}

function Send-RapportAds {
    $f = $null
    try {
        Add-Type -AssemblyName System.Windows.Forms
        $d = New-Object System.Windows.Forms.OpenFileDialog
        $d.Filter = 'Rapport Google Ads (*.csv)|*.csv|Tous les fichiers|*.*'
        $d.InitialDirectory = Join-Path $env:USERPROFILE 'Downloads'
        if ($d.ShowDialog() -eq 'OK') { $f = $d.FileName }
    } catch { $f = (Read-Host 'Chemin du fichier CSV').Trim('"') }
    if (-not $f) { return }
    $r = Invoke-Site -Methode 'POST' -Corps ([IO.File]::ReadAllBytes($f)) -Type 'text/csv'
    if ($r) {
        Write-Host "$($r.lignes) ligne(s) Google Ads enregistrée(s) sur le site." -ForegroundColor Green
        $r.erreurs | ForEach-Object { Write-Host "  $_" -ForegroundColor Yellow }
    }
}

# ---------------------------------------------------------------------
#  Dossier « Claude EMAE Pilotage » : consignes + outil d'accès au site
# ---------------------------------------------------------------------
function Install-ClaudePilotage {
    New-Item -ItemType Directory -Force -Path (Join-Path $DossierClaude '.claude') | Out-Null
    $c = $script:Conf

$outil = @'
# Accès au site pour Claude EMAE Pilotage. Exemples :
#   powershell -NoProfile -ExecutionPolicy Bypass -File site.ps1 resume 30
#   powershell -NoProfile -ExecutionPolicy Bypass -File site.ps1 envoyer C:\chemin\rapport.csv
#   powershell -NoProfile -ExecutionPolicy Bypass -File site.ps1 envoyer C:\chemin\chiffres.json
param([Parameter(Position = 0)][string]$Action = 'resume', [Parameter(Position = 1)][string]$Arg = '30')
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$dir = Join-Path $env:APPDATA 'emae-pilotage'
$conf = Get-Content -Raw (Join-Path $dir 'config.json') | ConvertFrom-Json
$s = Import-Clixml (Join-Path $dir 'jeton.xml')
$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
$j = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b); [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)
$p = @{ Uri = "$($conf.site_url)/api/pilotage.php"; Headers = @{ Authorization = "Bearer $j"; 'X-Pilotage-Token' = $j } }
try {
    if ($Action -eq 'resume') { $p.Method = 'GET'; $p.Uri += "?jours=$([int]$Arg)" }
    elseif ($Action -eq 'envoyer') {
        $p.Method = 'POST'; $p.Body = [IO.File]::ReadAllBytes((Resolve-Path $Arg))
        $p.ContentType = if ($Arg -match '\.json$') { 'application/json' } else { 'text/csv' }
    } else { Write-Output 'Actions : resume [jours] | envoyer <fichier.csv|fichier.json>'; exit 1 }
    Invoke-RestMethod @p | ConvertTo-Json -Depth 20
} catch {
    Write-Output "ERREUR : $($_.Exception.Message)"
    if ($_.ErrorDetails.Message) { Write-Output $_.ErrorDetails.Message }
    exit 1
}
'@
    Set-Content -Path (Join-Path $DossierClaude 'site.ps1') -Value $outil -Encoding UTF8

$md = @'
# Claude EMAE Pilotage

Tu es le copilote d'EMAE (entreprise multitechnique : électricité, plomberie, chauffage,
climatisation, VMC, portail/visiophone, automatismes ; site global sur le Doubs, le Jura et
la Côte-d'Or). Ton rôle : faire travailler ensemble les trois outils pour que chaque euro
de publicité rapporte le plus de chantiers facturés.
Réponds en français, simplement : l'utilisateur n'est pas développeur.

## Les trois projets

| Projet | Dossier | Ce qu'on y fait |
|---|---|---|
| Site emaee.fr | `{{SITE}}` | pages, textes, pages d'atterrissage Google Ads, dispatcher |
| Google Ads | `{{ADS}}` | campagnes, budgets, mots-clés, annonces |
| Pennylane | `{{PENNYLANE}}` | factures, devis, avoirs, clients |

Avant d'agir dans un projet, lis son `CLAUDE.md` (et pour le site, `passation.md`) : ses
règles s'appliquent. Point critique : un `git push` sur `main` du site le met en production
immédiatement ; ne pousse jamais sans accord explicite, et lance d'abord `bash tests/run.sh`.

## Ce qui relie les trois

- Une demande venue de Google Ads arrive sur une page d'atterrissage
  (`?route=landing&metier=…&dept=…`) et porte la source `landing_{métier}_{département}`.
- Le dispatcher transforme la demande en intervention, puis en facture (Pennylane).
- La page **Pilotage** du site (`{{URL}}/dispatcher/pilotage.php`) croise tout :
  dépense par campagne → demandes → interventions → chiffre d'affaires HT, et liste
  les actions à faire. Elle a besoin des chiffres Google Ads, que tu lui envoies.
- Pour que l'attribution soit exacte, chaque campagne doit porter son métier et son
  département dans son nom (ex. « Électricité Doubs »), et pointer vers la page
  d'atterrissage du même métier et département.

## Outils

```
powershell -NoProfile -ExecutionPolicy Bypass -File site.ps1 resume 30        # résumé JSON (7, 30, 90, 365 jours)
powershell -NoProfile -ExecutionPolicy Bypass -File site.ps1 envoyer <fichier> # chiffres Google Ads (.csv ou .json)
powershell -NoProfile -ExecutionPolicy Bypass -File "{{PENNYLANE}}\pl.ps1" GET /customer_invoices -Query "limit=100"
```

Format JSON accepté par `envoyer` :
`{"rows":[{"date":"2026-10-27","campaign":"Électricité Doubs","cost":12.5,"clicks":5,"impressions":120,"conversions":1,"metier":"electricite","dept":"25"}]}`
(`metier` : electricite, plomberie, chauffage, climatisation, vmc, portail, automatisme ;
`dept` : code à 2 chiffres ; tous deux facultatifs si le nom de la campagne les contient).
Pour obtenir les chiffres du jour par campagne, utilise l'outil du dossier Google Ads
(lis son CLAUDE.md / README) ; à défaut, demande à l'utilisateur d'exporter le rapport
« Campagnes » en CSV avec la colonne Jour.

Pour Pennylane, suis le `CLAUDE.md` du dossier Pennylane (format des factures et devis,
garde-fous).

## Le point pilotage (quand on te dit « fais le point », « optimise », « point de la semaine »)

1. Envoie au site les chiffres Google Ads des 30 derniers jours (par jour et par campagne).
2. Lis `site.ps1 resume 30` (et `resume 7` pour la tendance).
3. Présente en 10 lignes maximum : ce qui rapporte, ce qui coûte sans rapporter, ce qui
   attend (demandes sans réponse, brouillons, retards de paiement).
4. Propose un plan d'actions numéroté, une ligne par action, avec l'outil concerné :
   - **Google Ads** : mettre en pause, baisser ou monter un budget (par paliers de 20 %),
     ajouter des mots-clés négatifs, créer une campagne là où le site reçoit des demandes
     sans publicité ;
   - **Site** : améliorer la page d'atterrissage d'un métier/département qui reçoit des
     clics mais peu de demandes (prix annoncés, délai, zone, bouton d'appel) ;
   - **Pennylane** : finaliser les brouillons, relancer les retards, transformer les devis
     acceptés en factures ;
   - **Dispatcher** : rappeler les demandes sans réponse (la plus coûteuse des pertes).
5. N'exécute rien sans un « oui » : l'utilisateur peut répondre « fais 1, 3 et 4 ».
6. Après chaque action, vérifie le résultat et note-le dans `journal.md` (date, action,
   chiffres avant) pour mesurer l'effet au point suivant.

## Règles

- Jamais de changement de budget Google Ads de plus de 20 % d'un coup, jamais de
  suppression de campagne : mise en pause seulement.
- Ne juge pas une campagne sous 40 € de dépense : trop tôt.
- Les montants Google Ads sont HT ; compare-les au chiffre d'affaires HT.
- Les chiffres « demandes » d'une campagne sont estimés quand plusieurs campagnes visent
  le même métier et département : dis-le quand c'est le cas.
- N'affiche jamais un jeton ou une clé API.
'@
    $md = $md.Replace('{{SITE}}', [string]$c.site).Replace('{{ADS}}', [string]$c.ads).Replace('{{PENNYLANE}}', [string]$c.pennylane).Replace('{{URL}}', [string]$c.site_url)
    Set-Content -Path (Join-Path $DossierClaude 'CLAUDE.md') -Value $md -Encoding UTF8

    # Les trois dossiers sont ouverts à Claude sans qu'il ait à redemander l'accès.
    $dirs = @($c.site, $c.ads, $c.pennylane) | Where-Object { $_ -and (Test-Path $_) }
    @{ permissions = @{ additionalDirectories = @($dirs) } } | ConvertTo-Json -Depth 5 |
        Set-Content -Path (Join-Path $DossierClaude '.claude\settings.json') -Encoding UTF8
    if (-not (Test-Path (Join-Path $DossierClaude 'journal.md'))) {
        Set-Content -Path (Join-Path $DossierClaude 'journal.md') -Value "# Journal des actions de pilotage`n" -Encoding UTF8
    }
}

function Start-Claude([bool]$distance) {
    Install-ClaudePilotage
    if (-not (Get-Command claude -ErrorAction SilentlyContinue)) {
        Write-Host 'Commande « claude » introuvable dans ce PowerShell.' -ForegroundColor Yellow; return
    }
    Get-Jeton | Out-Null
    Push-Location $DossierClaude
    try {
        if ($distance) {
            Write-Host 'Sur ton téléphone : app Claude > Code, la session « ClaudeEmaePilotage » apparaît. Laisse cette fenêtre ouverte.' -ForegroundColor Green
            claude remote-control
        } else { claude }
    } finally { Pop-Location }
}

# ---------------------------------------------------------------------
#  Menu
# ---------------------------------------------------------------------
$script:Conf = Get-Conf
Get-Jeton | Out-Null

while ($true) {
    Write-Host ''
    Write-Host '================= EMAE PILOTAGE =================' -ForegroundColor Green
    Write-Host '  1  Résumé des 7 derniers jours'
    Write-Host '  2  Résumé des 30 derniers jours'
    Write-Host '  3  Envoyer un rapport Google Ads (CSV) au site'
    Write-Host '  4  Ouvrir la page Pilotage dans le navigateur'
    Write-Host '  P  Claude EMAE Pilotage : « fais le point », « optimise »…' -ForegroundColor Cyan
    Write-Host '  R  Pareil, pilotable depuis le téléphone (Remote Control)' -ForegroundColor Cyan
    Write-Host '  J  Nouveau jeton de liaison avec le site'
    Write-Host '  D  Changer les dossiers / l''adresse du site'
    Write-Host '  Q  Quitter'
    $choix = (Read-Host 'Choix').Trim().ToUpper()
    switch ($choix) {
        '1' { Show-Resume 7 }
        '2' { Show-Resume 30 }
        '3' { Send-RapportAds }
        '4' { Start-Process "$($script:Conf.site_url)/dispatcher/pilotage.php" }
        'P' { Start-Claude $false }
        'R' { Start-Claude $true }
        'J' { New-Jeton }
        'D' { Remove-Item $FichierConf -ErrorAction SilentlyContinue; $script:Conf = Get-Conf }
        'Q' { return }
        default { Write-Host 'Choix inconnu.' -ForegroundColor Yellow }
    }
}

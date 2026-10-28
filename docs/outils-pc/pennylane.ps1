# =====================================================================
#  Pennylane - console de gestion (API v2 « external »)
#  Lancement :  powershell -ExecutionPolicy Bypass -File .\pennylane.ps1
#  La clé API est demandée au premier lancement puis enregistrée,
#  chiffrée pour ta session Windows, dans %APPDATA%\pennylane\cle.xml
#  Source versionnée : docs/outils-pc/ du dépôt emae15.
# =====================================================================

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Base      = 'https://app.pennylane.com/api/external/v2'
$Dossier   = Join-Path $env:APPDATA 'pennylane'
$FichierCle = Join-Path $Dossier 'cle.xml'

# ---------------------------------------------------------------------
#  Clé API
# ---------------------------------------------------------------------
function Get-Cle {
    if (Test-Path $FichierCle) {
        $s = Import-Clixml $FichierCle
    } else {
        New-Item -ItemType Directory -Force -Path $Dossier | Out-Null
        $s = Read-Host 'Clé API Pennylane' -AsSecureString
        $s | Export-Clixml $FichierCle
        Write-Host "Clé enregistrée dans $FichierCle" -ForegroundColor DarkGray
    }
    $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
    try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b) }
}

function Reset-Cle {
    if (Test-Path $FichierCle) { Remove-Item $FichierCle }
    $script:Cle = Get-Cle
}

$script:Cle = Get-Cle

# ---------------------------------------------------------------------
#  Appels API
# ---------------------------------------------------------------------
function Invoke-PL {
    param(
        [string]$Methode = 'GET',
        [string]$Chemin,
        [hashtable]$Query = @{},
        $Corps = $null
    )
    $url = $Base + $Chemin
    if ($Query.Count) {
        $qs = ($Query.GetEnumerator() | ForEach-Object {
            [uri]::EscapeDataString($_.Key) + '=' + [uri]::EscapeDataString([string]$_.Value)
        }) -join '&'
        $url += '?' + $qs
    }
    $p = @{
        Method  = $Methode
        Uri     = $url
        Headers = @{ Authorization = "Bearer $script:Cle"; Accept = 'application/json' }
    }
    if ($null -ne $Corps) {
        $p.ContentType = 'application/json; charset=utf-8'
        $p.Body = [Text.Encoding]::UTF8.GetBytes(($Corps | ConvertTo-Json -Depth 10))
    }
    try {
        Invoke-RestMethod @p
    } catch {
        $code = $null
        if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
        if ($code -eq 401) { Write-Host 'Clé API refusée (401). Menu « C » pour la changer.' -ForegroundColor Red }
        else { Write-Host "Erreur API ($code) : $($_.Exception.Message)" -ForegroundColor Red }
        if ($_.ErrorDetails.Message) { Write-Host $_.ErrorDetails.Message -ForegroundColor DarkRed }
        $null
    }
}

# Parcourt toutes les pages d'une liste (pagination par curseur)
function Get-PLTout {
    param([string]$Chemin, [hashtable]$Query = @{}, [int]$MaxPages = 50)
    $q = $Query.Clone(); $q.limit = 100
    for ($i = 0; $i -lt $MaxPages; $i++) {
        $r = Invoke-PL -Chemin $Chemin -Query $q
        if (-not $r) { return }
        $r.items
        if (-not $r.has_more -or -not $r.next_cursor) { return }
        $q.cursor = $r.next_cursor
        Start-Sleep -Milliseconds 250
    }
}

# ---------------------------------------------------------------------
#  Affichage
# ---------------------------------------------------------------------
$Statuts = @{
    draft = 'Brouillon'; upcoming = 'À venir'; late = 'En retard'; paid = 'Payée'
    partially_paid = 'Part. payée'; cancelled = 'Annulée'; incomplete = 'Incomplète'
    archived = 'Archivée'; credit_note = 'Avoir'
}

function Format-Euro($v) { if ($null -eq $v -or $v -eq '') { '' } else { '{0:N2} €' -f [double]$v } }

function Show-Factures($liste) {
    if (-not $liste) { Write-Host 'Aucune facture.' -ForegroundColor Yellow; return }
    $liste | Sort-Object date -Descending | ForEach-Object {
        [pscustomobject]@{
            Id       = $_.id
            Numéro   = $_.invoice_number
            Date     = $_.date
            Échéance = $_.deadline
            Libellé  = if ($_.label.Length -gt 45) { $_.label.Substring(0, 45) + '…' } else { $_.label }
            TTC      = Format-Euro $_.amount
            Reste    = Format-Euro $_.remaining_amount_with_tax
            Statut   = if ($Statuts[$_.status]) { $Statuts[$_.status] } else { $_.status }
        }
    } | Format-Table -AutoSize | Out-Host
}

function Read-Id($texte = 'Id de la facture') {
    $v = (Read-Host $texte).Trim()
    if ($v -notmatch '^\d+$') { Write-Host 'Id invalide.' -ForegroundColor Yellow; return $null }
    $v
}

function Confirm-Action($texte) { (Read-Host "$texte (o/n)") -match '^[oOyY]' }

# ---------------------------------------------------------------------
#  Actions
# ---------------------------------------------------------------------
function Get-FacturesClients { Get-PLTout '/customer_invoices' }

function Show-Detail {
    $id = Read-Id; if (-not $id) { return }
    $f = Invoke-PL -Chemin "/customer_invoices/$id"; if (-not $f) { return }
    $client = Invoke-PL -Chemin "/customers/$($f.customer.id)"
    Write-Host ''
    Write-Host "  $($f.label)" -ForegroundColor Cyan
    Write-Host "  Client    : $($client.name)"
    Write-Host "  Numéro    : $(if ($f.invoice_number) { $f.invoice_number } else { '(pas encore numérotée)' })"
    Write-Host "  Date      : $($f.date)   Échéance : $($f.deadline)"
    Write-Host "  Statut    : $($Statuts[$f.status]) $(if ($f.paid) { '- payée' })"
    Write-Host "  HT        : $(Format-Euro $f.currency_amount_before_tax)   TVA : $(Format-Euro $f.currency_tax)   TTC : $(Format-Euro $f.amount)"
    if ($f.pdf_invoice_subject) { Write-Host "  Objet     : $($f.pdf_invoice_subject)" }
    if ($f.special_mention)     { Write-Host "  Mention   : $($f.special_mention)" }

    $lignes = @(Get-PLTout "/customer_invoices/$id/invoice_lines")
    Write-Host "`n  $($lignes.Count) ligne(s) :" -ForegroundColor Cyan
    $lignes | ForEach-Object {
        [pscustomobject]@{
            Libellé = $_.label
            Qté     = $_.quantity
            PU      = Format-Euro $_.raw_currency_unit_price
            HT      = Format-Euro $_.currency_amount_before_tax
            TVA     = $_.vat_rate
        }
    } | Format-Table -AutoSize | Out-Host

    $aCompleter = $lignes | Where-Object { $_.label -match 'compléter|completer|TODO|\?\?' }
    if ($aCompleter) {
        Write-Host '  ⚠ Lignes à compléter :' -ForegroundColor Yellow
        $aCompleter | ForEach-Object { Write-Host "    - $($_.label)" -ForegroundColor Yellow }
    }
}

function Save-Pdf {
    $id = Read-Id; if (-not $id) { return }
    $f = Invoke-PL -Chemin "/customer_invoices/$id"; if (-not $f) { return }
    if (-not $f.public_file_url) { Write-Host 'Pas de PDF disponible.' -ForegroundColor Yellow; return }
    $nom = if ($f.filename) { $f.filename } else { "facture-$id.pdf" }
    $nom = $nom -replace '[\\/:*?"<>|]', '_'
    $dest = Join-Path ([Environment]::GetFolderPath('MyDocuments')) $nom
    Invoke-WebRequest -Uri $f.public_file_url -OutFile $dest -UseBasicParsing
    Write-Host "PDF enregistré : $dest" -ForegroundColor Green
    if (Confirm-Action 'Ouvrir le PDF ?') { Start-Process $dest }
}

function Complete-Facture {
    $id = Read-Id 'Id du brouillon à finaliser'; if (-not $id) { return }
    $f = Invoke-PL -Chemin "/customer_invoices/$id"; if (-not $f) { return }
    if ($f.status -ne 'draft') { Write-Host 'Cette facture n''est pas un brouillon.' -ForegroundColor Yellow; return }
    $lignes = @(Get-PLTout "/customer_invoices/$id/invoice_lines")
    $aCompleter = $lignes | Where-Object { $_.label -match 'compléter|completer|TODO|\?\?' }
    if ($aCompleter) {
        Write-Host 'Attention, des lignes semblent incomplètes :' -ForegroundColor Yellow
        $aCompleter | ForEach-Object { Write-Host "  - $($_.label)" -ForegroundColor Yellow }
    }
    Write-Host "Finaliser « $($f.label) » - $(Format-Euro $f.amount) ?"
    Write-Host 'Une fois finalisée, la facture est numérotée et ne peut plus être modifiée.' -ForegroundColor DarkYellow
    if (-not (Confirm-Action 'Confirmer')) { return }
    $r = Invoke-PL -Methode 'PUT' -Chemin "/customer_invoices/$id/finalize"
    if ($r) { Write-Host "Facture finalisée : n° $($r.invoice_number)" -ForegroundColor Green }
}

function Send-Facture {
    $id = Read-Id 'Id de la facture à envoyer'; if (-not $id) { return }
    $f = Invoke-PL -Chemin "/customer_invoices/$id"; if (-not $f) { return }
    if ($f.status -eq 'draft') { Write-Host 'Finalise d''abord la facture.' -ForegroundColor Yellow; return }
    $mails = (Read-Host 'Destinataires (séparés par des virgules, vide = e-mail du client)').Trim()
    $corps = @{}
    if ($mails) { $corps.recipients = @($mails -split '\s*,\s*' | Where-Object { $_ }) }
    if (-not (Confirm-Action "Envoyer « $($f.label) » par e-mail ?")) { return }
    $r = Invoke-PL -Methode 'POST' -Chemin "/customer_invoices/$id/send_by_email" -Corps $corps
    if ($null -ne $r) { Write-Host 'Facture envoyée.' -ForegroundColor Green }
}

function Set-Payee {
    $id = Read-Id 'Id de la facture payée'; if (-not $id) { return }
    $f = Invoke-PL -Chemin "/customer_invoices/$id"; if (-not $f) { return }
    if (-not (Confirm-Action "Marquer « $($f.label) » ($(Format-Euro $f.amount)) comme payée ?")) { return }
    $r = Invoke-PL -Methode 'PUT' -Chemin "/customer_invoices/$id/mark_as_paid"
    if ($null -ne $r) { Write-Host 'Facture marquée payée.' -ForegroundColor Green }
}

function Find-Client {
    $t = (Read-Host 'Nom du client (ou partie du nom)').Trim(); if (-not $t) { return }
    Get-PLTout '/customers' | Where-Object { $_.name -like "*$t*" } | ForEach-Object {
        [pscustomobject]@{ Id = $_.id; Nom = $_.name; SIREN = $_.reg_no; TVA = $_.vat_number; Email = ($_.emails -join ', ') }
    } | Format-Table -AutoSize | Out-Host
}

function Show-Resume {
    $toutes = @(Get-FacturesClients)
    $impayees = $toutes | Where-Object { $_.status -in 'upcoming', 'late', 'partially_paid' }
    $retard   = $toutes | Where-Object { $_.status -eq 'late' }
    $brouil   = $toutes | Where-Object { $_.status -eq 'draft' }
    $somme = { param($l) ($l | ForEach-Object { [double]$(if ($_.remaining_amount_with_tax) { $_.remaining_amount_with_tax } else { $_.amount }) } | Measure-Object -Sum).Sum }
    Write-Host ''
    Write-Host ("  Factures clients : {0}" -f $toutes.Count)
    Write-Host ("  Brouillons       : {0}  ({1})" -f @($brouil).Count, (Format-Euro (& $somme $brouil))) -ForegroundColor Cyan
    Write-Host ("  À encaisser      : {0}  ({1})" -f @($impayees).Count, (Format-Euro (& $somme $impayees))) -ForegroundColor Yellow
    Write-Host ("  Dont en retard   : {0}  ({1})" -f @($retard).Count, (Format-Euro (& $somme $retard))) -ForegroundColor Red
}

# ---------------------------------------------------------------------
#  Installation du dossier ClaudePennylane (consignes + outil API)
# ---------------------------------------------------------------------
function Install-ClaudePennylane {
$Dossier = Join-Path $env:USERPROFILE 'ClaudePennylane'
New-Item -ItemType Directory -Force -Path $Dossier | Out-Null

# ---------------------------------------------------------------------
#  1. pl.ps1 : petit outil d'appel à l'API, utilisé par Claude
# ---------------------------------------------------------------------
$pl = @'
# Appel à l'API Pennylane v2. Exemples :
#   powershell -NoProfile -File pl.ps1 GET /customer_invoices/123
#   powershell -NoProfile -File pl.ps1 GET /customer_invoices/123/invoice_lines
#   powershell -NoProfile -File pl.ps1 PUT /customer_invoices/123 -CorpsFichier modif.json
param(
    [Parameter(Position = 0)][string]$Methode = 'GET',
    [Parameter(Position = 1)][string]$Chemin,
    [string]$Query = '',
    [string]$CorpsFichier = ''
)
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$fichierCle = Join-Path $env:APPDATA 'pennylane\cle.xml'
if (-not (Test-Path $fichierCle)) { Write-Output 'ERREUR : clé API absente. Lance d''abord pennylane.ps1 une fois.'; exit 1 }
$s = Import-Clixml $fichierCle
$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
$cle = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)

$url = 'https://app.pennylane.com/api/external/v2' + $Chemin
if ($Query) { $url += '?' + $Query }
$p = @{ Method = $Methode; Uri = $url; Headers = @{ Authorization = "Bearer $cle"; Accept = 'application/json' } }
if ($CorpsFichier) {
    $p.ContentType = 'application/json; charset=utf-8'
    $p.Body = [IO.File]::ReadAllBytes((Resolve-Path $CorpsFichier))
}
try {
    $r = Invoke-RestMethod @p
    if ($null -eq $r) { Write-Output 'OK (réponse vide)' } else { $r | ConvertTo-Json -Depth 20 }
} catch {
    $code = $null
    if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
    Write-Output "ERREUR HTTP $code : $($_.Exception.Message)"
    if ($_.ErrorDetails.Message) { Write-Output $_.ErrorDetails.Message }
    exit 1
}
'@
Set-Content -Path (Join-Path $Dossier 'pl.ps1') -Value $pl -Encoding UTF8

# ---------------------------------------------------------------------
#  2. CLAUDE.md : les consignes que Claude lit au démarrage
# ---------------------------------------------------------------------
$md = @'
# Claude Pennylane — EMAE

Tu es l'assistant de gestion d'EMAE (entreprise multitechnique : électricité, plomberie,
chauffage, climatisation, VMC, portail/visiophone, automatismes) dans Pennylane.
Tu fais tout ce que l'utilisateur demande : factures, devis, avoirs, clients, produits,
consultation des fournisseurs, des encaissements et de la trésorerie.
Réponds toujours en français, simplement : l'utilisateur n'est pas développeur.

## Accès à l'API

Tous les appels passent par `pl.ps1` dans ce dossier. La clé API est lue par le script :
ne la demande jamais et ne l'affiche jamais.

```
powershell -NoProfile -ExecutionPolicy Bypass -File pl.ps1 <GET|POST|PUT|DELETE> <chemin> [-Query "a=1&b=2"] [-CorpsFichier corps.json]
```

- Pour un POST/PUT : écris le corps JSON dans un fichier UTF-8, passe-le avec `-CorpsFichier`,
  puis supprime le fichier.
- Listes : pagination par curseur (`has_more`, `next_cursor` → `-Query "limit=100&cursor=..."`).
- Filtres : `-Query "filter=[{\"field\":\"label\",\"operator\":\"eq\",\"value\":\"...\"}]"` ;
  si un filtre est refusé, liste tout et cherche toi-même.
- Documentation API v2 : https://pennylane.readme.io/v2.0/reference
  En cas d'erreur 4xx, lis le message renvoyé et corrige le corps. Ne devine pas en boucle :
  après deux échecs, explique le blocage à l'utilisateur.

## Ressources (API v2)

| Sujet | Chemins |
|---|---|
| Factures clients | `GET/POST /customer_invoices`, `GET/PUT/DELETE /customer_invoices/{id}`, `GET /customer_invoices/{id}/invoice_lines`, `PUT …/{id}/finalize`, `POST …/{id}/send_by_email`, `PUT …/{id}/mark_as_paid` |
| Devis | `GET/POST /quotes`, `GET/PUT /quotes/{id}`, lignes `GET /quotes/{id}/invoice_lines`, statut `PUT /quotes/{id}/update_status`, envoi `POST /quotes/{id}/send_by_email` |
| Clients | `GET /customers`, `POST /company_customers` (pro), `POST /individual_customers` (particulier), `PUT /company_customers/{id}`, `PUT /individual_customers/{id}` |
| Produits (catalogue) | `GET/POST /products`, `PUT /products/{id}` |
| Fournisseurs (lecture) | `GET /suppliers`, `GET /supplier_invoices` |
| Banque (lecture) | `GET /bank_accounts`, `GET /transactions` |
| Modèles de facture | `GET /customer_invoice_templates` |

Le PDF d'une facture ou d'un devis est dans `public_file_url` : pour l'enregistrer,
`Invoke-WebRequest -Uri <url> -OutFile "$env:USERPROFILE\Documents\<nom>.pdf"`.

## Créer une facture ou un devis

Corps type (`POST /customer_invoices`, même logique pour `POST /quotes`) :

```json
{
  "customer_id": 123,
  "date": "2026-10-28",
  "deadline": "2026-11-27",
  "draft": true,
  "currency": "EUR",
  "language": "fr_FR",
  "label": "Facture Client – objet",
  "pdf_invoice_subject": "Objet affiché sur le PDF",
  "pdf_description": "Description affichée sur le PDF",
  "invoice_lines": [
    { "label": "Main d'œuvre", "quantity": 2, "unit": "heure", "raw_currency_unit_price": "45.00", "vat_rate": "FR_200" },
    { "label": "Déplacement", "quantity": 1, "unit": "forfait", "raw_currency_unit_price": "35.00", "vat_rate": "FR_200" }
  ]
}
```

- Prix toujours HT, en texte avec 2 décimales.
- TVA : `FR_200` (20 %), `FR_100` (10 % rénovation logement > 2 ans), `FR_55` (5,5 % rénovation énergétique),
  `exempt` pour la sous-traitance en autoliquidation (ajoute alors
  `"special_mention": "Autoliquidation : TVA due par le preneur, article 283-2 nonies du CGI."`).
- Échéance : 30 jours pour un professionnel, à réception pour un particulier, sauf demande contraire.
- Tarifs habituels EMAE : 45,00 € HT de l'heure, déplacement 35,00 € HT.
  Regarde aussi `/products` et les factures récentes du même client pour reprendre les mêmes libellés et prix.
- Crée toujours en brouillon (`draft: true`). Si le client n'existe pas, propose de le créer d'abord.

## Modifier

- Facture : seul un brouillon (`status: draft`) se modifie (`PUT /customer_invoices/{id}`).
  Les lignes se modifient via `invoice_lines` avec `create`, `update` (avec l'`id` de la ligne)
  et `delete` ; vérifie le format exact dans la documentation si la première tentative échoue.
  En dernier recours pour un brouillon : le recréer à l'identique avec les corrections puis supprimer l'ancien
  (après accord).
- Une facture finalisée ne se modifie plus : propose un avoir (facture avec montants négatifs liée
  via `credited_invoice_id`) puis une nouvelle facture.
- Devis : `PUT /quotes/{id}` ; statut accepté / refusé via `update_status`.

## Règles

1. Avant toute écriture (POST, PUT, DELETE, finalize, envoi, paiement) : montre un récapitulatif
   AVANT → APRÈS (client, lignes, montants HT/TVA/TTC) et attends un « oui » explicite.
2. Après l'écriture, relis l'objet dans Pennylane et confirme le résultat (numéro, total).
3. Ne finalise, n'envoie par e-mail et ne marque payé que si l'utilisateur le demande explicitement.
4. Ne supprime jamais une facture finalisée ni un client. Suppression d'un brouillon : seulement sur demande.
5. Les montants, SIREN, numéros de BC viennent de l'utilisateur ou de Pennylane : n'invente rien,
   demande ce qui manque.
'@
Set-Content -Path (Join-Path $Dossier 'CLAUDE.md') -Value $md -Encoding UTF8
    Write-Host "Dossier Claude installé : $Dossier" -ForegroundColor Green
}

# ---------------------------------------------------------------------
#  Menu
# ---------------------------------------------------------------------
while ($true) {
    Write-Host ''
    Write-Host '==================== PENNYLANE ====================' -ForegroundColor Green
    Write-Host '  1  Résumé (brouillons, à encaisser, retards)'
    Write-Host '  2  Factures clients - toutes'
    Write-Host '  3  Factures clients - brouillons'
    Write-Host '  4  Factures clients - impayées / en retard'
    Write-Host '  5  Rechercher une facture (client, libellé, n°)'
    Write-Host '  6  Détail d''une facture (lignes)'
    Write-Host '  7  Télécharger le PDF'
    Write-Host '  8  Finaliser un brouillon'
    Write-Host '  9  Envoyer une facture par e-mail'
    Write-Host ' 10  Marquer une facture payée'
    Write-Host ' 11  Factures fournisseurs'
    Write-Host ' 12  Rechercher un client'
    Write-Host '  P  Demander à Claude : créer/modifier factures, devis, clients…' -ForegroundColor Cyan
    Write-Host '  R  Pareil, pilotable depuis le téléphone (Remote Control)' -ForegroundColor Cyan
    Write-Host '  W  Ouvrir Pennylane dans le navigateur'
    Write-Host '  C  Changer la clé API'
    Write-Host '  Q  Quitter'
    $choix = (Read-Host 'Choix').Trim().ToUpper()
    switch ($choix) {
        '1'  { Show-Resume }
        '2'  { Show-Factures (Get-FacturesClients) }
        '3'  { Show-Factures (Get-FacturesClients | Where-Object { $_.status -eq 'draft' }) }
        '4'  { Show-Factures (Get-FacturesClients | Where-Object { $_.status -in 'upcoming', 'late', 'partially_paid' }) }
        '5'  {
                $t = (Read-Host 'Texte à chercher').Trim()
                if ($t) { Show-Factures (Get-FacturesClients | Where-Object { $_.label -like "*$t*" -or $_.invoice_number -like "*$t*" -or $_.filename -like "*$t*" }) }
             }
        '6'  { Show-Detail }
        '7'  { Save-Pdf }
        '8'  { Complete-Facture }
        '9'  { Send-Facture }
        '10' { Set-Payee }
        '11' { Show-Factures (Get-PLTout '/supplier_invoices') }
        '12' { Find-Client }
        { $_ -in 'P', 'R' } {
                $dc = Join-Path $env:USERPROFILE 'ClaudePennylane'
                Install-ClaudePennylane
                if (-not (Get-Command claude -ErrorAction SilentlyContinue)) { Write-Host 'Commande « claude » introuvable.' -ForegroundColor Yellow }
                elseif ($choix -eq 'R') {
                    Write-Host 'Ouvre l''app Claude > Code sur ton téléphone : la session « ClaudePennylane » va apparaître. Laisse cette fenêtre ouverte.' -ForegroundColor Green
                    Push-Location $dc; try { claude remote-control } finally { Pop-Location }
                }
                else { Push-Location $dc; try { claude } finally { Pop-Location } }
             }
        'W'  { Start-Process 'https://app.pennylane.com/' }
        'C'  { Reset-Cle }
        'Q'  { return }
        default { Write-Host 'Choix inconnu.' -ForegroundColor Yellow }
    }
}
